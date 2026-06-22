<?php

declare(strict_types=1);

namespace App\Services\Transactions;

use App\Models\Transaction;

class TransactionPrintQueryService
{
    public function execute(string $invoice): array
    {
        $transaction = Transaction::with(
            'details.product',
            'details.productUnit',
            'details.pricingRule',
            'cashier',
            'customer',
            'receivable',
            'bankAccount'
        )
            ->where('invoice', $invoice)
            ->firstOrFail();

        $payload = $transaction->toArray();
        $payload['details'] = collect($payload['details'] ?? [])
            ->map(function (array $detail) {
                $detail['qty'] = (int) $detail['qty'];

                return $detail;
            })
            ->values()
            ->all();

        return $payload;
    }
}
