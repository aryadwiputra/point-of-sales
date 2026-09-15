<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Http\Resources\CashierShiftResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\TransactionResource;
use App\Http\Traits\ApiResponder;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\CustomerVoucher;
use App\Models\DiscountApprovalLog;
use App\Models\PaymentSetting;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\Receivable;
use App\Models\Transaction;
use App\Models\TransactionTender;
use App\Models\Warehouse;
use App\Services\CashierShiftService;
use App\Services\LoyaltyService;
use App\Services\OutletAccessService;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\PriceListService;
use App\Services\PricingService;
use App\Services\TransactionTenderService;
use App\Services\UnitConversionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosApiController extends Controller
{
    use ApiResponder;

    public function __construct(
        private readonly CashierShiftService $cashierShiftService,
        private readonly PricingService $pricingService,
        private readonly LoyaltyService $loyaltyService,
        private readonly UnitConversionService $unitConversionService,
        private readonly PriceListService $priceListService,
        private readonly TransactionTenderService $tenderService,
        private readonly OutletAccessService $outletAccessService,
    ) {}

    /**
     * GET /api/v1/pos/shift
     * Current active shift (or null).
     */
    public function currentShift(Request $request): JsonResponse
    {
        $shift = $this->cashierShiftService->getActiveShiftForUser($request->user()->id);

        return $this->ok([
            'shift' => $shift ? new CashierShiftResource($shift->load('warehouse')) : null,
        ]);
    }

    /**
     * POST /api/v1/pos/shift/open
     * Open a cashier shift.
     */
    public function openShift(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'opening_cash' => ['required', 'numeric', 'min:0'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $warehouseId = $validated['warehouse_id'] ?? null;
        if (! $warehouseId) {
            $warehouse = Warehouse::active()->orderBy('code')->first();
            $warehouseId = $warehouse?->id;
        }

        if (! $this->outletAccessService->canUseWarehouse(
            $request->user(),
            $warehouseId ? Warehouse::find($warehouseId) : null
        )) {
            return $this->forbidden('Outlet tidak dapat digunakan oleh pengguna ini.');
        }

        try {
            $shift = $this->cashierShiftService->openShift(
                cashier: $request->user(),
                actor: $request->user(),
                openingCash: (int) $validated['opening_cash'],
                notes: $validated['notes'] ?? null,
                warehouseId: $warehouseId,
            );
        } catch (ValidationException $e) {
            return $this->validationError($e->errors(), $e->getMessage());
        }

        return $this->created(
            new CashierShiftResource($shift->load('warehouse')),
            'Shift kasir berhasil dibuka'
        );
    }

    /**
     * POST /api/v1/pos/shift/close
     * Close the active shift with actual cash count.
     */
    public function closeShift(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'closing_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $shift = $this->cashierShiftService->getActiveShiftForUser($request->user()->id);

        if (! $shift) {
            return $this->error('Tidak ada shift aktif.', 422);
        }

        try {
            $closed = $this->cashierShiftService->closeShift(
                shift: $shift,
                actor: $request->user(),
                actualCash: (int) $validated['closing_cash'],
                closeNotes: $validated['notes'] ?? null,
            );
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->ok(
            new CashierShiftResource($closed->load('warehouse')),
            'Shift kasir berhasil ditutup'
        );
    }

    /**
     * GET /api/v1/pos/products?search=&category_id=&page=&per_page=
     * Searchable product list (for the POS picker).
     */
    public function products(Request $request): JsonResponse
    {
        $shift = $this->cashierShiftService->getActiveShiftForUser($request->user()->id);
        $warehouseId = $shift?->warehouse_id;

        $products = Product::query()
            ->with('category')
            ->when($warehouseId, function ($q) use ($warehouseId) {
                $q->whereHas('warehouses', fn ($w) => $w->where('product_warehouse.warehouse_id', $warehouseId)
                    ->where('product_warehouse.stock', '>', 0));
            }, function ($q) {
                $q->where('stock', '>', 0);
            })
            ->when($request->string('search')->toString(), function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('title', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->when($request->integer('category_id'), fn ($q, $id) => $q->where('category_id', $id))
            ->latest()
            ->paginate($this->perPage());

        // Attach warehouse stock if shift is active
        $products->through(function (Product $product) use ($warehouseId) {
            $data = (new ProductResource($product))->resolve();
            $data['stock'] = $warehouseId
                ? (int) ($product->warehouses()->where('warehouse_id', $warehouseId)->first()?->pivot->stock ?? 0)
                : (int) $product->stock;

            return $data;
        });

        return $this->paginated($products);
    }

    /**
     * POST /api/v1/pos/products/scan
     * Scan barcode → return product with warehouse stock.
     */
    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => ['required', 'string'],
        ]);

        $shift = $this->cashierShiftService->getActiveShiftForUser($request->user()->id);
        $warehouseId = $shift?->warehouse_id;

        $product = Product::whereRaw('LOWER(barcode) = ?', [strtolower($validated['barcode'])])
            ->when($warehouseId, fn ($q) => $q->whereHas('warehouses', fn ($w) => $w->where('product_warehouse.warehouse_id', $warehouseId)))
            ->first();

        if (! $product) {
            return $this->notFound('Produk dengan barcode tersebut tidak ditemukan.');
        }

        $stock = $warehouseId
            ? (int) ($product->warehouses()->where('warehouse_id', $warehouseId)->first()?->pivot->stock ?? 0)
            : (int) $product->stock;

        $data = (new ProductResource($product->load('category')))->resolve();
        $data['stock'] = $stock;
        $data['units'] = $product->units()->get()->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'conversion_factor' => (float) $u->pivot->conversion_factor,
        ]);

        return $this->ok($data, 'Produk ditemukan');
    }

    /**
     * GET /api/v1/pos/cart
     * Current active cart.
     */
    public function cart(Request $request): JsonResponse
    {
        $carts = Cart::with('product', 'unit')
            ->where('cashier_id', $request->user()->id)
            ->active()
            ->latest()
            ->get();

        $customer = $request->integer('customer_id')
            ? Customer::find($request->integer('customer_id'))
            : null;

        $activeShift = $this->cashierShiftService->getActiveShiftForUser($request->user()->id);
        $activeShift?->loadMissing('warehouse.outlet');
        $outlet = $activeShift?->warehouse?->outlet;
        $preview = $this->pricingService->previewCart($carts, $customer, null, $outlet);
        $checkout = $this->loyaltyService->previewCheckout($preview, $customer, [
            'manual_discount' => (int) $request->integer('discount', 0),
            'shipping_cost' => (int) $request->integer('shipping_cost', 0),
            'redeem_points' => (int) $request->integer('redeem_points', 0),
        ], null, $outlet);

        return $this->ok([
            'items' => CartResource::collection($carts),
            'summary' => [
                'subtotal' => (float) data_get($checkout, 'summary.subtotal', 0),
                'subtotal_after_promo' => (float) data_get($checkout, 'summary.subtotal_after_promo', 0),
                'discount_total' => (float) data_get($checkout, 'summary.discount_total', 0),
                'voucher_discount' => (float) data_get($checkout, 'summary.voucher_discount_total', 0),
                'loyalty_discount' => (float) data_get($checkout, 'summary.loyalty_discount_total', 0),
                'manual_discount' => (float) data_get($checkout, 'summary.manual_discount_total', 0),
                'shipping_cost' => (float) $request->integer('shipping_cost', 0),
                'tax_rate' => (float) data_get($checkout, 'summary.tax_rate', 0),
                'tax_total' => (float) data_get($checkout, 'summary.tax_total', 0),
                'grand_total' => (float) data_get($checkout, 'summary.grand_total', 0),
                'points_earned' => (int) data_get($checkout, 'summary.points_earned', 0),
                'available_points' => (int) data_get($checkout, 'customer.available_points', 0),
            ],
        ]);
    }

    /**
     * POST /api/v1/pos/cart
     * Add product to cart.
     */
    public function addToCart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'qty' => ['required', 'numeric', 'min:0.01'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
        ]);

        $shift = $this->cashierShiftService->getActiveShiftForUser($request->user()->id);

        if (! $shift) {
            return $this->error('Shift kasir belum dibuka.', 422);
        }

        $warehouseId = $shift->warehouse_id;

        $product = Product::find($validated['product_id']);

        if (! $product) {
            return $this->notFound('Produk tidak ditemukan.');
        }

        if ($product->is_composite) {
            $product->load('components');
            foreach ($product->components as $component) {
                $needed = (float) $component->pivot->qty * $validated['qty'];
                $avail = (int) ($component->warehouses()->where('warehouse_id', $warehouseId)->first()?->pivot->stock ?? 0);
                if ($avail < $needed) {
                    return $this->error("Stok {$component->title} tidak mencukupi.", 422);
                }
            }
            $sellPrice = (int) $product->components->sum(fn ($c) => $c->sell_price * (float) $c->pivot->qty);
            $unitId = null;
            $conversionFactor = 1;
        } else {
            $unitId = (int) ($validated['unit_id'] ?? $product->baseUnit()?->id ?? 1);

            if (isset($validated['unit_id']) && ! $product->units()->whereKey($unitId)->exists()) {
                return $this->error('Satuan tidak valid untuk produk ini.', 422);
            }

            $baseQty = $this->unitConversionService->toBaseUnit($product, $unitId, $validated['qty']);

            $availableStock = $warehouseId
                ? (int) ($product->warehouses()->where('warehouse_id', $warehouseId)->first()?->pivot->stock ?? 0)
                : (int) $product->stock;

            if ($availableStock < $baseQty) {
                return $this->error("Stok tidak mencukupi. Tersedia: {$availableStock}", 422);
            }

            $sellPrice = $this->unitConversionService->getSellPrice($product, $unitId);
            $pu = $product->units()->where('unit_id', $unitId)->first();
            $conversionFactor = $pu?->pivot->conversion_factor ?? 1;
        }

        $cart = Cart::with('product')
            ->where('product_id', $validated['product_id'])
            ->where('cashier_id', $request->user()->id)
            ->active()
            ->first();

        if ($cart) {
            $cart->increment('qty', $validated['qty']);
            $cart->price = $sellPrice * $cart->qty;
            $cart->save();
        } else {
            $cart = Cart::create([
                'cashier_id' => $request->user()->id,
                'warehouse_id' => $warehouseId,
                'product_id' => $validated['product_id'],
                'unit_id' => $unitId,
                'conversion_factor' => $conversionFactor,
                'qty' => $validated['qty'],
                'price' => $sellPrice * $validated['qty'],
            ]);
        }

        return $this->ok(
            new CartResource($cart->load('product', 'unit')),
            'Produk ditambahkan ke keranjang'
        );
    }

    /**
     * PUT /api/v1/pos/cart/{cart}
     * Update cart quantity.
     */
    public function updateCart(Request $request, Cart $cart): JsonResponse
    {
        $validated = $request->validate([
            'qty' => ['required', 'numeric', 'min:0.01'],
        ]);

        if ($cart->cashier_id !== $request->user()->id) {
            return $this->forbidden('Bukan keranjang milik Anda.');
        }

        $shift = $this->cashierShiftService->getActiveShiftForUser($request->user()->id);

        if (! $shift) {
            return $this->error('Shift kasir belum dibuka.', 422);
        }

        $warehouseId = $shift->warehouse_id;

        $product = $cart->product;

        if (! $product->is_composite) {
            $baseQty = $this->unitConversionService->toBaseUnit($product, $cart->unit_id, $validated['qty']);
            $availableStock = $warehouseId
                ? (int) ($product->warehouses()->where('warehouse_id', $warehouseId)->first()?->pivot->stock ?? 0)
                : (int) $product->stock;

            if ($availableStock < $baseQty) {
                return $this->error("Stok tidak mencukupi. Tersedia: {$availableStock}", 422);
            }
        }

        $cart->qty = $validated['qty'];
        $cart->price = $product->is_composite
            ? (int) $product->components->sum(fn ($c) => $c->sell_price * (float) $c->pivot->qty) * $validated['qty']
            : $this->unitConversionService->getSellPrice($product, $cart->unit_id) * $validated['qty'];
        $cart->save();

        return $this->ok(new CartResource($cart->load('product', 'unit')), 'Keranjang diperbarui');
    }

    /**
     * DELETE /api/v1/pos/cart/{cart}
     * Remove item from cart.
     */
    public function removeFromCart(Request $request, Cart $cart): JsonResponse
    {
        if ($cart->cashier_id !== $request->user()->id) {
            return $this->forbidden('Bukan keranjang milik Anda.');
        }

        $cart->delete();

        return $this->noContent();
    }

    /**
     * POST /api/v1/pos/cart/clear
     * Clear active cart.
     */
    public function clearCart(Request $request): JsonResponse
    {
        Cart::where('cashier_id', $request->user()->id)->active()->delete();

        return $this->ok(null, 'Keranjang dikosongkan');
    }

    /**
     * POST /api/v1/pos/hold
     * Hold current cart.
     */
    public function holdCart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:50'],
        ]);

        $userId = $request->user()->id;
        $activeCarts = Cart::where('cashier_id', $userId)->active()->get();

        if ($activeCarts->isEmpty()) {
            return $this->error('Keranjang kosong, tidak ada yang bisa ditahan.', 422);
        }

        $holdId = 'HOLD-'.strtoupper(Str::random(10));
        $label = $validated['label'] ?? 'Transaksi '.now()->format('H:i');

        Cart::where('cashier_id', $userId)->active()->update([
            'hold_id' => $holdId,
            'hold_label' => $label,
            'held_at' => now(),
        ]);

        return $this->ok(['hold_id' => $holdId, 'label' => $label], 'Transaksi ditahan');
    }

    /**
     * GET /api/v1/pos/holds
     * List held carts.
     */
    public function heldCarts(Request $request): JsonResponse
    {
        $held = Cart::with('product:id,title,sell_price,image')
            ->where('cashier_id', $request->user()->id)
            ->held()
            ->get()
            ->groupBy('hold_id')
            ->map(function ($items, $holdId) {
                $first = $items->first();

                return [
                    'hold_id' => $holdId,
                    'label' => $first->hold_label,
                    'held_at' => optional($first->held_at)->toISOString(),
                    'items_count' => (int) $items->sum('qty'),
                    'total' => (float) $items->sum('price'),
                ];
            })
            ->values();

        return $this->ok(['holds' => $held]);
    }

    /**
     * POST /api/v1/pos/holds/{holdId}/resume
     * Resume a held cart.
     */
    public function resumeHold(Request $request, string $holdId): JsonResponse
    {
        $userId = $request->user()->id;

        $activeCount = Cart::where('cashier_id', $userId)->active()->count();
        if ($activeCount > 0) {
            return $this->error('Selesaikan atau tahan transaksi aktif terlebih dahulu.', 422);
        }

        $held = Cart::where('cashier_id', $userId)->forHold($holdId)->get();
        if ($held->isEmpty()) {
            return $this->notFound('Transaksi ditahan tidak ditemukan.');
        }

        Cart::where('cashier_id', $userId)->forHold($holdId)->update([
            'hold_id' => null,
            'hold_label' => null,
            'held_at' => null,
        ]);

        return $this->ok(null, 'Transaksi dilanjutkan');
    }

    /**
     * DELETE /api/v1/pos/holds/{holdId}
     * Delete a held cart.
     */
    public function deleteHold(Request $request, string $holdId): JsonResponse
    {
        $deleted = Cart::where('cashier_id', $request->user()->id)->forHold($holdId)->delete();

        if ($deleted === 0) {
            return $this->notFound('Transaksi ditahan tidak ditemukan.');
        }

        return $this->noContent();
    }

    /**
     * POST /api/v1/pos/checkout
     * Complete a transaction. Supports cash, pay_later, and payment gateways.
     */
    public function checkout(Request $request, PaymentGatewayManager $paymentGatewayManager): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => [
                'nullable',
                'integer',
                'exists:customers,id',
                'required_if:payment_method,pay_later',
            ],
            'customer_voucher_id' => ['nullable', 'integer', 'exists:customer_vouchers,id'],
            'discount' => ['nullable', 'integer', 'min:0'],
            'shipping_cost' => ['nullable', 'integer', 'min:0'],
            'redeem_points' => ['nullable', 'integer', 'min:0'],
            'cash' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'in:cash,bank_transfer,midtrans,xendit,qris,pay_later'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'due_date' => ['nullable', 'date', 'required_if:payment_method,pay_later'],
            'customer_npwp' => ['nullable', 'string', 'max:50'],
            'order_type' => ['nullable', 'in:in_store,takeaway,delivery'],
            'note' => ['nullable', 'string', 'max:1000'],
            'tenders' => ['nullable', 'array', 'max:2'],
            'tenders.*.method' => ['required', 'string'],
            'tenders.*.amount' => ['required', 'integer', 'min:1'],
            'tenders.*.cash_received' => ['nullable', 'integer', 'min:0'],
            'tenders.*.bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
        ]);

        $paymentMethod = $validated['payment_method'] ?? 'cash';
        $isPayLater = $paymentMethod === 'pay_later';
        $tenderInput = $validated['tenders'] ?? [];
        $useTenders = ! $isPayLater && $tenderInput !== [];
        $paymentGateway = ! $isPayLater && $paymentMethod !== 'cash'
            ? $paymentMethod
            : null;
        $activeShift = $this->cashierShiftService->getActiveShiftForUser($request->user()->id);
        $activeShift?->load('warehouse.outlet');
        $outlet = $activeShift?->warehouse?->outlet;

        if ($isPayLater && ! $request->filled('due_date')) {
            return $this->error('Tanggal jatuh tempo wajib diisi untuk nota barang.', 422);
        }

        if ($paymentGateway) {
            $paymentSetting = PaymentSetting::forOutlet($outlet);
            $gatewayReady = $paymentSetting && ($paymentGateway === 'qris'
                ? $paymentSetting->isGatewayReady(PaymentSetting::GATEWAY_MIDTRANS)
                    || $paymentSetting->isGatewayReady(PaymentSetting::GATEWAY_XENDIT)
                : $paymentSetting->isGatewayReady($paymentGateway));

            if (! $gatewayReady) {
                return $this->error('Gateway pembayaran belum dikonfigurasi.', 422);
            }
        }

        $invoice = 'TRX-'.Str::upper(Str::random(10));
        $isCashPayment = ! $paymentGateway && ! $isPayLater && ! $useTenders;
        $cashAmount = $isCashPayment ? max(0, (int) $validated['cash'] ?? 0) : 0;
        $customer = isset($validated['customer_id']) ? Customer::find($validated['customer_id']) : null;
        $voucher = isset($validated['customer_voucher_id']) ? CustomerVoucher::find($validated['customer_voucher_id']) : null;
        $manualDiscount = max(0, (int) ($validated['discount'] ?? 0));
        $shippingCost = max(0, (int) ($validated['shipping_cost'] ?? 0));
        $requestedRedeemPoints = max(0, (int) ($validated['redeem_points'] ?? 0));

        try {
            $transaction = DB::transaction(function () use (
                $request, $invoice, $cashAmount, $paymentGateway, $isCashPayment, $isPayLater,
                $manualDiscount, $shippingCost, $requestedRedeemPoints, $customer, $voucher, $validated,
                $useTenders, $tenderInput, $outlet
            ) {
                $activeShift = $this->cashierShiftService->requireActiveShiftForUser(
                    $request->user()->id,
                    lockForUpdate: true
                );

                $carts = Cart::with('product')
                    ->where('cashier_id', $request->user()->id)
                    ->active()
                    ->get();

                if ($carts->isEmpty()) {
                    throw new \RuntimeException('Keranjang kosong.');
                }

                $pricingPreview = $this->pricingService->previewCart($carts, $customer, null, $outlet);
                $checkoutPreview = $this->loyaltyService->previewCheckout($pricingPreview, $customer, [
                    'manual_discount' => $manualDiscount,
                    'shipping_cost' => $shippingCost,
                    'redeem_points' => $requestedRedeemPoints,
                    'voucher' => $voucher,
                ], null, $outlet);
                $pricingItems = collect($pricingPreview['items']);
                $subtotalAfterPromo = (int) data_get($pricingPreview, 'summary.subtotal_after_promo', 0);
                $voucherDiscount = (int) data_get($checkoutPreview, 'summary.voucher_discount_total', 0);
                $loyaltyDiscount = (int) data_get($checkoutPreview, 'summary.loyalty_discount_total', 0);
                $appliedManualDiscount = (int) data_get($checkoutPreview, 'summary.manual_discount_total', 0);
                $grandTotal = (int) data_get($checkoutPreview, 'summary.grand_total', 0);
                $changeAmount = $isCashPayment ? max(0, $cashAmount - $grandTotal) : 0;

                if ($isCashPayment && $cashAmount < $grandTotal) {
                    throw ValidationException::withMessages([
                        'cash' => 'Uang tunai kurang dari total belanja.',
                    ]);
                }

                // ponytail: legacy single-method path kept — split tenders only activate when payload has tenders[]
                $tenders = $useTenders ? $this->tenderService->normalize($tenderInput, $grandTotal, allowEmpty: false, outlet: $outlet) : [];
                $tenderCash = collect($tenders)->where('method', TransactionTender::METHOD_CASH)->sum('cash_received');
                $tenderChange = collect($tenders)->sum('change');
                $tenderBankAccountId = collect($tenders)->firstWhere('bank_account_id', '!==', null)['bank_account_id'] ?? null;

                $transaction = Transaction::create([
                    'cashier_id' => $request->user()->id,
                    'cashier_shift_id' => $activeShift->id,
                    'warehouse_id' => $activeShift->warehouse_id,
                    'customer_id' => $validated['customer_id'] ?? null,
                    'invoice' => $invoice,
                    'cash' => $useTenders ? $tenderCash : $cashAmount,
                    'change' => $useTenders ? $tenderChange : $changeAmount,
                    'discount' => $appliedManualDiscount,
                    'loyalty_points_redeemed' => (int) data_get($checkoutPreview, 'summary.applied_redeem_points', 0),
                    'loyalty_discount_total' => $loyaltyDiscount,
                    'customer_voucher_discount' => $voucherDiscount,
                    'customer_voucher_code' => data_get($checkoutPreview, 'voucher.code'),
                    'customer_voucher_name' => data_get($checkoutPreview, 'voucher.name'),
                    'shipping_cost' => $shippingCost,
                    'grand_total' => $grandTotal,
                    'payment_method' => $useTenders
                        ? (count($tenders) > 1 ? 'split' : $tenders[0]['method'])
                        : ($isPayLater ? 'pay_later' : ($paymentGateway ?: 'cash')),
                    'payment_status' => $useTenders
                        ? $this->tenderService->aggregateStatus($tenders)
                        : ($isCashPayment ? 'paid' : ($isPayLater ? 'unpaid' : 'pending')),
                    'bank_account_id' => $useTenders
                        ? $tenderBankAccountId
                        : ($paymentGateway === 'bank_transfer' ? ($validated['bank_account_id'] ?? null) : null),
                    'order_type' => $validated['order_type'] ?? null,
                    'note' => isset($validated['note']) ? trim($validated['note']) ?: null : null,
                    'tax_rate' => data_get($checkoutPreview, 'summary.tax_rate'),
                    'tax_total' => data_get($checkoutPreview, 'summary.tax_total', 0),
                    'customer_npwp' => $validated['customer_npwp'] ?? null,
                    'price_list_id' => $this->priceListService->getApplicablePriceList($customer, $outlet)?->id,
                ]);

                if ($useTenders) {
                    $transaction->tenders()->createMany($tenders);
                }

                foreach ($carts as $cart) {
                    $pricingItem = $pricingItems->firstWhere('cart_id', $cart->id);
                    $lineTotal = (int) data_get($pricingItem, 'line_total', $cart->price);
                    $linePromoDiscount = (int) data_get($pricingItem, 'line_discount_total', 0);
                    $baseUnitPrice = (int) data_get($pricingItem, 'base_unit_price', $cart->product->sell_price);
                    $unitPrice = (int) data_get($pricingItem, 'effective_unit_price', $cart->product->sell_price);

                    $transaction->details()->create([
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
                    $transaction->profits()->create([
                        'transaction_id' => $transaction->id,
                        'total' => $netSellPrice - $totalBuyPrice,
                    ]);

                    $product = Product::find($cart->product_id);
                    $warehouseId = $activeShift->warehouse_id;

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
                                throw ValidationException::withMessages(['stock' => "Stok komponen {$component->title} tidak mencukupi. Tersedia: {$available}."]);
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
                            throw ValidationException::withMessages(['stock' => "Stok {$product->title} tidak mencukupi. Tersedia: {$available}."]);
                        }
                        if ($pw) {
                            $pw->decrement('stock', $baseQty);
                        }
                        $product->decrement('stock', $baseQty);
                    }
                }

                Cart::where('cashier_id', $request->user()->id)->active()->delete();

                $this->loyaltyService->finalizeTransaction($transaction, $customer, $checkoutPreview);

                if ($isPayLater) {
                    Receivable::create([
                        'customer_id' => $validated['customer_id'],
                        'transaction_id' => $transaction->id,
                        'invoice' => $invoice,
                        'total' => $grandTotal,
                        'paid' => 0,
                        'due_date' => $validated['due_date'],
                        'status' => 'unpaid',
                    ]);
                }

                return $transaction->fresh(['customer', 'cashier:id,name', 'warehouse:id,code,name']);
            });
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'Keranjang kosong')) {
                return $this->error('Keranjang kosong.', 422);
            }
            if ($e instanceof ValidationException) {
                return $this->validationError($e->errors(), $e->getMessage());
            }

            throw $e;
        }

        // Discount approval flow
        if ($transaction->discount > 0 && $transaction->needsDiscountApproval()) {
            $transaction->update([
                'discount_approval_status' => 'pending',
                'payment_status' => 'pending_approval',
            ]);

            DiscountApprovalLog::create([
                'transaction_id' => $transaction->id,
                'cashier_id' => $request->user()->id,
                'requested_discount' => $manualDiscount,
                'status' => 'pending',
            ]);

            return $this->ok(
                new TransactionResource($transaction->load('details.product', 'customer', 'cashier', 'tenders')),
                'Transaksi menunggu approval supervisor.',
                202
            );
        }

        // Payment gateway
        if ($useTenders) {
            $gatewayTenders = $transaction->tenders()
                ->whereIn('method', [TransactionTender::METHOD_MIDTRANS, TransactionTender::METHOD_XENDIT, TransactionTender::METHOD_QRIS])
                ->where('payment_status', TransactionTender::STATUS_PENDING)
                ->get();

            try {
                $paymentSetting ??= PaymentSetting::forOutlet($outlet);
                foreach ($gatewayTenders as $tender) {
                    $paymentResponse = $paymentGatewayManager->createTenderPayment($transaction, $tender, $paymentSetting);
                    $tender->update([
                        'payment_reference' => $paymentResponse['reference'] ?? null,
                        'payment_url' => $paymentResponse['payment_url'] ?? null,
                        'qr_string' => $paymentResponse['qr_string'] ?? null,
                    ]);
                }

                if ($gatewayTenders->count() === 1) {
                    $transaction->update($gatewayTenders->first()->only(['payment_reference', 'payment_url', 'qr_string']));
                }
            } catch (\Throwable $e) {
                // Gateway failure — transaction still valid, just no payment URL
                $transaction->update(['payment_status' => 'pending']);
            }
        } elseif ($paymentGateway) {
            try {
                $paymentResponse = $paymentGateway === 'qris'
                    ? $paymentGatewayManager->createQrisPayment($transaction, $paymentSetting)
                    : $paymentGatewayManager->createPayment($transaction, $paymentGateway, $paymentSetting);

                $transaction->update([
                    'payment_method' => $paymentGateway === 'qris'
                        ? ($paymentResponse['raw']['payment_type'] ?? 'qris')
                        : $paymentGateway,
                    'payment_reference' => $paymentResponse['reference'] ?? null,
                    'payment_url' => $paymentResponse['payment_url'] ?? null,
                    'qr_string' => $paymentResponse['qr_string'] ?? null,
                ]);
            } catch (\Throwable $e) {
                // Gateway failure — transaction still valid, just no payment URL
                $transaction->update(['payment_status' => 'pending']);
            }
        }

        $resource = new TransactionResource($transaction->load('details.product', 'customer', 'cashier', 'warehouse', 'tenders'));

        if ($paymentGateway === 'qris') {
            $data = $resource->toArray(request());
            $data['payment_method'] = $transaction->payment_method;

            return $this->created($data, 'Transaksi berhasil — scan QR untuk membayar');
        }

        return $this->created(
            $resource,
            'Transaksi berhasil'
        );
    }

    /**
     * POST /api/v1/pos/transactions/sync
     * Flush offline-queued transactions. Each item carries its own items[]
     * because server-side carts do not exist while offline. Prices are
     * revalidated server-side; the client grand_total is ignored.
     */
    public function syncTransactions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'transactions' => ['required', 'array', 'max:50'],
            'transactions.*.client_uuid' => ['required', 'uuid'],
            'transactions.*.customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'transactions.*.discount' => ['nullable', 'integer', 'min:0'],
            'transactions.*.redeem_points' => ['nullable', 'integer', 'min:0'],
            'transactions.*.cash' => ['nullable', 'numeric', 'min:0'],
            'transactions.*.pay_later' => ['nullable', 'boolean'],
            'transactions.*.due_date' => ['nullable', 'date'],
            'transactions.*.items' => ['required', 'array', 'min:1'],
            'transactions.*.items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'transactions.*.items.*.qty' => ['required', 'numeric', 'min:0.01'],
            'transactions.*.items.*.unit_id' => ['nullable', 'integer', 'exists:units,id'],
        ]);

        $results = [];

        foreach ($validated['transactions'] as $index => $payload) {
            $uuid = $payload['client_uuid'];

            // Idempotency: already synced?
            if ($existing = Transaction::where('client_uuid', $uuid)->first()) {
                $results[] = [
                    'client_uuid' => $uuid,
                    'status' => 'duplicate',
                    'transaction_id' => $existing->id,
                    'invoice' => $existing->invoice,
                ];

                continue;
            }

            $response = $this->syncOne($request, $payload, $uuid);

            $results[] = array_merge(['client_uuid' => $uuid], $response);
        }

        return $this->ok(['results' => $results]);
    }

    private function syncOne(Request $request, array $payload, string $uuid): array
    {
        $isPayLater = (bool) ($payload['pay_later'] ?? false);
        $user = $request->user();

        if (! $user) {
            return ['status' => 'failed', 'reason' => 'Tidak terautentikasi.'];
        }

        // Rebuild cart rows from the offline items, then reuse the normal
        // checkout flow so pricing/stock/loyalty logic has a single source.
        try {
            $shift = $this->cashierShiftService->getActiveShiftForUser($user->id);

            if (! $shift) {
                return ['status' => 'failed', 'reason' => 'Shift kasir belum dibuka. Transaksi offline tetap tersimpan di antrean.'];
            }

            $createdCartIds = [];

            foreach ($payload['items'] as $item) {
                $product = Product::find($item['product_id']);

                if (! $product) {
                    $this->deleteCarts($createdCartIds);

                    return ['status' => 'failed', 'reason' => "Produk #{$item['product_id']} tidak ditemukan."];
                }

                if ($product->is_composite) {
                    $product->load('components');
                    $sellPrice = (int) $product->components->sum(fn ($c) => $c->sell_price * (float) $c->pivot->qty);
                    $cart = Cart::create([
                        'cashier_id' => $user->id,
                        'warehouse_id' => $shift->warehouse_id,
                        'product_id' => $product->id,
                        'unit_id' => null,
                        'conversion_factor' => 1,
                        'qty' => $item['qty'],
                        'price' => $sellPrice * $item['qty'],
                    ]);
                } else {
                    $unitId = (int) ($item['unit_id'] ?? $product->baseUnit()?->id ?? 1);
                    $pu = $product->units()->where('unit_id', $unitId)->first();
                    $conversionFactor = $pu?->pivot->conversion_factor ?? 1;
                    $sellPrice = $this->unitConversionService->getSellPrice($product, $unitId);

                    $cart = Cart::create([
                        'cashier_id' => $user->id,
                        'warehouse_id' => $shift->warehouse_id,
                        'product_id' => $product->id,
                        'unit_id' => $unitId,
                        'conversion_factor' => $conversionFactor,
                        'qty' => $item['qty'],
                        'price' => $sellPrice * $item['qty'],
                    ]);
                }

                $createdCartIds[] = $cart->id;
            }

            // Reuse checkout() with a synthesized request.
            $checkoutRequest = new Request(array_filter([
                'customer_id' => $payload['customer_id'] ?? null,
                'discount' => $payload['discount'] ?? 0,
                'redeem_points' => $payload['redeem_points'] ?? 0,
                'cash' => $payload['cash'] ?? null,
                'payment_method' => $isPayLater ? 'pay_later' : 'cash',
                'due_date' => $isPayLater ? ($payload['due_date'] ?? null) : null,
                'client_uuid' => $uuid,
            ]));

            $checkoutRequest->setUserResolver(fn () => $user);

            $response = $this->checkout($checkoutRequest, app(PaymentGatewayManager::class));

            // checkout() succeeded (2xx) — carts were consumed by it.
            $json = json_decode($response->getContent(), true);

            if ($response->isSuccessful()) {
                $transactionId = data_get($json, 'data.id');
                if ($transactionId) {
                    Transaction::whereKey($transactionId)->update(['client_uuid' => $uuid]);
                }

                return [
                    'status' => $response->getStatusCode() === 202 ? 'pending_approval' : 'synced',
                    'transaction_id' => $transactionId,
                    'invoice' => data_get($json, 'data.invoice'),
                    'grand_total' => (int) data_get($json, 'data.grand_total', 0),
                ];
            }

            // Checkout failed — remove the carts we just created.
            $this->deleteCarts($createdCartIds);

            return ['status' => 'failed', 'reason' => data_get($json, 'message', 'Checkout gagal.')];
        } catch (\Throwable $e) {
            report($e);

            return ['status' => 'failed', 'reason' => $e->getMessage()];
        }
    }

    private function deleteCarts(array $ids): void
    {
        if ($ids !== []) {
            Cart::whereIn('id', $ids)->delete();
        }
    }

    /**
     * GET /api/v1/pos/transactions?page=&per_page=&date_from=&date_to=
     * Transaction history (this cashier, or all for super-admin).
     */
    public function transactions(Request $request): JsonResponse
    {
        $query = Transaction::query()
            ->with(['cashier:id,name', 'warehouse:id,code,name', 'customer:id,name', 'tenders'])
            ->withSum('details as total_items', 'qty')
            ->orderByDesc('created_at');

        if (! $request->user()->isSuperAdmin()) {
            $query->where('cashier_id', $request->user()->id);
        }

        $query
            ->when($request->string('invoice')->toString(), fn ($q, $inv) => $q->where('invoice', 'like', "%{$inv}%"))
            ->when($request->string('date_from')->toString(), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->string('date_to')->toString(), fn ($q, $d) => $q->whereDate('created_at', '<=', $d));

        $transactions = $query->paginate($this->perPage());

        return $this->paginated($transactions, TransactionResource::collection($transactions));
    }

    /**
     * GET /api/v1/pos/transactions/{transaction}
     * Transaction detail with items.
     */
    public function transactionDetail(Request $request, Transaction $transaction): JsonResponse
    {
        if (! $request->user()->isSuperAdmin() && $transaction->cashier_id !== $request->user()->id) {
            return $this->forbidden('Bukan transaksi Anda.');
        }

        $transaction->load('details.product', 'customer', 'cashier', 'warehouse', 'receivable', 'bankAccount', 'tenders');

        return $this->ok(new TransactionResource($transaction));
    }
}
