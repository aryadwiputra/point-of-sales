<?php

namespace App\Services;

use App\Models\ProductWarehouse;
use App\Models\StockMutation;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockTransferService
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {}

    public function generateDocumentNumber(): string
    {
        $prefix = 'ST-'.now()->format('Ymd').'-';
        $last = StockTransfer::where('document_number', 'like', $prefix.'%')
            ->orderByDesc('document_number')
            ->value('document_number');

        $next = $last ? (int) Str::afterLast($last, '-') + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function createDraft(array $data, array $items, int $userId): StockTransfer
    {
        if ($data['source_warehouse_id'] === $data['destination_warehouse_id']) {
            throw ValidationException::withMessages([
                'destination_warehouse_id' => 'Gudang asal dan tujuan harus berbeda.',
            ]);
        }

        return DB::transaction(function () use ($data, $items, $userId) {
            $transfer = StockTransfer::create([
                'source_warehouse_id' => $data['source_warehouse_id'],
                'destination_warehouse_id' => $data['destination_warehouse_id'],
                'document_number' => $data['document_number'] ?? $this->generateDocumentNumber(),
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($items as $item) {
                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $item['product_id'],
                    'qty' => $item['qty'],
                ]);
            }

            $this->auditLogService->log(
                event: 'stock_transfer.created',
                module: 'stock',
                auditable: $transfer,
                description: 'Transfer stok '.$transfer->document_number.' dibuat.',
                after: [
                    'document_number' => $transfer->document_number,
                    'source_warehouse_id' => $transfer->source_warehouse_id,
                    'destination_warehouse_id' => $transfer->destination_warehouse_id,
                    'status' => 'draft',
                    'total_items' => count($items),
                ],
                meta: ['stock_transfer_id' => $transfer->id],
            );

            return $transfer;
        });
    }

    public function send(StockTransfer $transfer, int $userId): void
    {
        DB::transaction(function () use ($transfer, $userId) {
            // ponytail: lock the transfer row and re-check status inside the transaction (prevents double-send / send-after-cancel races)
            $transfer = StockTransfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if (! $transfer->isDraft()) {
                throw ValidationException::withMessages([
                    'transfer' => 'Hanya transfer dengan status draft yang bisa dikirim.',
                ]);
            }

            $transfer->load('items.product');
            $before = $transfer->replicate();

            // Validate stock availability and decrement source warehouse stock (locked rows)
            foreach ($transfer->items as $item) {
                $pw = ProductWarehouse::where([
                    'product_id' => $item->product_id,
                    'warehouse_id' => $transfer->source_warehouse_id,
                ])->lockForUpdate()->first();

                $available = $pw ? (int) $pw->stock : 0;
                if ($available < $item->qty) {
                    throw ValidationException::withMessages([
                        'transfer' => "Stok {$item->product->title} tidak mencukupi di gudang asal (tersedia: {$available}).",
                    ]);
                }

                $stockAfter = $available - $item->qty;
                if ($pw) {
                    $pw->decrement('stock', $item->qty);
                }
                $item->product->decrement('stock', $item->qty);

                StockMutation::create([
                    'product_id' => $item->product_id,
                    'warehouse_id' => $transfer->source_warehouse_id,
                    'reference_type' => 'stock_transfer',
                    'reference_id' => $transfer->id,
                    'mutation_type' => 'out',
                    'qty' => $item->qty,
                    'stock_before' => $available,
                    'stock_after' => $stockAfter,
                    'notes' => 'Transfer ke '.$transfer->destinationWarehouse->code,
                    'created_by' => $userId,
                ]);
            }

            $transfer->update([
                'status' => 'in_transit',
            ]);

            $this->auditLogService->log(
                event: 'stock_transfer.sent',
                module: 'stock',
                auditable: $transfer,
                description: 'Transfer stok '.$transfer->document_number.' dikirim.',
                before: ['status' => $before->status],
                after: ['status' => 'in_transit'],
                meta: ['stock_transfer_id' => $transfer->id],
            );
        });
    }

    public function receive(StockTransfer $transfer, int $userId): void
    {
        DB::transaction(function () use ($transfer, $userId) {
            // ponytail: lock the transfer row and re-check status inside the transaction (prevents double-receive)
            $transfer = StockTransfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if (! $transfer->isInTransit()) {
                throw ValidationException::withMessages([
                    'transfer' => 'Hanya transfer dengan status in_transit yang bisa diterima.',
                ]);
            }

            $transfer->load('items.product');
            $before = $transfer->replicate();

            // Increment destination warehouse stock + legacy stock
            foreach ($transfer->items as $item) {
                $product = $item->product;
                $stockBefore = (int) $product->stock;

                // ponytail: firstOrCreate (NOT updateOrCreate with stock=0, which would reset an existing row)
                ProductWarehouse::firstOrCreate(
                    ['product_id' => $item->product_id, 'warehouse_id' => $transfer->destination_warehouse_id],
                    ['stock' => 0]
                )->increment('stock', $item->qty);

                $product->increment('stock', $item->qty);

                StockMutation::create([
                    'product_id' => $product->id,
                    'warehouse_id' => $transfer->destination_warehouse_id,
                    'reference_type' => 'stock_transfer',
                    'reference_id' => $transfer->id,
                    'mutation_type' => 'in',
                    'qty' => $item->qty,
                    'stock_before' => $stockBefore,
                    'stock_after' => (int) $product->stock,
                    'notes' => 'Transfer dari '.$transfer->sourceWarehouse->code,
                    'created_by' => $userId,
                ]);
            }

            $transfer->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            $this->auditLogService->log(
                event: 'stock_transfer.received',
                module: 'stock',
                auditable: $transfer,
                description: 'Transfer stok '.$transfer->document_number.' diterima.',
                before: ['status' => $before->status],
                after: ['status' => 'completed'],
                meta: ['stock_transfer_id' => $transfer->id],
            );
        });
    }

    public function cancel(StockTransfer $transfer, int $userId): void
    {
        DB::transaction(function () use ($transfer) {
            // ponytail: lock the transfer row and re-check status inside the transaction (prevents cancel-after-complete races)
            $transfer = StockTransfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if (! in_array($transfer->status, ['draft', 'in_transit'])) {
                throw ValidationException::withMessages([
                    'transfer' => 'Hanya transfer draft atau in_transit yang bisa dibatalkan.',
                ]);
            }

            $before = $transfer->replicate();
            $returnStock = $transfer->isInTransit();

            // If sent but not received, return stock to source
            if ($returnStock) {
                $transfer->load('items.product');

                foreach ($transfer->items as $item) {
                    $product = $item->product;

                    // ponytail: firstOrCreate (NOT updateOrCreate with stock=0, which would reset an existing row)
                    ProductWarehouse::firstOrCreate(
                        ['product_id' => $item->product_id, 'warehouse_id' => $transfer->source_warehouse_id],
                        ['stock' => 0]
                    )->increment('stock', $item->qty);

                    $product->increment('stock', $item->qty);
                }
            }

            $transfer->update(['status' => 'cancelled']);

            $this->auditLogService->log(
                event: 'stock_transfer.cancelled',
                module: 'stock',
                auditable: $transfer,
                description: 'Transfer stok '.$transfer->document_number.' dibatalkan.'.($returnStock ? ' Stok dikembalikan ke gudang asal.' : ''),
                before: ['status' => $before->status],
                after: ['status' => 'cancelled'],
                meta: ['stock_transfer_id' => $transfer->id],
            );
        });
    }
}
