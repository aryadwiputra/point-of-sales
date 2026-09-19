<?php

namespace App\Support\Checkout;

class CheckoutFingerprint
{
    /**
     * Build a deterministic fingerprint of an offline sync payload.
     *
     * Used to detect reuse of the same client_uuid with a materially
     * different payload (idempotency conflict) versus an exact retry.
     */
    public static function fromSyncPayload(array $payload): string
    {
        $normalized = [
            'items' => self::normalizeItems($payload['items'] ?? []),
            'customer_id' => isset($payload['customer_id']) ? (int) $payload['customer_id'] : null,
            'customer_voucher_id' => isset($payload['customer_voucher_id']) ? (int) $payload['customer_voucher_id'] : null,
            'discount' => (int) ($payload['discount'] ?? 0),
            'shipping_cost' => (int) ($payload['shipping_cost'] ?? 0),
            'redeem_points' => (int) ($payload['redeem_points'] ?? 0),
            'pay_later' => (bool) ($payload['pay_later'] ?? false),
            'payment_method' => $payload['payment_method'] ?? null,
            'bank_account_id' => isset($payload['bank_account_id']) ? (int) $payload['bank_account_id'] : null,
            'due_date' => $payload['due_date'] ?? null,
            'order_type' => $payload['order_type'] ?? null,
            'note' => isset($payload['note']) ? trim($payload['note']) ?: null : null,
            'cash' => isset($payload['cash']) ? round((float) $payload['cash'], 2) : null,
        ];

        return hash('sha256', json_encode($normalized));
    }

    private static function normalizeItems(array $items): array
    {
        return collect($items)
            ->map(fn (array $item) => [
                'product_id' => (int) $item['product_id'],
                'qty' => round((float) $item['qty'], 3),
                'unit_id' => isset($item['unit_id']) ? (int) $item['unit_id'] : null,
            ])
            ->sortBy([['product_id', 'asc'], ['unit_id', 'asc']])
            ->values()
            ->all();
    }
}
