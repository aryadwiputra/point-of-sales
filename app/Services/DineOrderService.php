<?php

namespace App\Services;

use App\Models\CashierShift;
use App\Models\DineOrder;
use App\Models\ProductBatch;
use App\Models\ProductWarehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DineOrderService
{
    public function __construct(
        private readonly StockMutationService $stockMutationService,
        private readonly DineSessionService $sessionService,
        private readonly DineBillService $billService,
        private readonly KitchenTicketService $kitchenTicketService,
    ) {}

    public function accept(DineOrder $order): void
    {
        $cashierId = $order->cashier_id ?? auth()->id();
        $shift = CashierShift::with('warehouse.outlet')
            ->where('user_id', $cashierId)
            ->open()
            ->first();

        if (! $shift) {
            throw ValidationException::withMessages([
                'shift' => 'Tidak ada shift kasir aktif. Buka shift terlebih dahulu sebelum menerima pesanan.',
            ]);
        }

        $warehouseId = $shift->warehouse_id;

        DB::transaction(function () use ($order, $shift, $warehouseId, $cashierId) {
            $order = DineOrder::with(['items.product.components', 'items.modifiers', 'table.area.outlet'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $tableOutletId = $order->table?->area?->outlet_id;
            $shiftOutletId = $shift->warehouse?->outlet_id;

            if ($tableOutletId && $shiftOutletId && (int) $tableOutletId !== (int) $shiftOutletId) {
                throw ValidationException::withMessages([
                    'shift' => 'Shift kasir harus berada di outlet yang sama dengan meja.',
                ]);
            }

            $session = $this->sessionService->ensureOpenForTable($order->table, $cashierId);
            $bill = $this->billService->openBillForSession($session, $cashierId);

            foreach ($order->items as $item) {
                $product = $item->product;
                $unitCost = $product->is_composite
                    ? (int) round($product->components->sum(
                        fn ($component) => $component->buy_price * (float) $component->pivot->qty
                    ))
                    : (int) round($product->buy_price * (float) ($item->conversion_factor ?? 1));

                $item->update([
                    'dine_bill_id' => $bill->id,
                    'unit_cost' => $unitCost,
                    'tax_type' => $product->tax_type ?? 'exclusive',
                    'tax_rate' => $product->tax_rate ?? 0,
                ]);

                $this->consumeStock($order, $item, $product, $warehouseId, $cashierId);
            }

            $order->update([
                'status' => DineOrder::STATUS_ACCEPTED,
                'cashier_id' => $cashierId,
                'dine_session_id' => $session->id,
                'source' => DineOrder::SOURCE_QR,
            ]);

            $this->billService->recomputeTotals($bill);
            $this->kitchenTicketService->createForDineOrder($order, $shift->warehouse?->outlet_id);
        });
    }

    public function reject(DineOrder $order, ?string $reason = null): void
    {
        $order->update([
            'status' => DineOrder::STATUS_REJECTED,
            'notes' => $order->notes
                ? "{$order->notes}\n[Penolakan: {$reason}]"
                : "[Penolakan: {$reason}]",
        ]);
    }

    private function consumeStock(
        DineOrder $order,
        $item,
        $product,
        int $warehouseId,
        int $userId
    ): void {
        if ($product->is_composite) {
            foreach ($product->components as $component) {
                $componentQty = (int) round((float) $component->pivot->qty * (int) $item->qty);
                $pivot = ProductWarehouse::query()
                    ->where('product_id', $component->id)
                    ->where('warehouse_id', $warehouseId)
                    ->lockForUpdate()
                    ->first();
                $available = $pivot ? (int) $pivot->stock : (int) $component->stock;

                if ($available < $componentQty) {
                    throw ValidationException::withMessages([
                        'stock' => "Stok komponen {$component->title} tidak mencukupi (tersedia: {$available}).",
                    ]);
                }

                $pivot?->decrement('stock', $componentQty);
                $component->decrement('stock', $componentQty);

                $this->stockMutationService->recordMutation(
                    product: $component,
                    warehouseId: $warehouseId,
                    referenceType: 'dine_order',
                    referenceId: $order->id,
                    mutationType: 'out',
                    qty: $componentQty,
                    stockBefore: $available,
                    stockAfter: $available - $componentQty,
                    notes: "Pesanan dine-in #{$order->id} (komponen komposit {$product->title})",
                    userId: $userId,
                );
            }

            return;
        }

        if (! $product->tracksStock()) {
            return;
        }

        $baseQty = $item->baseQuantity();
        $pivot = ProductWarehouse::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();
        $available = $pivot ? (int) $pivot->stock : (int) $product->stock;

        if ($available < $baseQty) {
            throw ValidationException::withMessages([
                'stock' => "Stok {$product->title} tidak mencukupi (tersedia: {$available}).",
            ]);
        }

        $pivot?->decrement('stock', $baseQty);
        $product->decrement('stock', $baseQty);

        $this->stockMutationService->recordMutation(
            product: $product,
            warehouseId: $warehouseId,
            referenceType: 'dine_order',
            referenceId: $order->id,
            mutationType: 'out',
            qty: $baseQty,
            stockBefore: $available,
            stockAfter: $available - $baseQty,
            notes: "Pesanan dine-in #{$order->id}",
            userId: $userId,
        );

        $batches = ProductBatch::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouseId)
            ->where('stock', '>', 0)
            ->where(function (Builder $query) {
                $query->whereNull('expired_at')->orWhere('expired_at', '>', now());
            })
            ->orderBy('expired_at')
            ->orderBy('received_at')
            ->lockForUpdate()
            ->get();

        if ($batches->isEmpty()) {
            return;
        }

        $covered = (int) $batches->sum('stock');

        if ($covered < $baseQty) {
            throw ValidationException::withMessages([
                'stock' => "Stok batch {$product->title} tidak mencukupi. Tersedia: {$covered}.",
            ]);
        }

        $remaining = $baseQty;

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $take = min((int) $batch->stock, $remaining);
            $batch->decrement('stock', $take);
            $remaining -= $take;

            $item->batchAllocations()->create([
                'product_batch_id' => $batch->id,
                'qty' => $take,
            ]);
        }
    }
}
