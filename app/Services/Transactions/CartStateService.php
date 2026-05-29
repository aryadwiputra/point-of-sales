<?php

declare(strict_types=1);

namespace App\Services\Transactions;

use App\Models\Cart;
use App\Models\Customer;
use App\Models\CustomerVoucher;
use App\Services\LoyaltyService;
use App\Services\PricingService;

class CartStateService
{
    public function __construct(
        private readonly PricingService $pricingService,
        private readonly LoyaltyService $loyaltyService
    ) {}

    public function state(int $userId, array $context, string $message): array
    {
        $carts = Cart::with(['product', 'productUnit'])
            ->where('cashier_id', $userId)
            ->active()
            ->latest()
            ->get();

        $customer = isset($context['customer_id'])
            ? Customer::find($context['customer_id'])
            : null;
        $voucher = isset($context['customer_voucher_id'])
            ? CustomerVoucher::find($context['customer_voucher_id'])
            : null;

        $pricingPreview = $this->loyaltyService->previewCheckout(
            $this->pricingService->previewCart($carts, $customer),
            $customer,
            [
                'manual_discount' => (int) ($context['discount'] ?? 0),
                'shipping_cost' => (int) ($context['shipping_cost'] ?? 0),
                'redeem_points' => (int) ($context['redeem_points'] ?? 0),
                'voucher' => $voucher,
            ]
        );

        return [
            'success' => true,
            'message' => $message,
            'carts' => $carts,
            'carts_total' => (int) $carts->sum('price'),
            'pricingPreview' => $pricingPreview,
        ];
    }
}
