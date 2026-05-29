<?php

declare(strict_types=1);

namespace App\Services\Transactions;

use App\Models\Cart;
use App\Models\Customer;
use App\Models\CustomerVoucher;
use App\Services\LoyaltyService;
use App\Services\PricingService;

class TransactionPricingPreviewService
{
    public function __construct(
        private readonly PricingService $pricingService,
        private readonly LoyaltyService $loyaltyService
    ) {}

    public function execute(array $data, int $userId): array
    {
        $customer = isset($data['customer_id'])
            ? Customer::find($data['customer_id'])
            : null;
        $voucher = isset($data['customer_voucher_id'])
            ? CustomerVoucher::find($data['customer_voucher_id'])
            : null;

        $carts = Cart::with(['product.category', 'productUnit'])
            ->where('cashier_id', $userId)
            ->active()
            ->latest()
            ->get();

        $pricingPreview = $this->pricingService->previewCart($carts, $customer);

        return $this->loyaltyService->previewCheckout($pricingPreview, $customer, [
            'manual_discount' => (int) ($data['discount'] ?? 0),
            'shipping_cost' => (int) ($data['shipping_cost'] ?? 0),
            'redeem_points' => (int) ($data['redeem_points'] ?? 0),
            'voucher' => $voucher,
        ]);
    }
}
