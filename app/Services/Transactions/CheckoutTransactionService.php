<?php

declare(strict_types=1);

namespace App\Services\Transactions;

use App\Exceptions\PaymentGatewayException;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\CustomerVoucher;
use App\Models\PaymentSetting;
use App\Models\Product;
use App\Models\Receivable;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CashierShiftService;
use App\Services\LoyaltyService;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\PricingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutTransactionService
{
    public function __construct(
        private readonly CashierShiftService $cashierShiftService,
        private readonly PricingService $pricingService,
        private readonly LoyaltyService $loyaltyService,
        private readonly PaymentGatewayManager $paymentGatewayManager
    ) {}

    public function execute(array $data, User $user): array
    {
        $isPayLater = (bool) ($data['pay_later'] ?? false);
        $paymentGateway = $isPayLater ? null : ($data['payment_gateway'] ?? null);

        if ($paymentGateway) {
            $paymentGateway = strtolower($paymentGateway);
        }

        if ($isPayLater && empty($data['customer_id'])) {
            return [
                'success' => false,
                'error' => 'Pelanggan wajib dipilih untuk nota barang/piutang.',
            ];
        }

        if ($isPayLater && empty($data['due_date'])) {
            return [
                'success' => false,
                'error' => 'Tanggal jatuh tempo wajib diisi untuk nota barang.',
            ];
        }

        $paymentSetting = null;

        if ($paymentGateway) {
            $paymentSetting = PaymentSetting::first();

            if (! $paymentSetting || ! $paymentSetting->isGatewayReady($paymentGateway)) {
                return [
                    'success' => false,
                    'error' => 'Gateway pembayaran belum dikonfigurasi.',
                ];
            }
        }

        $transaction = $this->createTransaction($data, $user, $paymentGateway, $isPayLater);

        if ($paymentGateway) {
            try {
                $paymentResponse = $this->paymentGatewayManager->createPayment(
                    $transaction,
                    $paymentGateway,
                    $paymentSetting
                );

                $transaction->update([
                    'payment_reference' => $paymentResponse['reference'] ?? null,
                    'payment_url' => $paymentResponse['payment_url'] ?? null,
                ]);
            } catch (PaymentGatewayException $exception) {
                return [
                    'success' => true,
                    'transaction' => $transaction,
                    'warning' => $exception->getMessage(),
                ];
            }
        }

        return [
            'success' => true,
            'transaction' => $transaction,
        ];
    }

    private function createTransaction(array $data, User $user, ?string $paymentGateway, bool $isPayLater): Transaction
    {
        $invoice = 'TRX-'.Str::upper(Str::random(10));
        $isCashPayment = empty($paymentGateway) && ! $isPayLater;
        $manualDiscount = max(0, (int) ($data['discount'] ?? 0));
        $shippingCost = max(0, (int) ($data['shipping_cost'] ?? 0));
        $requestedRedeemPoints = max(0, (int) ($data['redeem_points'] ?? 0));
        $cashAmount = $isCashPayment ? max(0, (int) ($data['cash'] ?? 0)) : 0;
        $customerId = $data['customer_id'] ?? null;
        $customer = $customerId
            ? Customer::find($customerId)
            : null;
        $voucher = ! empty($data['customer_voucher_id'])
            ? CustomerVoucher::find((int) $data['customer_voucher_id'])
            : null;

        return DB::transaction(function () use (
            $data,
            $user,
            $invoice,
            $cashAmount,
            $paymentGateway,
            $isCashPayment,
            $isPayLater,
            $manualDiscount,
            $shippingCost,
            $requestedRedeemPoints,
            $customerId,
            $customer,
            $voucher
        ) {
            $activeShift = $this->cashierShiftService->requireActiveShiftForUser(
                $user->id,
                lockForUpdate: true
            );

            $carts = Cart::with(['product', 'productUnit'])
                ->where('cashier_id', $user->id)
                ->active()
                ->get();

            if ($carts->isEmpty()) {
                abort(422, 'Keranjang kosong.');
            }

            $pricingPreview = $this->pricingService->previewCart($carts, $customer);
            $checkoutPreview = $this->loyaltyService->previewCheckout($pricingPreview, $customer, [
                'manual_discount' => $manualDiscount,
                'shipping_cost' => $shippingCost,
                'redeem_points' => $requestedRedeemPoints,
                'voucher' => $voucher,
            ]);
            $pricingItems = collect($pricingPreview['items']);
            $subtotalAfterPromo = (int) data_get($pricingPreview, 'summary.subtotal_after_promo', 0);
            $voucherDiscount = (int) data_get($checkoutPreview, 'summary.voucher_discount_total', 0);
            $loyaltyDiscount = (int) data_get($checkoutPreview, 'summary.loyalty_discount_total', 0);
            $appliedManualDiscount = (int) data_get($checkoutPreview, 'summary.manual_discount_total', 0);
            $grandTotal = (int) data_get($checkoutPreview, 'summary.grand_total', 0);
            $changeAmount = $isCashPayment ? max(0, $cashAmount - $grandTotal) : 0;

            $transaction = Transaction::create([
                'cashier_id' => $user->id,
                'cashier_shift_id' => $activeShift->id,
                'customer_id' => $customerId,
                'invoice' => $invoice,
                'cash' => $cashAmount,
                'change' => $changeAmount,
                'discount' => $appliedManualDiscount,
                'loyalty_points_redeemed' => (int) data_get($checkoutPreview, 'summary.applied_redeem_points', 0),
                'loyalty_discount_total' => $loyaltyDiscount,
                'customer_voucher_discount' => $voucherDiscount,
                'customer_voucher_code' => data_get($checkoutPreview, 'voucher.code'),
                'customer_voucher_name' => data_get($checkoutPreview, 'voucher.name'),
                'shipping_cost' => $shippingCost,
                'grand_total' => $grandTotal,
                'payment_method' => $isPayLater ? 'pay_later' : ($paymentGateway ?: 'cash'),
                'payment_status' => $isCashPayment ? 'paid' : ($isPayLater ? 'unpaid' : 'pending'),
                'bank_account_id' => $paymentGateway === 'bank_transfer' ? ($data['bank_account_id'] ?? null) : null,
            ]);

            foreach ($carts as $cart) {
                $pricingItem = $pricingItems->firstWhere('cart_id', $cart->id);
                $lineTotal = (int) data_get($pricingItem, 'line_total', $cart->price);
                $linePromoDiscount = (int) data_get($pricingItem, 'line_discount_total', 0);
                $baseUnitPrice = (int) data_get($pricingItem, 'base_unit_price', $cart->product->sell_price);
                $unitPrice = (int) data_get($pricingItem, 'effective_unit_price', $cart->product->sell_price);
                $unit = $cart->productUnit;
                $unitConversionQty = (float) ($cart->unit_conversion_qty ?: $unit?->conversion_qty ?: 1);

                $transaction->details()->create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $cart->product_id,
                    'product_unit_id' => $cart->product_unit_id,
                    'unit_label' => $cart->unit_label ?? $unit?->label,
                    'unit_conversion_qty' => $unitConversionQty,
                    'qty' => $cart->qty,
                    'base_unit_price' => $baseUnitPrice,
                    'unit_price' => $unitPrice,
                    'price' => $lineTotal,
                    'discount_total' => $linePromoDiscount,
                    'pricing_rule_id' => data_get($pricingItem, 'pricing_rule.id'),
                    'pricing_rule_name' => data_get($pricingItem, 'pricing_rule.name'),
                    'pricing_rule_kind' => data_get($pricingItem, 'pricing_rule.kind'),
                    'pricing_group_key' => data_get($pricingItem, 'pricing_group_key'),
                    'pricing_group_label' => data_get($pricingItem, 'pricing_group_label'),
                ]);

                $totalBuyPrice = ($unit?->buy_price ?? $cart->product->buy_price) * (float) $cart->qty;
                $lineShare = $subtotalAfterPromo > 0 ? $lineTotal / $subtotalAfterPromo : 0;
                $allocatedManualDiscount = (int) round($appliedManualDiscount * $lineShare);
                $netSellPrice = max(0, $lineTotal - $allocatedManualDiscount);
                $profits = $netSellPrice - $totalBuyPrice;

                $transaction->profits()->create([
                    'transaction_id' => $transaction->id,
                    'total' => $profits,
                ]);

                $product = Product::find($cart->product_id);
                $product->stock = $product->stock - ((float) $cart->qty * $unitConversionQty);
                $product->save();
            }

            Cart::where('cashier_id', $user->id)->active()->delete();

            $this->loyaltyService->finalizeTransaction($transaction, $customer, $checkoutPreview);

            if ($isPayLater) {
                Receivable::create([
                    'customer_id' => $customerId,
                    'transaction_id' => $transaction->id,
                    'invoice' => $invoice,
                    'total' => $grandTotal,
                    'paid' => 0,
                    'due_date' => $data['due_date'] ?? null,
                    'status' => 'unpaid',
                ]);
            }

            return $transaction->fresh(['customer']);
        });
    }
}
