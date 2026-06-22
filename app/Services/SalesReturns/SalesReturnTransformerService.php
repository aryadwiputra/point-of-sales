<?php

declare(strict_types=1);

namespace App\Services\SalesReturns;

use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Carbon\Carbon;

class SalesReturnTransformerService
{
    public function transactionForEditor(Transaction $transaction, ?SalesReturn $salesReturn = null): array
    {
        $draftItems = collect($salesReturn?->items ?? [])
            ->keyBy('transaction_detail_id');

        return [
            'id' => $transaction->id,
            'invoice' => $transaction->invoice,
            'created_at' => $transaction->getRawOriginal('created_at')
                ? Carbon::parse($transaction->getRawOriginal('created_at'))->toISOString()
                : null,
            'cashier' => $transaction->cashier ? [
                'id' => $transaction->cashier->id,
                'name' => $transaction->cashier->name,
            ] : null,
            'customer' => $transaction->customer ? [
                'id' => $transaction->customer->id,
                'name' => $transaction->customer->name,
            ] : null,
            'grand_total' => (int) $transaction->grand_total,
            'payment_method' => $transaction->payment_method,
            'payment_status' => $transaction->payment_status,
            'receivable' => $transaction->receivable ? [
                'id' => $transaction->receivable->id,
                'total' => (int) $transaction->receivable->total,
                'paid' => (int) $transaction->receivable->paid,
                'status' => $transaction->receivable->status,
                'remaining' => (int) $transaction->receivable->remaining,
            ] : null,
            'details' => $transaction->details->map(function (TransactionDetail $detail) use ($draftItems) {
                $completedReturnedQty = (int) $detail->salesReturnItems
                    ->filter(fn (SalesReturnItem $item) => $item->salesReturn?->status === 'completed')
                    ->sum('qty_return');

                $draftItem = $draftItems->get($detail->id);
                $qtySold = (int) $detail->qty;

                return [
                    'id' => $detail->id,
                    'product_id' => $detail->product_id,
                    'product' => $detail->product ? [
                        'id' => $detail->product->id,
                        'title' => $detail->product->title,
                        'barcode' => $detail->product->barcode,
                        'sku' => $detail->product->sku,
                    ] : null,
                    'qty' => $qtySold,
                    'price' => (int) $detail->price,
                    'returned_completed_qty' => $completedReturnedQty,
                    'remaining_returnable_qty' => max(0, $qtySold - $completedReturnedQty),
                    'draft_item' => $draftItem ? [
                        'qty_return' => (int) $draftItem->qty_return,
                        'return_reason' => $draftItem->return_reason,
                        'restock_to_inventory' => (bool) $draftItem->restock_to_inventory,
                        'subtotal' => (int) $draftItem->subtotal,
                    ] : null,
                ];
            })->values(),
        ];
    }

    public function salesReturn(SalesReturn $salesReturn): array
    {
        return [
            'id' => $salesReturn->id,
            'code' => $salesReturn->code,
            'status' => $salesReturn->status,
            'return_type' => $salesReturn->return_type,
            'refund_amount' => (int) $salesReturn->refund_amount,
            'credited_amount' => (int) $salesReturn->credited_amount,
            'total_return_amount' => (int) $salesReturn->total_return_amount,
            'notes' => $salesReturn->notes,
            'created_at' => optional($salesReturn->created_at)?->toISOString(),
            'completed_at' => optional($salesReturn->completed_at)?->toISOString(),
            'cashier' => $salesReturn->cashier ? [
                'id' => $salesReturn->cashier->id,
                'name' => $salesReturn->cashier->name,
            ] : null,
            'customer' => $salesReturn->customer ? [
                'id' => $salesReturn->customer->id,
                'name' => $salesReturn->customer->name,
            ] : null,
            'transaction' => [
                'id' => $salesReturn->transaction?->id,
                'invoice' => $salesReturn->transaction?->invoice,
            ],
            'items' => $salesReturn->items->map(function (SalesReturnItem $item) {
                return [
                    'id' => $item->id,
                    'transaction_detail_id' => $item->transaction_detail_id,
                    'product' => $item->product ? [
                        'id' => $item->product->id,
                        'title' => $item->product->title,
                        'barcode' => $item->product->barcode,
                        'sku' => $item->product->sku,
                    ] : null,
                    'qty_sold' => (int) $item->qty_sold,
                    'qty_returned_before' => (int) $item->qty_returned_before,
                    'qty_return' => (int) $item->qty_return,
                    'unit_price' => (int) $item->unit_price,
                    'subtotal' => (int) $item->subtotal,
                    'return_reason' => $item->return_reason,
                    'restock_to_inventory' => (bool) $item->restock_to_inventory,
                ];
            })->values(),
        ];
    }

    public function hasReturnableItems(Transaction $transaction): bool
    {
        return $transaction->details->contains(function (TransactionDetail $detail) {
            $completedReturnedQty = (int) $detail->salesReturnItems
                ->filter(fn (SalesReturnItem $item) => $item->salesReturn?->status === 'completed')
                ->sum('qty_return');

            return $completedReturnedQty < (int) $detail->qty;
        });
    }

    public function auditPayload(SalesReturn $salesReturn): array
    {
        return [
            'code' => $salesReturn->code,
            'status' => $salesReturn->status,
            'return_type' => $salesReturn->return_type,
            'refund_amount' => (int) $salesReturn->refund_amount,
            'credited_amount' => (int) $salesReturn->credited_amount,
            'total_return_amount' => (int) $salesReturn->total_return_amount,
            'transaction_id' => (int) $salesReturn->transaction_id,
            'items_summary' => $salesReturn->items->map(fn (SalesReturnItem $item) => [
                'product_id' => $item->product_id,
                'product_title' => $item->product?->title,
                'qty_return' => (int) $item->qty_return,
                'subtotal_return' => (int) $item->subtotal_return,
                'restock_to_inventory' => (bool) $item->restock_to_inventory,
            ])->values()->all(),
        ];
    }
}
