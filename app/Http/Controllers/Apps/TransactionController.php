<?php

namespace App\Http\Controllers\Apps;

use App\Exceptions\PaymentGatewayException;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerVoucher;
use App\Models\DiscountApprovalLog;
use App\Models\PaymentSetting;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductWarehouse;
use App\Models\Receivable;
use App\Models\Transaction;
use App\Models\Warehouse;
use App\Services\AuditLogService;
use App\Services\BatchService;
use App\Services\CashierShiftService;
use App\Services\LoyaltyService;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\PriceListService;
use App\Services\PricingService;
use App\Services\UnitConversionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TransactionController extends Controller
{
    public function __construct(
        private readonly CashierShiftService $cashierShiftService,
        private readonly AuditLogService $auditLogService,
        private readonly PricingService $pricingService,
        private readonly LoyaltyService $loyaltyService,
        private readonly PriceListService $priceListService,
        private readonly BatchService $batchService
    ) {}

    /**
     * index
     *
     * @return void
     */
    public function index()
    {
        $userId = auth()->user()->id;
        $activeShift = $this->cashierShiftService->getActiveShiftForUser($userId);
        $warehouseId = $activeShift?->warehouse_id;

        // Get active cart items (not held)
        $carts = Cart::with('product')
            ->where('cashier_id', $userId)
            ->active()
            ->latest()
            ->get();

        $initialPricingPreview = $this->loyaltyService->previewCheckout(
            $this->pricingService->previewCart($carts, null)
        );

        // Get held carts grouped by hold_id
        $heldCarts = Cart::with('product:id,title,sell_price,image')
            ->where('cashier_id', $userId)
            ->held()
            ->get()
            ->groupBy('hold_id')
            ->map(function ($items, $holdId) {
                $first = $items->first();

                return [
                    'hold_id' => $holdId,
                    'label' => $first->hold_label,
                    'held_at' => $first->held_at?->toISOString(),
                    'items_count' => $items->sum('qty'),
                    'total' => $items->sum('price'),
                ];
            })
            ->values();

        // get all customers
        $customers = Customer::latest()->get();

        // get products with stock > 0 in active warehouse
        $products = Product::with(['category:id,name', 'units'])
            ->select('id', 'barcode', 'title', 'description', 'image', 'buy_price', 'sell_price', 'stock', 'category_id')
            ->when($warehouseId, function ($q) use ($warehouseId) {
                $q->whereHas('warehouses', fn ($w) => $w->where('product_warehouse.warehouse_id', $warehouseId)
                    ->where('product_warehouse.stock', '>', 0));
            }, function ($q) {
                $q->where('stock', '>', 0);
            })
            ->orderBy('title')
            ->get();
        $pricingBadges = $this->pricingService->previewProducts($products, null);
        $products = $products->map(function (Product $product) use ($pricingBadges) {
            $pricing = $pricingBadges->get($product->id);

            return [
                ...$product->toArray(),
                'units' => $product->units->map(fn ($u) => [
                    'unit_id' => $u->id,
                    'code' => $u->code,
                    'is_base' => (bool) $u->pivot->is_base,
                    'conversion_factor' => (float) $u->pivot->conversion_factor,
                    'sell_price' => (int) $u->pivot->sell_price,
                    'barcode' => $u->pivot->barcode,
                ]),
                'pricing_badge' => $pricing && ! empty($pricing['pricing_rule']) ? [
                    'label' => $pricing['pricing_rule']['label'],
                    'promo_price' => $pricing['pricing_rule']['price_context']
                        ? $pricing['effective_unit_price']
                        : null,
                    'base_price' => $pricing['base_unit_price'],
                    'kind' => $pricing['pricing_rule']['kind'],
                ] : null,
            ];
        });

        // get all categories
        $categories = Category::select('id', 'name', 'image')
            ->orderBy('name')
            ->get();

        $paymentSetting = PaymentSetting::first();

        $carts_total = 0;
        foreach ($carts as $cart) {
            $carts_total += $cart->price;
        }

        $defaultGateway = $paymentSetting?->default_gateway ?? 'cash';
        if (
            $defaultGateway !== 'cash'
            && (! $paymentSetting || ! $paymentSetting->isGatewayReady($defaultGateway))
        ) {
            $defaultGateway = 'cash';
        }

        // Get active bank accounts for bank transfer
        $bankAccounts = BankAccount::active()->ordered()->get();

        return Inertia::render('Dashboard/Transactions/Index', [
            'carts' => $carts,
            'carts_total' => $carts_total,
            'heldCarts' => $heldCarts,
            'customers' => $customers,
            'products' => $products,
            'categories' => $categories,
            'initialPricingPreview' => $initialPricingPreview,
            'paymentGateways' => $paymentSetting?->enabledGateways() ?? [],
            'defaultPaymentGateway' => $defaultGateway,
            'bankAccounts' => $bankAccounts,
            'shiftSummary' => $this->cashierShiftService->summarizeForDisplay($activeShift),
            'loyaltyTierOptions' => $this->loyaltyService->tierOptions(),
        ]);
    }

    /**
     * searchProduct
     *
     * @param  mixed  $request
     * @return void
     */
    public function searchProduct(Request $request)
    {
        $activeShift = $this->cashierShiftService->getActiveShiftForUser(auth()->user()->id);
        $warehouseId = $activeShift?->warehouse_id;

        $product = Product::where('barcode', $request->barcode)
            ->whereHas('warehouses', fn ($q) => $q->where('product_warehouse.warehouse_id', $warehouseId))
            ->first();

        if ($product) {
            $pivotStock = $product->warehouses()->where('warehouse_id', $warehouseId)->first()?->pivot->stock ?? 0;

            return response()->json([
                'success' => true,
                'data' => [
                    ...$product->toArray(),
                    'stock' => $pivotStock,
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'data' => null,
        ]);
    }

    public function previewPricing(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'discount' => ['nullable', 'integer', 'min:0'],
            'shipping_cost' => ['nullable', 'integer', 'min:0'],
            'redeem_points' => ['nullable', 'integer', 'min:0'],
            'customer_voucher_id' => ['nullable', 'integer', 'exists:customer_vouchers,id'],
        ]);

        $customer = isset($validated['customer_id'])
            ? Customer::find($validated['customer_id'])
            : null;
        $voucher = isset($validated['customer_voucher_id'])
            ? CustomerVoucher::find($validated['customer_voucher_id'])
            : null;

        $carts = Cart::with('product.category')
            ->where('cashier_id', $request->user()->id)
            ->active()
            ->latest()
            ->get();

        $pricingPreview = $this->pricingService->previewCart($carts, $customer);

        return response()->json([
            'success' => true,
            'data' => $this->loyaltyService->previewCheckout($pricingPreview, $customer, [
                'manual_discount' => (int) ($validated['discount'] ?? 0),
                'shipping_cost' => (int) ($validated['shipping_cost'] ?? 0),
                'redeem_points' => (int) ($validated['redeem_points'] ?? 0),
                'voucher' => $voucher,
            ]),
        ]);
    }

    /**
     * addToCart
     *
     * @param  mixed  $request
     * @return void
     */
    public function addToCart(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'qty' => ['required', 'numeric', 'min:0.01'],
            'unit_id' => ['nullable', 'integer'],
        ]);

        $activeShift = $this->cashierShiftService->getActiveShiftForUser(auth()->user()->id);
        $warehouseId = $activeShift?->warehouse_id;

        $product = Product::whereId($validated['product_id'])->first();

        if (! $product) {
            return redirect()->back()->with('error', 'Product not found.');
        }

        // ponytail: composite branch skips unit logic; null unit_id is fine for composite carts
        $unitId = null;

        // Composite: check component stock
        if ($product->is_composite) {
            $product->load('components');
            foreach ($product->components as $component) {
                $needed = (float) $component->pivot->qty * $validated['qty'];
                $whProduct = $component->warehouses()->where('warehouse_id', $warehouseId)->first();
                $avail = $whProduct?->pivot->stock ?? 0;
                if ($avail < $needed) {
                    return redirect()->back()->with('error', "Stok {$component->title} tidak mencukupi.");
                }
            }
            // Composite price = sum component prices
            $sellPrice = (int) $product->components->sum(fn ($c) => $c->sell_price * (float) $c->pivot->qty);
        } else {
            $unitId = (int) (($validated['unit_id'] ?? null) ?: $product->baseUnit()?->id ?: 1);

            // Reject an explicitly provided unit that does not belong to this product
            if (! empty($validated['unit_id'] ?? null) && ! $product->units()->whereKey($unitId)->exists()) {
                return redirect()->back()->with('error', 'Satuan tidak valid untuk produk ini.');
            }

            $unitConversion = app(UnitConversionService::class);
            $baseQty = $unitConversion->toBaseUnit($product, $unitId, $validated['qty']);

            $availableStock = $warehouseId
                ? (int) ($product->warehouses()->where('warehouse_id', $warehouseId)->first()?->pivot->stock ?? 0)
                : (int) $product->stock;

            if ($availableStock < $baseQty) {
                return redirect()->back()->with('error', 'Stok tidak mencukupi.');
            }

            $sellPrice = $unitConversion->getSellPrice($product, $unitId);
            $pu = $product->units()->where('unit_id', $unitId)->first();
            $conversionFactor = $pu?->pivot->conversion_factor ?? 1;
        }
        if (! isset($conversionFactor)) {
            $conversionFactor = 1;
        }

        $cart = Cart::with('product')
            ->where('product_id', $validated['product_id'])
            ->where('cashier_id', auth()->user()->id)
            ->active()
            ->first();

        if ($cart) {
            $cart->increment('qty', $validated['qty']);
            $cart->price = $sellPrice * $cart->qty;
            $cart->save();
        } else {
            Cart::create([
                'cashier_id' => auth()->user()->id,
                'warehouse_id' => $warehouseId,
                'product_id' => $validated['product_id'],
                'unit_id' => $unitId,
                'conversion_factor' => $conversionFactor,
                'qty' => $validated['qty'],
                'price' => $sellPrice * $validated['qty'],
            ]);
        }

        return redirect()->route('transactions.index')->with('success', 'Product Added Successfully!.');
    }

    /**
     * destroyCart
     *
     * @param  mixed  $request
     * @return void
     */
    public function destroyCart($cart_id)
    {
        $cart = Cart::with('product')
            ->whereId($cart_id)
            ->where('cashier_id', auth()->user()->id)
            ->active()
            ->first();

        if ($cart) {
            $cart->delete();

            return back();
        } else {
            // Handle case where no cart is found (e.g., redirect with error message)
            return back()->withErrors(['message' => 'Cart not found']);
        }

    }

    /**
     * updateCart - Update cart item quantity
     *
     * @param  mixed  $request
     * @param  int  $cart_id
     * @return void
     */
    public function updateCart(Request $request, $cart_id)
    {
        $request->validate([
            'qty' => 'required|integer|min:1',
        ]);

        $activeShift = $this->cashierShiftService->getActiveShiftForUser(auth()->user()->id);
        $warehouseId = $activeShift?->warehouse_id;

        $cart = Cart::with('product')->whereId($cart_id)
            ->where('cashier_id', auth()->user()->id)
            ->first();

        if (! $cart) {
            return response()->json([
                'success' => false,
                'message' => 'Cart item not found',
            ], 404);
        }

        $product = $cart->product;

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan',
            ], 404);
        }

        if ($product->is_composite) {
            $product->load('components');
        }

        // Check stock availability (convert display qty to base qty for the cart's unit)
        $unitConversion = app(UnitConversionService::class);
        $baseQty = $product->is_composite
            ? (int) $request->qty
            : $unitConversion->toBaseUnit($product, (int) $cart->unit_id, $request->qty);

        $availableStock = $warehouseId
            ? (int) ($product->warehouses()->where('warehouse_id', $warehouseId)->first()?->pivot->stock ?? 0)
            : (int) $product->stock;

        if ($availableStock < $baseQty) {
            return response()->json([
                'success' => false,
                'message' => 'Stok tidak mencukupi. Tersedia: '.$availableStock,
            ], 422);
        }

        // Update quantity and price (derive price from DB, not from request/global sell_price)
        $cart->qty = $request->qty;
        $cart->price = $product->is_composite
            ? (int) $product->components->sum(fn ($c) => $c->sell_price * (float) $c->pivot->qty) * $request->qty
            : $unitConversion->getSellPrice($product, (int) $cart->unit_id) * $request->qty;
        $cart->save();

        return back()->with('success', 'Quantity updated successfully');
    }

    /**
     * holdCart - Hold current cart items for later
     *
     * @return JsonResponse
     */
    public function holdCart(Request $request)
    {
        $request->validate([
            'label' => 'nullable|string|max:50',
        ]);

        $userId = auth()->user()->id;

        // Get active cart items
        $activeCarts = Cart::where('cashier_id', $userId)
            ->active()
            ->get();

        if ($activeCarts->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Keranjang kosong, tidak ada yang bisa ditahan',
            ], 422);
        }

        // Generate unique hold ID
        $holdId = 'HOLD-'.strtoupper(uniqid());
        $label = $request->label ?: 'Transaksi '.now()->format('H:i');

        // Mark all active cart items as held
        Cart::where('cashier_id', $userId)
            ->active()
            ->update([
                'hold_id' => $holdId,
                'hold_label' => $label,
                'held_at' => now(),
            ]);

        return back()->with('success', 'Transaksi ditahan: '.$label);
    }

    /**
     * resumeCart - Resume a held cart
     *
     * @param  string  $holdId
     * @return JsonResponse
     */
    public function resumeCart($holdId)
    {
        $userId = auth()->user()->id;

        // Check if there are any active carts (not held)
        $activeCarts = Cart::where('cashier_id', $userId)
            ->active()
            ->count();

        if ($activeCarts > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Selesaikan atau tahan transaksi aktif terlebih dahulu',
            ], 422);
        }

        // Get held carts
        $heldCarts = Cart::where('cashier_id', $userId)
            ->forHold($holdId)
            ->get();

        if ($heldCarts->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Transaksi ditahan tidak ditemukan',
            ], 404);
        }

        // Resume by clearing hold info
        Cart::where('cashier_id', $userId)
            ->forHold($holdId)
            ->update([
                'hold_id' => null,
                'hold_label' => null,
                'held_at' => null,
            ]);

        return back()->with('success', 'Transaksi dilanjutkan');
    }

    /**
     * clearHold - Delete a held cart
     *
     * @param  string  $holdId
     * @return JsonResponse
     */
    public function clearHold($holdId)
    {
        $userId = auth()->user()->id;

        $deleted = Cart::where('cashier_id', $userId)
            ->forHold($holdId)
            ->delete();

        if ($deleted === 0) {
            return request()->wantsJson()
                ? response()->json([
                    'success' => false,
                    'message' => 'Transaksi ditahan tidak ditemukan',
                ], 404)
                : back()->with('error', 'Transaksi ditahan tidak ditemukan');
        }

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Transaksi ditahan berhasil dihapus',
            ]);
        }

        return back()->with('success', 'Transaksi ditahan berhasil dihapus');
    }

    /**
     * getHeldCarts - Get all held carts for current user
     *
     * @return JsonResponse
     */
    public function getHeldCarts()
    {
        $userId = auth()->user()->id;

        $heldCarts = Cart::with('product:id,title,sell_price,image')
            ->where('cashier_id', $userId)
            ->held()
            ->get()
            ->groupBy('hold_id')
            ->map(function ($items, $holdId) {
                $first = $items->first();

                return [
                    'hold_id' => $holdId,
                    'label' => $first->hold_label,
                    'held_at' => $first->held_at,
                    'items_count' => $items->sum('qty'),
                    'total' => $items->sum('price'),
                    'items' => $items->map(fn ($item) => [
                        'id' => $item->id,
                        'product' => $item->product,
                        'qty' => $item->qty,
                        'price' => $item->price,
                    ]),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'held_carts' => $heldCarts,
        ]);
    }

    /**
     * store
     *
     * @param  mixed  $request
     * @return void
     */
    public function store(Request $request, PaymentGatewayManager $paymentGatewayManager)
    {
        $isPayLater = $request->boolean('pay_later');
        $paymentGateway = $isPayLater ? null : $request->input('payment_gateway');
        if ($paymentGateway) {
            $paymentGateway = strtolower($paymentGateway);
        }
        $paymentSetting = null;

        if ($isPayLater && ! $request->filled('due_date')) {
            return redirect()
                ->route('transactions.index')
                ->with('error', 'Tanggal jatuh tempo wajib diisi untuk nota barang.');
        }

        if ($paymentGateway) {
            $paymentSetting = PaymentSetting::first();

            if (! $paymentSetting || ! $paymentSetting->isGatewayReady($paymentGateway)) {
                return redirect()
                    ->route('transactions.index')
                    ->with('error', 'Gateway pembayaran belum dikonfigurasi.');
            }
        }

        $invoice = 'TRX-'.Str::upper(Str::random(10));
        $isCashPayment = empty($paymentGateway) && ! $isPayLater;
        $manualDiscount = max(0, (int) $request->input('discount', 0));
        $shippingCost = max(0, (int) $request->input('shipping_cost', 0));
        $requestedRedeemPoints = max(0, (int) $request->input('redeem_points', 0));
        $cashAmount = $isCashPayment ? max(0, (int) $request->cash) : 0;
        $customer = $request->filled('customer_id')
            ? Customer::find($request->integer('customer_id'))
            : null;
        $voucher = $request->filled('customer_voucher_id')
            ? CustomerVoucher::find($request->integer('customer_voucher_id'))
            : null;

        $transaction = DB::transaction(function () use (
            $request,
            $invoice,
            $cashAmount,
            $paymentGateway,
            $isCashPayment,
            $isPayLater,
            $manualDiscount,
            $shippingCost,
            $requestedRedeemPoints,
            $customer,
            $voucher
        ) {
            $activeShift = $this->cashierShiftService->requireActiveShiftForUser(
                auth()->user()->id,
                lockForUpdate: true
            );

            $carts = Cart::with('product')
                ->where('cashier_id', auth()->user()->id)
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

            if ($isCashPayment && $cashAmount < $grandTotal) {
                throw ValidationException::withMessages([
                    'cash' => 'Uang tunai kurang dari total belanja.',
                ]);
            }

            $transaction = Transaction::create([
                'cashier_id' => auth()->user()->id,
                'cashier_shift_id' => $activeShift->id,
                'warehouse_id' => $activeShift->warehouse_id,
                'customer_id' => $request->customer_id,
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
                'bank_account_id' => $paymentGateway === 'bank_transfer' ? $request->bank_account_id : null,
                'tax_rate' => data_get($checkoutPreview, 'summary.tax_rate'),
                'tax_total' => data_get($checkoutPreview, 'summary.tax_total', 0),
                'customer_npwp' => $request->customer_npwp,
                'price_list_id' => $this->priceListService->getApplicablePriceList($customer)?->id,
            ]);

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

                $total_buy_price = $cart->product->buy_price * $cart->qty;
                $lineShare = $subtotalAfterPromo > 0 ? $lineTotal / $subtotalAfterPromo : 0;
                $allocatedManualDiscount = (int) round($appliedManualDiscount * $lineShare);
                $netSellPrice = max(0, $lineTotal - $allocatedManualDiscount);
                $profits = $netSellPrice - $total_buy_price;

                $transaction->profits()->create([
                    'transaction_id' => $transaction->id,
                    'total' => $profits,
                ]);

                $product = Product::find($cart->product_id);
                $warehouseId = $activeShift->warehouse_id;

                if ($product->is_composite) {
                    $product->load('components');
                    foreach ($product->components as $component) {
                        $componentQty = (int) round((float) $component->pivot->qty * $cart->qty);
                        // ponytail: lock pivot row, re-check stock inside the transaction to prevent overselling
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

                    // ponytail: lock pivot row and re-check stock at checkout time (cart add only checks once)
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

                    // FEFO: consume non-expired batch stock (locked). Products with partial batch coverage are rejected.
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
                                throw ValidationException::withMessages(['stock' => "Stok batch {$product->title} tidak mencukupi. Tersedia: {$covered}."]);
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
                                // record every batch consumed so multi-batch lines are fully traceable
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

            Cart::where('cashier_id', auth()->user()->id)->active()->delete();

            $this->loyaltyService->finalizeTransaction($transaction, $customer, $checkoutPreview);

            if ($isPayLater) {
                Receivable::create([
                    'customer_id' => $request->customer_id,
                    'transaction_id' => $transaction->id,
                    'invoice' => $invoice,
                    'total' => $grandTotal,
                    'paid' => 0,
                    'due_date' => $request->due_date,
                    'status' => 'unpaid',
                ]);
            }

            return $transaction->fresh(['customer']);
        });

        // Check if discount needs approval
        if ($transaction->discount > 0 && $transaction->needsDiscountApproval()) {
            $transaction->update([
                'discount_approval_status' => 'pending',
                'payment_status' => 'pending_approval',
            ]);

            DiscountApprovalLog::create([
                'transaction_id' => $transaction->id,
                'cashier_id' => auth()->id(),
                'requested_discount' => $appliedManualDiscount,
                'status' => 'pending',
            ]);

            return redirect()
                ->route('transactions.print', $transaction->invoice)
                ->with('info', 'Transaksi menunggu approval supervisor.');
        }

        if ($paymentGateway) {
            try {
                $paymentResponse = $paymentGatewayManager->createPayment($transaction, $paymentGateway, $paymentSetting);

                $transaction->update([
                    'payment_reference' => $paymentResponse['reference'] ?? null,
                    'payment_url' => $paymentResponse['payment_url'] ?? null,
                ]);
            } catch (PaymentGatewayException $exception) {
                return redirect()
                    ->route('transactions.print', $transaction->invoice)
                    ->with('error', $exception->getMessage());
            }
        }

        return to_route('transactions.print', $transaction->invoice);
    }

    public function print($invoice)
    {
        // get transaction
        $transaction = Transaction::with('details.product', 'details.pricingRule', 'cashier', 'customer', 'receivable', 'bankAccount')
            ->where('invoice', $invoice)
            ->firstOrFail();

        return Inertia::render('Dashboard/Transactions/Print', [
            'transaction' => $transaction,
        ]);
    }

    /**
     * Display transaction history.
     */
    public function history(Request $request)
    {
        $salesReturnTablesReady = Schema::hasTable('sales_returns') && Schema::hasTable('sales_return_items');

        $filters = [
            'invoice' => $request->input('invoice'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'warehouse_id' => $request->input('warehouse_id'),
        ];

        $query = Transaction::query()
            ->with([
                'cashier:id,name',
                'warehouse:id,code,name',
                'cashierShift:id,opened_at,status',
                'customer:id,name',
                'receivable',
            ])
            ->withSum('details as total_items', 'qty')
            ->withSum('profits as total_profit', 'total')
            ->orderByDesc('created_at');

        if ($salesReturnTablesReady) {
            $query->with('details.salesReturnItems.salesReturn:id,status');
        }

        if (! $request->user()->isSuperAdmin()) {
            $query->where('cashier_id', $request->user()->id);
        }

        $query
            ->when($filters['invoice'], function (Builder $builder, $invoice) {
                $builder->where('invoice', 'like', '%'.$invoice.'%');
            })
            ->when($filters['start_date'], function (Builder $builder, $date) {
                $builder->whereDate('created_at', '>=', $date);
            })
            ->when($filters['end_date'], function (Builder $builder, $date) {
                $builder->whereDate('created_at', '<=', $date);
            })
            ->when($filters['warehouse_id'], function (Builder $builder, $warehouseId) {
                $builder->where('warehouse_id', $warehouseId);
            });

        $transactions = $query->paginate($this->perPage())->withQueryString();
        $warehouses = Warehouse::active()->orderBy('code')->get(['id', 'code', 'name']);
        $transactions->through(function (Transaction $transaction) use ($salesReturnTablesReady) {
            $canCreateSalesReturn = false;

            if ($salesReturnTablesReady) {
                $allReturned = true;

                foreach ($transaction->details as $detail) {
                    $returnedQty = (int) $detail->salesReturnItems
                        ->filter(fn ($item) => $item->salesReturn?->status === 'completed')
                        ->sum('qty_return');

                    if ($returnedQty < (int) $detail->qty) {
                        $allReturned = false;
                        break;
                    }
                }

                $canCreateSalesReturn = $transaction->details->isNotEmpty() && ! $allReturned;
            }

            return [
                ...$transaction->toArray(),
                'can_create_sales_return' => $canCreateSalesReturn,
            ];
        });

        return Inertia::render('Dashboard/Transactions/History', [
            'transactions' => $transactions,
            'filters' => $filters,
            'salesReturnFeatureReady' => $salesReturnTablesReady,
            'warehouses' => $warehouses,
        ]);
    }

    /**
     * Confirm payment for bank transfer transactions
     */
    public function confirmPayment(Transaction $transaction)
    {
        if ($transaction->payment_status === 'paid') {
            return redirect()
                ->back()
                ->with('error', 'Transaksi sudah dibayar.');
        }

        if ($transaction->payment_method !== 'bank_transfer') {
            return redirect()
                ->back()
                ->with('error', 'Hanya transaksi transfer bank yang dapat dikonfirmasi pembayarannya.');
        }

        if (! $transaction->bank_account_id) {
            return redirect()
                ->back()
                ->with('error', 'Transaksi transfer bank belum memiliki rekening tujuan.');
        }

        if (! in_array($transaction->payment_status, ['pending', 'pending_approval'], true)) {
            return redirect()
                ->back()
                ->with('error', 'Status transaksi tidak dapat dikonfirmasi saat ini.');
        }

        $beforeStatus = $transaction->payment_status;
        $transaction->update([
            'payment_status' => 'paid',
        ]);

        $this->auditLogService->log(
            event: 'transaction.payment_confirmed',
            module: 'transactions',
            auditable: $transaction,
            description: "Pembayaran untuk invoice {$transaction->invoice} dikonfirmasi.",
            before: [
                'invoice' => $transaction->invoice,
                'payment_method' => $transaction->payment_method,
                'payment_status' => $beforeStatus,
                'bank_account_id' => $transaction->bank_account_id,
            ],
            after: [
                'invoice' => $transaction->invoice,
                'payment_method' => $transaction->payment_method,
                'payment_status' => 'paid',
                'bank_account_id' => $transaction->bank_account_id,
            ],
            meta: [
                'invoice' => $transaction->invoice,
                'bank_account_id' => $transaction->bank_account_id,
            ],
        );

        return redirect()
            ->back()
            ->with('success', "Pembayaran untuk invoice {$transaction->invoice} berhasil dikonfirmasi.");
    }
}
