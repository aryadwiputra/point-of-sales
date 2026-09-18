<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductWarehouse;
use App\Models\Receivable;
use App\Models\Transaction;
use App\Models\TransactionTender;
use App\Support\Checkout\CheckoutContext;
use App\Support\Checkout\CheckoutResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        private readonly CashierShiftService $cashierShiftService,
        private readonly PricingService $pricingService,
        private readonly LoyaltyService $loyaltyService,
        private readonly PriceListService $priceListService,
        private readonly BatchService $batchService,
        private readonly TransactionTenderService $tenderService,
    ) {}

    public function execute(CheckoutContext $ctx): CheckoutResult
    {
        return DB::transaction(function () use ($ctx) {
            $activeShift = $this->cashierShiftService->requireActiveShiftForUser(
                $ctx->userId,
                lockForUpdate: true,
            );

            $carts = Cart::with('product')
                ->where('cashier_id', $ctx->userId)
                ->active()
                ->get();

            if ($carts->isEmpty()) {
                abort(422, 'Keranjang kosong.');
            }

            $pricingPreview = $this->pricingService->previewCart($carts, $ctx->customer, null, $ctx->outlet);
            $checkoutPreview = $this->loyaltyService->previewCheckout(
                $pricingPreview,
                $ctx->customer,
                [
                    'manual_discount' => $ctx->manualDiscount,
                    'shipping_cost' => $ctx->shippingCost,
                    'redeem_points' => $ctx->requestedRedeemPoints,
                    'voucher' => $ctx->voucher,
                ],
                null,
                $ctx->outlet,
            );

            $grandTotal = (int) data_get($checkoutPreview, 'summary.grand_total', 0);
            $appliedManualDiscount = (int) data_get($checkoutPreview, 'summary.manual_discount_total', 0);
            $loyaltyDiscount = (int) data_get($checkoutPreview, 'summary.loyalty_discount_total', 0);
            $voucherDiscount = (int) data_get($checkoutPreview, 'summary.voucher_discount_total', 0);
            $subtotalAfterPromo = (int) data_get($pricingPreview, 'summary.subtotal_after_promo', 0);

            if ($ctx->isCashPayment && $ctx->cashAmount < $grandTotal) {
                throw ValidationException::withMessages([
                    'cash' => 'Uang tunai kurang dari total belanja.',
                ]);
            }

            $tenders = $ctx->useTenders
                ? $this->tenderService->normalize($ctx->tenderInput, $grandTotal, allowEmpty: false, outlet: $ctx->outlet)
                : [];

            $tenderCash = (int) collect($tenders)->where('method', TransactionTender::METHOD_CASH)->sum('cash_received');
            $tenderChange = (int) collect($tenders)->sum('change');
            $paymentMethod = $ctx->useTenders
                ? (count($tenders) > 1 ? 'split' : $tenders[0]['method'])
                : ($ctx->isPayLater ? 'pay_later' : ($ctx->paymentGateway ?: 'cash'));
            $paymentStatus = $ctx->useTenders
                ? $this->tenderService->aggregateStatus($tenders)
                : ($ctx->isCashPayment ? 'paid' : ($ctx->isPayLater ? 'unpaid' : 'pending'));
            $tenderBankAccountId = $ctx->useTenders
                ? (collect($tenders)->firstWhere('bank_account_id', '!==', null)['bank_account_id'] ?? null)
                : ($ctx->paymentGateway === 'bank_transfer' ? $ctx->bankAccountId : null);

            $transaction = Transaction::create([
                'cashier_id' => $ctx->userId,
                'cashier_shift_id' => $activeShift->id,
                'warehouse_id' => $activeShift->warehouse_id,
                'customer_id' => $ctx->customer?->id,
                'invoice' => 'TRX-'.strtoupper(Str::random(10)),
                'cash' => $ctx->useTenders ? $tenderCash : $ctx->cashAmount,
                'change' => $ctx->useTenders ? $tenderChange : max(0, $ctx->cashAmount - $grandTotal),
                'discount' => $appliedManualDiscount,
                'loyalty_points_redeemed' => (int) data_get($checkoutPreview, 'summary.applied_redeem_points', 0),
                'loyalty_discount_total' => $loyaltyDiscount,
                'customer_voucher_discount' => $voucherDiscount,
                'customer_voucher_code' => data_get($checkoutPreview, 'voucher.code'),
                'customer_voucher_name' => data_get($checkoutPreview, 'voucher.name'),
                'shipping_cost' => $ctx->shippingCost,
                'grand_total' => $grandTotal,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'bank_account_id' => $tenderBankAccountId,
                'order_type' => $ctx->orderType,
                'note' => $ctx->note,
                'tax_rate' => data_get($checkoutPreview, 'summary.tax_rate'),
                'tax_total' => data_get($checkoutPreview, 'summary.tax_total', 0),
                'customer_npwp' => $ctx->customerNpwp,
                'price_list_id' => $this->priceListService->getApplicablePriceList($ctx->customer, $ctx->outlet)?->id,
            ]);

            if ($ctx->useTenders) {
                $transaction->tenders()->createMany($tenders);
            }

            $this->processCartItems($transaction, $carts, $pricingPreview, $ctx->outlet, $subtotalAfterPromo, $appliedManualDiscount, $activeShift->warehouse_id);

            Cart::where('cashier_id', $ctx->userId)->active()->delete();

            $this->loyaltyService->finalizeTransaction($transaction, $ctx->customer, $checkoutPreview);

            if ($ctx->isPayLater) {
                Receivable::create([
                    'customer_id' => $ctx->customer?->id,
                    'transaction_id' => $transaction->id,
                    'invoice' => $transaction->invoice,
                    'total' => $grandTotal,
                    'paid' => 0,
                    'due_date' => $ctx->dueDate,
                    'status' => 'unpaid',
                ]);
            }

            $needsDiscountApproval = $appliedManualDiscount > 0 && $transaction->needsDiscountApproval();

            return new CheckoutResult(
                transaction: $transaction->fresh(['customer']),
                pricingPreview: $pricingPreview,
                checkoutPreview: $checkoutPreview,
                needsDiscountApproval: $needsDiscountApproval,
                appliedManualDiscount: $appliedManualDiscount,
                tenders: $tenders,
                paymentMethod: $paymentMethod,
                paymentStatus: $paymentStatus,
                tenderBankAccountId: $tenderBankAccountId,
            );
        });
    }

    private function processCartItems(
        Transaction $transaction,
        $carts,
        array $pricingPreview,
        $outlet,
        int $subtotalAfterPromo,
        int $appliedManualDiscount,
        ?int $warehouseId,
    ): void {
        $pricingItems = collect($pricingPreview['items']);

        foreach ($carts as $cart) {
            $pricingItem = $pricingItems->firstWhere('cart_id', $cart->id);
            $lineTotal = (int) data_get($pricingItem, 'line_total', $cart->price);
            $linePromoDiscount = (int) data_get($pricingItem, 'line_discount_total', 0);
            $baseUnitPrice = (int) data_get($pricingItem, 'base_unit_price', $cart->product->sell_price);
            $unitPrice = (int) data_get($pricingItem, 'effective_unit_price', $cart->product->sell_price);

            $detail = $transaction->details()->create([
                'transaction_id' => $transaction->id,
                'product_id' => $cart->product_id,
                'unit_id' => $cart->unit_id,
                'conversion_factor' => $cart->conversion_factor,
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

            $totalBuyPrice = $cart->product->buy_price * $cart->qty;
            $lineShare = $subtotalAfterPromo > 0 ? $lineTotal / $subtotalAfterPromo : 0;
            $allocatedManualDiscount = (int) round($appliedManualDiscount * $lineShare);
            $netSellPrice = max(0, $lineTotal - $allocatedManualDiscount);
            $profits = $netSellPrice - $totalBuyPrice;

            $transaction->profits()->create([
                'transaction_id' => $transaction->id,
                'total' => $profits,
            ]);

            $product = Product::find($cart->product_id);

            if ($product->is_composite) {
                $product->load('components');
                foreach ($product->components as $component) {
                    $componentQty = (int) round((float) $component->pivot->qty * $cart->qty);
                    $pw = $warehouseId
                        ? ProductWarehouse::where([
                            'product_id' => $component->id,
                            'warehouse_id' => $warehouseId,
                        ])->lockForUpdate()->first()
                        : null;
                    $available = $pw ? (int) $pw->stock : (int) $component->stock;
                    if ($available < $componentQty) {
                        throw ValidationException::withMessages([
                            'stock' => "Stok komponen {$component->title} tidak mencukupi. Tersedia: {$available}.",
                        ]);
                    }
                    if ($pw) {
                        $pw->decrement('stock', $componentQty);
                    }
                    $component->decrement('stock', $componentQty);
                }
            } else {
                $baseQty = (int) round($cart->qty * (float) ($cart->conversion_factor ?? 1));

                $pw = $warehouseId
                    ? ProductWarehouse::where([
                        'product_id' => $product->id,
                        'warehouse_id' => $warehouseId,
                    ])->lockForUpdate()->first()
                    : null;
                $available = $pw ? (int) $pw->stock : (int) $product->stock;
                if ($available < $baseQty) {
                    throw ValidationException::withMessages([
                        'stock' => "Stok {$product->title} tidak mencukupi. Tersedia: {$available}.",
                    ]);
                }
                if ($pw) {
                    $pw->decrement('stock', $baseQty);
                }
                $product->decrement('stock', $baseQty);

                if ($warehouseId) {
                    $batches = ProductBatch::where('product_id', $product->id)
                        ->where('warehouse_id', $warehouseId)
                        ->where('stock', '>', 0)
                        ->where(function (Builder $q) {
                            $q->whereNull('expired_at')->orWhere('expired_at', '>', now());
                        })
                        ->orderBy('expired_at')
                        ->orderBy('received_at')
                        ->lockForUpdate()
                        ->get();

                    if ($batches->isNotEmpty()) {
                        $covered = (int) $batches->sum('stock');
                        if ($covered < $baseQty) {
                            throw ValidationException::withMessages([
                                'stock' => "Stok batch {$product->title} tidak mencukupi. Tersedia: {$covered}.",
                            ]);
                        }
                        $remaining = $baseQty;
                        $firstBatchId = null;
                        foreach ($batches as $batch) {
                            if ($remaining <= 0) {
                                break;
                            }
                            $take = min((int) $batch->stock, $remaining);
                            $batch->decrement('stock', $take);
                            $remaining -= $take;
                            $firstBatchId ??= $batch->id;
                            $detail->batchAllocations()->create([
                                'product_batch_id' => $batch->id,
                                'qty' => $take,
                            ]);
                        }
                        $detail->update(['product_batch_id' => $firstBatchId]);
                    }
                }
            }
        }
    }
}
