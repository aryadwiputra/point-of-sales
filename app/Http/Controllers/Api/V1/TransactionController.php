<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Apps\TransactionController as AppsTransactionController;
use App\Http\Traits\ApiResponder;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\CustomerVoucher;
use App\Models\Outlet;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TransactionController extends AppsTransactionController
{
    use ApiResponder;

    /**
     * GET /api/v1/transactions
     * Paginated transaction history with filters.
     */
    public function listTransactions(Request $request): JsonResponse
    {
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

        $warehouseIds = $this->outletAccessService->warehousesFor($request->user())->pluck('id');
        $query->where(function (Builder $builder) use ($warehouseIds) {
            $builder->whereIn('warehouse_id', $warehouseIds);

            if (Outlet::active()->count() <= 1) {
                $builder->orWhereNull('warehouse_id');
            }
        });

        if (! $request->user()->isSuperAdmin()) {
            $query->where('cashier_id', $request->user()->id);
        }

        $query
            ->when($filters['invoice'], fn (Builder $builder, $invoice) => $builder->where('invoice', 'like', '%'.$invoice.'%'))
            ->when($filters['start_date'], fn (Builder $builder, $date) => $builder->whereDate('created_at', '>=', $date))
            ->when($filters['end_date'], fn (Builder $builder, $date) => $builder->whereDate('created_at', '<=', $date))
            ->when($filters['warehouse_id'], fn (Builder $builder, $warehouseId) => $builder->where('warehouse_id', $warehouseId));

        $transactions = $query->paginate($this->perPage())->withQueryString();

        return $this->paginated($transactions);
    }

    /**
     * GET /api/v1/transactions/{invoice}
     * Transaction detail with items.
     */
    public function show(Request $request, string $invoice): JsonResponse
    {
        $transaction = Transaction::with(
            'details.product',
            'details.pricingRule',
            'cashier',
            'customer',
            'warehouse',
            'receivable',
            'bankAccount',
            'tenders',
        )->where('invoice', $invoice)->first();

        if (! $transaction) {
            return $this->notFound('Transaksi tidak ditemukan.');
        }

        $this->ensureTransactionAccess($request, $transaction);

        return $this->ok($transaction);
    }

    /**
     * GET /api/v1/transactions/{invoice}/status
     * Payment status only (lightweight polling endpoint).
     */
    public function status(Request $request, $invoice): JsonResponse
    {
        $transaction = Transaction::where('invoice', $invoice)->first();

        if (! $transaction) {
            return $this->notFound('Transaksi tidak ditemukan.');
        }

        $this->ensureTransactionAccess($request, $transaction);

        return $this->ok([
            'payment_status' => $transaction->payment_status,
            'payment_method' => $transaction->payment_method,
        ]);
    }

    /**
     * GET /api/v1/transactions/{invoice}/qr
     * QRIS code as SVG image payload.
     */
    public function qrImage(Request $request, string $invoice): JsonResponse
    {
        $transaction = Transaction::where('invoice', $invoice)->first();

        if (! $transaction) {
            return $this->notFound('Transaksi tidak ditemukan.');
        }

        $this->ensureTransactionAccess($request, $transaction);

        if (! filled($transaction->qr_string)) {
            return $this->notFound('Transaksi ini tidak memiliki QR code.');
        }

        return $this->ok([
            'qr_string' => $transaction->qr_string,
            'qr_svg' => base64_encode(
                QrCode::format('svg')->size(300)->margin(2)->generate($transaction->qr_string)
            ),
        ]);
    }

    /**
     * POST /api/v1/transactions/pricing-preview
     * Preview pricing/loyalty totals for the active cart.
     */
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
        $activeShift = $this->cashierShiftService->getActiveShiftForUser($request->user()->id);
        $activeShift?->loadMissing('warehouse.outlet');

        $carts = Cart::with('product.category')
            ->where('cashier_id', $request->user()->id)
            ->active()
            ->latest()
            ->get();

        $pricingPreview = $this->pricingService->previewCart(
            $carts,
            $customer,
            null,
            $activeShift?->warehouse?->outlet
        );

        return $this->ok($this->loyaltyService->previewCheckout($pricingPreview, $customer, [
            'manual_discount' => (int) ($validated['discount'] ?? 0),
            'shipping_cost' => (int) ($validated['shipping_cost'] ?? 0),
            'redeem_points' => (int) ($validated['redeem_points'] ?? 0),
            'voucher' => $voucher,
        ], null, $activeShift?->warehouse?->outlet));
    }

    /**
     * POST /api/v1/transactions/{transaction}/confirm-payment
     * Confirm a pending bank transfer payment. Requires step-up.
     */
    public function confirmPayment(Request $request, Transaction $transaction): JsonResponse
    {
        $this->ensureTransactionAccess($request, $transaction);

        if ($transaction->payment_status === 'paid') {
            return $this->error('Transaksi sudah dibayar.', 422);
        }

        if ($transaction->payment_method !== 'bank_transfer') {
            return $this->error('Hanya transaksi transfer bank yang dapat dikonfirmasi pembayarannya.', 422);
        }

        if (! $transaction->bank_account_id) {
            return $this->error('Transaksi transfer bank belum memiliki rekening tujuan.', 422);
        }

        if (! in_array($transaction->payment_status, ['pending', 'pending_approval'], true)) {
            return $this->error('Status transaksi tidak dapat dikonfirmasi saat ini.', 422);
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

        return $this->ok($transaction->fresh(), "Pembayaran untuk invoice {$transaction->invoice} berhasil dikonfirmasi.");
    }
}
