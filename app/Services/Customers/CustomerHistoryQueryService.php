<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Models\Customer;
use App\Models\CustomerVoucher;
use App\Models\Transaction;
use App\Services\LoyaltyService;
use Carbon\Carbon;

class CustomerHistoryQueryService
{
    public function __construct(
        private readonly LoyaltyService $loyaltyService
    ) {}

    public function showActivityPayload(Customer $customer): array
    {
        return [
            'stats' => $this->stats($customer),
            'recentTransactions' => $this->recentTransactions($customer),
            'frequentProducts' => $this->frequentProducts($customer),
            'rewardHistory' => $this->rewardHistory($customer, 15, true),
            'vouchers' => $this->vouchers($customer),
        ];
    }

    public function historyPayload(Customer $customer): array
    {
        $stats = $this->stats($customer);

        return [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->no_telp,
            ],
            'stats' => [
                'total_transactions' => (int) ($stats->total_transactions ?? 0),
                'total_spent' => (int) ($stats->total_spent ?? 0),
                'last_visit' => $stats->last_visit ? Carbon::parse($stats->last_visit)->format('d M Y') : null,
            ],
            'loyalty' => [
                'is_member' => (bool) $customer->is_loyalty_member,
                'member_code' => $customer->member_code,
                'tier' => $customer->loyalty_tier,
                'points' => (int) $customer->loyalty_points,
                'member_since' => optional($customer->loyalty_member_since)?->format('d M Y'),
            ],
            'recent_transactions' => $this->recentTransactions($customer),
            'frequent_products' => $this->frequentProducts($customer),
            'loyalty_history' => $this->rewardHistory($customer, 5, false),
            'eligible_vouchers' => $this->eligibleVouchers($customer),
        ];
    }

    private function stats(Customer $customer)
    {
        return Transaction::query()
            ->where('customer_id', $customer->id)
            ->selectRaw('
                COUNT(*) as total_transactions,
                SUM(grand_total) as total_spent,
                MAX(created_at) as last_visit
            ')
            ->first();
    }

    private function recentTransactions(Customer $customer)
    {
        return Transaction::query()
            ->where('customer_id', $customer->id)
            ->select('id', 'invoice', 'grand_total', 'payment_method', 'created_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'invoice' => $transaction->invoice,
                'total' => $transaction->grand_total,
                'payment_method' => $transaction->payment_method,
                'date' => Carbon::parse($transaction->created_at)->format('d M Y H:i'),
            ]);
    }

    private function frequentProducts(Customer $customer)
    {
        return Transaction::query()
            ->where('customer_id', $customer->id)
            ->join('transaction_details', 'transactions.id', '=', 'transaction_details.transaction_id')
            ->join('products', 'transaction_details.product_id', '=', 'products.id')
            ->selectRaw('products.id, products.title, SUM(transaction_details.qty) as total_qty')
            ->groupBy('products.id', 'products.title')
            ->orderByDesc('total_qty')
            ->limit(3)
            ->get();
    }

    private function rewardHistory(Customer $customer, int $limit, bool $includeBalanceAfter)
    {
        return $customer->loyaltyPointHistories()
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function ($history) use ($includeBalanceAfter) {
                $payload = [
                    'id' => $history->id,
                    'type' => $history->type,
                    'points_delta' => (int) $history->points_delta,
                    'amount_delta' => (int) $history->amount_delta,
                    'reference' => $history->reference,
                    'notes' => $history->notes,
                    'created_at' => optional($history->created_at)?->format('d M Y H:i'),
                ];

                if ($includeBalanceAfter) {
                    $payload['balance_after'] = (int) $history->balance_after;
                }

                return $payload;
            });
    }

    private function vouchers(Customer $customer)
    {
        return $customer->vouchers()
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (CustomerVoucher $voucher) => $this->loyaltyService->serializeVoucher($voucher) + [
                'is_active' => (bool) $voucher->is_active,
                'is_used' => (bool) $voucher->is_used,
            ]);
    }

    private function eligibleVouchers(Customer $customer)
    {
        return $customer->vouchers()
            ->where('is_active', true)
            ->where('is_used', false)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (CustomerVoucher $voucher) => $this->loyaltyService->serializeVoucher($voucher));
    }
}
