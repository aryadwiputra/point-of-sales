<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CashierShift;
use App\Models\DineBill;
use App\Models\DineOrder;
use App\Models\DineSession;
use App\Models\ProductBatch;
use App\Models\ProductWarehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DineStaffOrderService
{
    public function __construct(
        private readonly PricingService $pricingService,
        private readonly DineSessionService $sessionService,
        private readonly DineBillService $billService,
        private readonly StockMutationService $stockMutationService,
    ) {}

    public function submit(int $userId, DineBill $bill): DineOrder
    {
        $shift = CashierShift::with('warehouse.outlet')
            ->where('user_id', $userId)
            ->open()
            ->first();

        if (! $shift) {
            throw ValidationException::withMessages([
                'shift' => 'Shift kasir belum dibuka.',
            ]);
        }

        return DB::transaction(function () use ($userId, $bill, $shift) {
            $bill = DineBill::query()->lockForUpdate()->findOrFail($bill->id);

            if ($bill->status !== DineBill::STATUS_OPEN) {
                throw ValidationException::withMessages([
                    'bill' => 'Bill tidak dapat menerima pesanan pada status '.$bill->status.'.',
                ]);
            }

            $session = DineSession::query()
                ->with('table.area')
                ->lockForUpdate()
                ->findOrFail($bill->dine_session_id);

            $this->sessionService->assertOutletAccess($session->table, $shift->warehouse?->outlet);

            $carts = Cart::with(['product.components', 'modifiers'])
                ->where('cashier_id', $userId)
                ->active()
                ->lockForUpdate()
                ->get();

            if ($carts->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => 'Keranjang kosong.',
                ]);
            }

            $preview = $this->pricingService->previewCart(
                $carts,
                null,
                null,
                $shift->warehouse?->outlet,
            );
            $pricingByCart = collect($preview['items'])->keyBy('cart_id');

            $order = DineOrder::create([
                'dine_table_id' => $session->dine_table_id,
                'dine_session_id' => $session->id,
                'cashier_id' => $userId,
                'access_token' => (string) Str::uuid(),
                'status' => DineOrder::STATUS_ACCEPTED,
                'source' => DineOrder::SOURCE_STAFF,
                'payment_option' => DineOrder::PAY_AT_COUNTER,
                'submitted_at' => now(),
                'subtotal' => 0,
                'item_count' => 0,
            ]);

            $subtotal = 0;
            $itemCount = 0;

            foreach ($carts as $cart) {
                $product = $cart->product;
                $line = $pricingByCart->get($cart->id);
                $unitPrice = (int) ($line['effective_unit_price'] ?? round($cart->price / max(1, (int) $cart->qty)));
                $unitCost = $product->is_composite
                    ? (int) round($product->components->sum(
                        fn ($component) => $component->buy_price * (float) $component->pivot->qty
                    ))
                    : (int) round($product->buy_price * (float) ($cart->conversion_factor ?? 1));

                $item = $order->items()->create([
                    'dine_bill_id' => $bill->id,
                    'product_id' => $product->id,
                    'unit_id' => $cart->unit_id,
                    'conversion_factor' => $cart->conversion_factor ?? 1,
                    'qty' => (int) $cart->qty,
                    'price' => $unitPrice,
                    'unit_cost' => $unitCost,
                    'tax_type' => $product->tax_type ?? 'exclusive',
                    'tax_rate' => $product->tax_rate ?? 0,
                    'note' => $cart->note,
                ]);

                $item->modifiers()->createMany(
                    $cart->modifiers->map(fn ($modifier) => [
                        'modifier_id' => $modifier->modifier_id,
                        'modifier_group_id' => $modifier->modifier_group_id,
                        'group_name' => $modifier->group_name,
                        'name' => $modifier->name,
                        'price_delta' => $modifier->price_delta,
                    ])->all()
                );

                $this->consumeStock($order, $item, $product, $shift->warehouse_id, $userId);

                $subtotal += $unitPrice * (int) $cart->qty;
                $itemCount += (int) $cart->qty;
            }

            $order->update([
                'subtotal' => $subtotal,
                'item_count' => $itemCount,
            ]);

            Cart::query()
                ->where('cashier_id', $userId)
                ->active()
                ->whereIn('id', $carts->pluck('id'))
                ->delete();

            $this->billService->recomputeTotals($bill);

            return $order->fresh(['items']);
        });
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
                    notes: "Pesanan staff dine-in #{$order->id} (komponen komposit {$product->title})",
                    userId: $userId,
                );
            }

            return;
        }

        if (! $product->tracksStock()) {
            return;
        }

        $baseQty = (int) round((int) $item->qty * (float) ($item->conversion_factor ?? 1));
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
            notes: "Pesanan staff dine-in #{$order->id}",
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
