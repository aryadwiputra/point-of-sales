<?php

namespace App\Services;

use App\Models\SupplierReturn;
use App\Models\SupplierReturnItem;
use App\Models\ProductWarehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupplierReturnService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly StockMutationService $stockMutationService
    ) {}

    public function generateDocumentNumber(): string
    {
        $prefix = 'SR-'.now()->format('Ymd').'-';
        $last = SupplierReturn::where('document_number', 'like', $prefix.'%')
            ->orderByDesc('document_number')
            ->value('document_number');

        $next = $last ? (int) Str::afterLast($last, '-') + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function createReturn(array $data, array $items, int $userId): SupplierReturn
    {
        return DB::transaction(function () use ($data, $items, $userId) {
            $return = SupplierReturn::create([
                'supplier_id' => $data['supplier_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'goods_receiving_id' => $data['goods_receiving_id'] ?? null,
                'payable_id' => $data['payable_id'] ?? null,
                'document_number' => $this->generateDocumentNumber(),
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($items as $item) {
                SupplierReturnItem::create([
                    'supplier_return_id' => $return->id,
                    'goods_receiving_item_id' => $item['goods_receiving_item_id'] ?? null,
                    'product_id' => $item['product_id'],
                    'qty_returned' => $item['qty_returned'],
                    'unit_price' => $item['unit_price'] ?? 0,
                    'reason' => $item['reason'] ?? null,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            $this->auditLogService->log(
                event: 'supplier_return.created',
                module: 'purchase',
                auditable: $return,
                description: 'Supplier return '.$return->document_number.' dibuat.',
                after: [
                    'document_number' => $return->document_number,
                    'supplier_id' => $return->supplier_id,
                    'status' => 'draft',
                    'total_items' => count($items),
                ],
                meta: ['supplier_return_id' => $return->id],
            );

            return $return;
        });
    }

    public function complete(SupplierReturn $return): void
    {
        DB::transaction(function () use ($return) {
            $return->load('items');

            foreach ($return->items as $item) {
                $product = $item->product;
                $stockBefore = (int) $product->stock;
                $product->decrement('stock', $item->qty_returned);

                // Decrement pivot warehouse stock
                if ($return->warehouse_id) {
                    ProductWarehouse::where([
                        'product_id' => $product->id,
                        'warehouse_id' => $return->warehouse_id,
                    ])->decrement('stock', $item->qty_returned);
                }

                $this->stockMutationService->recordSupplierReturnOut(
                    product: $product,
                    supplierReturn: $return,
                    qty: $item->qty_returned,
                    stockBefore: $stockBefore,
                    stockAfter: (int) $product->stock,
                    notes: $item->reason ?? 'Retur barang ke supplier',
                    userId: $return->created_by,
                );
            }

            if ($return->payable_id && $return->payable) {
                $returnAmount = $return->items->sum(fn ($i) => $i->qty_returned * $i->unit_price);
                $payable = $return->payable;
                $payable->total = max(0, $payable->total - $returnAmount);
                if ($payable->total <= 0) {
                    $payable->total = 0;
                    $payable->status = 'paid';
                } elseif ($payable->paid > 0) {
                    $payable->status = $payable->paid >= $payable->total ? 'paid' : 'partial';
                }
                $payable->save();
            }

            $return->update([
                'status' => 'completed',
                'returned_at' => now(),
            ]);

            $this->auditLogService->log(
                event: 'supplier_return.completed',
                module: 'purchase',
                auditable: $return,
                description: 'Supplier return '.$return->document_number.' diselesaikan. Stok dikurangi dan hutang dikoreksi.',
                after: ['status' => 'completed'],
                meta: ['supplier_return_id' => $return->id],
            );
        });
    }

    public function cancel(SupplierReturn $return): void
    {
        DB::transaction(function () use ($return) {
            $return->update(['status' => 'cancelled']);

            $this->auditLogService->log(
                event: 'supplier_return.cancelled',
                module: 'purchase',
                auditable: $return,
                description: 'Supplier return '.$return->document_number.' dibatalkan.',
                after: ['status' => 'cancelled'],
                meta: ['supplier_return_id' => $return->id],
            );
        });
    }
}
