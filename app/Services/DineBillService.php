<?php

namespace App\Services;

use App\Models\DineBill;
use App\Models\DineOrder;
use App\Models\DineOrderItem;
use App\Models\DineSession;
use App\Models\ProductBatch;
use App\Models\ProductWarehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DineBillService
{
    public function __construct(
        private readonly StockMutationService $stockMutationService,
        private readonly AuditLogService $auditLogService,
        private readonly DineSessionService $sessionService,
        private readonly KitchenTicketService $kitchenTicketService,
    ) {}

    public function openBillForSession(DineSession $session, ?int $createdBy = null): DineBill
    {
        $bill = DineBill::query()
            ->where('dine_session_id', $session->id)
            ->where('status', DineBill::STATUS_OPEN)
            ->orderBy('sequence')
            ->lockForUpdate()
            ->first();

        return $bill ?? $this->createNextBill($session, $createdBy);
    }

    public function createNextBill(DineSession $session, ?int $createdBy = null): DineBill
    {
        return DB::transaction(function () use ($session, $createdBy) {
            $locked = DineSession::query()->lockForUpdate()->findOrFail($session->id);
            $next = ((int) DineBill::query()
                ->where('dine_session_id', $locked->id)
                ->max('sequence')) + 1;

            return DineBill::create([
                'dine_session_id' => $locked->id,
                'sequence' => $next,
                'status' => DineBill::STATUS_OPEN,
                'created_by' => $createdBy,
            ]);
        });
    }

    public function recomputeTotals(DineBill $bill): DineBill
    {
        $subtotal = (int) $bill->items()
            ->whereNull('voided_at')
            ->get()
            ->sum(fn (DineOrderItem $item) => $item->price * $item->qty);

        $bill->update([
            'subtotal' => $subtotal,
            'grand_total' => max(0, $subtotal - (int) $bill->discount_total) + (int) $bill->tax_total,
        ]);

        return $bill->fresh();
    }

    public function split(DineBill $bill, array $itemIds, ?int $userId = null): DineBill
    {
        return DB::transaction(function () use ($bill, $itemIds, $userId) {
            $locked = DineBill::query()->lockForUpdate()->findOrFail($bill->id);
            $this->assertBillModifiable($locked);

            $ids = collect($itemIds)->map(fn ($id) => (int) $id)->unique()->values();

            if ($ids->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Pilih minimal satu item untuk dipindahkan.',
                ]);
            }

            $items = DineOrderItem::query()
                ->where('dine_bill_id', $locked->id)
                ->whereIn('id', $ids)
                ->whereNull('voided_at')
                ->lockForUpdate()
                ->get();

            if ($items->count() !== $ids->count()) {
                throw ValidationException::withMessages([
                    'items' => 'Item yang dipilih tidak valid atau sudah void.',
                ]);
            }

            $newBill = $this->createNextBill($locked->session, $userId);

            DineOrderItem::whereIn('id', $items->pluck('id'))->update([
                'dine_bill_id' => $newBill->id,
            ]);

            $this->recomputeTotals($newBill);
            $this->recomputeTotals($locked);

            if ($locked->items()->whereNull('voided_at')->count() === 0) {
                $locked->update(['status' => DineBill::STATUS_VOID]);
            }

            return $newBill->fresh();
        });
    }

    public function voidItem(
        DineBill $bill,
        DineOrderItem $item,
        string $reason,
        int $userId,
        int $warehouseId
    ): void {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan void wajib diisi.',
            ]);
        }

        DB::transaction(function () use ($bill, $item, $reason, $userId, $warehouseId) {
            $lockedBill = DineBill::query()->lockForUpdate()->findOrFail($bill->id);
            $this->assertBillModifiable($lockedBill);

            $lockedItem = DineOrderItem::query()
                ->with('product.components')
                ->lockForUpdate()
                ->findOrFail($item->id);

            if ((int) $lockedItem->dine_bill_id !== (int) $lockedBill->id) {
                throw ValidationException::withMessages([
                    'item' => 'Item bukan milik bill ini.',
                ]);
            }

            if ($lockedItem->isVoided()) {
                throw ValidationException::withMessages([
                    'item' => 'Item sudah void.',
                ]);
            }

            $this->restoreStock($lockedItem, $lockedBill, $warehouseId, $userId);

            $lockedItem->update([
                'voided_at' => now(),
                'voided_by' => $userId,
                'void_reason' => $reason,
            ]);

            $this->auditLogService->log(
                event: 'dine_bill.item_voided',
                module: 'dine-in',
                auditable: $lockedBill,
                description: "Void item {$lockedItem->product?->title} pada bill #{$lockedBill->sequence}",
                before: ['voided_at' => null],
                after: [
                    'item_id' => $lockedItem->id,
                    'voided_at' => now()->toISOString(),
                    'voided_by' => $userId,
                    'void_reason' => $reason,
                ],
            );

            $this->kitchenTicketService->cancelForDineOrderItem($lockedItem, $reason, $userId);

            $this->recomputeTotals($lockedBill);
            $this->voidIfEmpty($lockedBill);
            $this->completeOrdersForBill($lockedBill->fresh());
            $this->sessionService->closeIfIdle($lockedBill->session);
        });
    }

    public function voidIfEmpty(DineBill $bill): bool
    {
        if ($bill->items()->whereNull('voided_at')->count() > 0) {
            return false;
        }

        $bill->update(['status' => DineBill::STATUS_VOID]);

        return true;
    }

    public function completeOrdersForBill(DineBill $bill): void
    {
        $orderIds = $bill->items()->pluck('dine_order_id')->unique()->filter()->values();

        DineOrder::query()->whereIn('id', $orderIds)->get()->each(function (DineOrder $order) {
            $items = $order->items()->with('bill')->get();

            $hasOpenItem = $items->contains(function (DineOrderItem $item) {
                if ($item->isVoided() || ! $item->bill) {
                    return false;
                }

                return in_array($item->bill->status, [
                    DineBill::STATUS_OPEN,
                    DineBill::STATUS_PENDING_APPROVAL,
                ], true);
            });

            if ($hasOpenItem) {
                return;
            }

            $allVoided = $items->every(fn (DineOrderItem $item) => $item->isVoided());
            $order->update([
                'status' => $allVoided
                    ? DineOrder::STATUS_CANCELLED
                    : DineOrder::STATUS_COMPLETED,
            ]);
        });
    }

    public function assertBillModifiable(DineBill $bill): void
    {
        if ($bill->status !== DineBill::STATUS_OPEN) {
            throw ValidationException::withMessages([
                'bill' => 'Bill tidak dapat diubah pada status '.$bill->status.'.',
            ]);
        }
    }

    private function restoreStock(
        DineOrderItem $item,
        DineBill $bill,
        int $warehouseId,
        int $userId
    ): void {
        $product = $item->product;

        if (! $product) {
            return;
        }

        if ($product->is_composite) {
            foreach ($product->components as $component) {
                $qty = (int) round((float) $component->pivot->qty * (int) $item->qty);
                $pivot = ProductWarehouse::query()
                    ->where('product_id', $component->id)
                    ->where('warehouse_id', $warehouseId)
                    ->lockForUpdate()
                    ->first();
                $before = $pivot ? (int) $pivot->stock : (int) $component->stock;

                $pivot?->increment('stock', $qty);
                $component->increment('stock', $qty);

                $this->stockMutationService->recordMutation(
                    product: $component,
                    warehouseId: $warehouseId,
                    referenceType: 'dine_bill',
                    referenceId: $bill->id,
                    mutationType: 'in',
                    qty: $qty,
                    stockBefore: $before,
                    stockAfter: $before + $qty,
                    notes: "Void item bill #{$bill->sequence} (komponen komposit {$product->title})",
                    userId: $userId,
                );
            }

            return;
        }

        if (! $product->tracksStock()) {
            return;
        }

        $qty = $item->baseQuantity();
        $pivot = ProductWarehouse::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();
        $before = $pivot ? (int) $pivot->stock : (int) $product->stock;

        $pivot?->increment('stock', $qty);
        $product->increment('stock', $qty);

        $this->stockMutationService->recordMutation(
            product: $product,
            warehouseId: $warehouseId,
            referenceType: 'dine_bill',
            referenceId: $bill->id,
            mutationType: 'in',
            qty: $qty,
            stockBefore: $before,
            stockAfter: $before + $qty,
            notes: "Void item bill #{$bill->sequence}",
            userId: $userId,
        );

        foreach ($item->batchAllocations()->lockForUpdate()->get() as $allocation) {
            ProductBatch::query()
                ->whereKey($allocation->product_batch_id)
                ->lockForUpdate()
                ->first()
                ?->increment('stock', (int) $allocation->qty);
        }
    }
}
