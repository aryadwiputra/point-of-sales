<?php

declare(strict_types=1);

namespace App\Services\SalesReturns;

use App\Models\SalesReturnItem;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SalesReturnPayloadService
{
    public function prepareDraftPayload(Transaction $transaction, array $validated, ?int $excludeSalesReturnId = null): array
    {
        $details = $transaction->details->keyBy('id');
        $returnedQtyMap = $this->completedReturnedQtyMap($transaction->id, $excludeSalesReturnId);

        $returnType = $validated['return_type'];

        if (! $transaction->customer_id) {
            $returnType = 'refund_cash';
        }

        $items = collect($validated['items'])
            ->map(function (array $item) use ($details, $returnedQtyMap) {
                $detail = $details->get((int) $item['transaction_detail_id']);

                if (! $detail) {
                    throw ValidationException::withMessages([
                        'items' => 'Ada item retur yang tidak cocok dengan transaksi asal.',
                    ]);
                }

                $qtyReturn = (int) ($item['qty_return'] ?? 0);

                if ($qtyReturn < 1) {
                    return null;
                }

                $qtyReturnedBefore = (int) ($returnedQtyMap[$detail->id] ?? 0);
                $remainingQty = (int) $detail->qty - $qtyReturnedBefore;

                if ($qtyReturn > $remainingQty) {
                    throw ValidationException::withMessages([
                        'items' => 'Qty retur melebihi sisa qty yang bisa diretur.',
                    ]);
                }

                if (blank($item['return_reason'] ?? null)) {
                    throw ValidationException::withMessages([
                        'items' => 'Alasan retur wajib diisi untuk setiap item yang diretur.',
                    ]);
                }

                return [
                    'transaction_detail_id' => $detail->id,
                    'product_id' => $detail->product_id,
                    'qty_sold' => (int) $detail->qty,
                    'qty_returned_before' => $qtyReturnedBefore,
                    'qty_return' => $qtyReturn,
                    'unit_price' => (int) $detail->price,
                    'subtotal' => $qtyReturn * (int) $detail->price,
                    'return_reason' => trim($item['return_reason']),
                    'restock_to_inventory' => (bool) ($item['restock_to_inventory'] ?? true),
                ];
            })
            ->filter()
            ->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Pilih minimal satu item retur dengan qty lebih dari 0.',
            ]);
        }

        $totalReturnAmount = (int) $items->sum('subtotal');
        $settlement = $this->calculateSettlement($transaction, $totalReturnAmount, $returnType);

        return [
            'return_type' => $settlement['return_type'],
            'notes' => $validated['notes'] ?? null,
            'refund_amount' => $settlement['refund_amount'],
            'credited_amount' => $settlement['credited_amount'],
            'total_return_amount' => $totalReturnAmount,
            'items' => $items->all(),
        ];
    }

    public function calculateSettlement(Transaction $transaction, int $totalReturnAmount, string $returnType): array
    {
        $resolvedReturnType = ! $transaction->customer_id && $returnType === 'store_credit'
            ? 'refund_cash'
            : $returnType;

        $refundAmount = 0;
        $creditedAmount = 0;
        $receivableTotalAfter = null;

        if ($transaction->payment_method === 'pay_later' && $transaction->receivable) {
            $currentTotal = (int) $transaction->receivable->total;
            $paid = (int) $transaction->receivable->paid;
            $receivableTotalAfter = max(0, $currentTotal - $totalReturnAmount);
            $settlementAmount = max(0, $paid - $receivableTotalAfter);

            if ($resolvedReturnType === 'store_credit') {
                $creditedAmount = $settlementAmount;
            } else {
                $refundAmount = $settlementAmount;
            }
        } elseif ($transaction->payment_status === 'paid') {
            if ($resolvedReturnType === 'store_credit') {
                $creditedAmount = $totalReturnAmount;
            } else {
                $refundAmount = $totalReturnAmount;
            }
        }

        return [
            'return_type' => $resolvedReturnType,
            'refund_amount' => $refundAmount,
            'credited_amount' => $creditedAmount,
            'receivable_total_after' => $receivableTotalAfter,
        ];
    }

    public function determineReceivableStatus(int $total, int $paid, $dueDate): string
    {
        if ($paid >= $total) {
            return 'paid';
        }

        if ($paid > 0) {
            return 'partial';
        }

        if ($dueDate && now()->startOfDay()->gt($dueDate->copy()->startOfDay())) {
            return 'overdue';
        }

        return 'unpaid';
    }

    public function completedReturnedQtyMap(int $transactionId, ?int $excludeSalesReturnId = null): Collection
    {
        return SalesReturnItem::query()
            ->selectRaw('transaction_detail_id, COALESCE(SUM(qty_return), 0) as total_qty')
            ->whereHas('salesReturn', function (Builder $query) use ($transactionId, $excludeSalesReturnId) {
                $query->where('transaction_id', $transactionId)
                    ->where('status', 'completed');

                if ($excludeSalesReturnId) {
                    $query->where('id', '!=', $excludeSalesReturnId);
                }
            })
            ->groupBy('transaction_detail_id')
            ->pluck('total_qty', 'transaction_detail_id');
    }
}
