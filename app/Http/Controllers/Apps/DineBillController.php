<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerVoucher;
use App\Models\DineBill;
use App\Models\DineOrderItem;
use App\Services\DineBillService;
use App\Services\DineBillSettlementService;
use App\Services\DineStaffOrderService;
use App\Services\OutletAccessService;
use App\Support\Dine\DineSettlementContext;
use Illuminate\Http\Request;

class DineBillController extends Controller
{
    public function __construct(
        protected readonly DineStaffOrderService $staffOrders,
        protected readonly DineBillService $bills,
        protected readonly DineBillSettlementService $settlements,
        protected readonly OutletAccessService $outletAccess,
    ) {}

    public function submitCart(Request $request, DineBill $dineBill)
    {
        $this->ensureBillOutlet($request, $dineBill);
        $this->staffOrders->submit($request->user()->id, $dineBill);

        return redirect()
            ->route('dine-tables.index')
            ->with('success', 'Pesanan dikirim ke meja.');
    }

    public function voidItem(Request $request, DineBill $dineBill, DineOrderItem $dineOrderItem)
    {
        $this->ensureBillOutlet($request, $dineBill);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $shift = $request->user()->cashierShifts()->open()->latest('opened_at')->first();

        if (! $shift) {
            return back()->with('error', 'Shift kasir belum dibuka.');
        }

        $this->bills->voidItem(
            $dineBill,
            $dineOrderItem,
            $validated['reason'],
            $request->user()->id,
            $shift->warehouse_id,
        );

        return back()->with('success', 'Item berhasil di-void.');
    }

    public function split(Request $request, DineBill $dineBill)
    {
        $this->ensureBillOutlet($request, $dineBill);
        $validated = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer'],
        ]);

        $this->bills->split($dineBill, $validated['item_ids'], $request->user()->id);

        return back()->with('success', 'Bill berhasil dipisah.');
    }

    public function settle(Request $request, DineBill $dineBill)
    {
        $this->ensureBillOutlet($request, $dineBill);
        $validated = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_voucher_id' => ['nullable', 'integer', 'exists:customer_vouchers,id'],
            'discount' => ['nullable', 'integer', 'min:0'],
            'redeem_points' => ['nullable', 'integer', 'min:0'],
            'pay_later' => ['boolean'],
            'due_date' => ['nullable', 'date'],
            'cash' => ['nullable', 'integer', 'min:0'],
            'payment_gateway' => ['nullable', 'string', 'max:30'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'tenders' => ['nullable', 'array', 'max:2'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $isPayLater = $request->boolean('pay_later');

        if ($isPayLater && empty($validated['customer_id'])) {
            return back()->with('error', 'Nota barang memerlukan pelanggan.');
        }

        if ($isPayLater && empty($validated['due_date'])) {
            return back()->with('error', 'Tanggal jatuh tempo wajib diisi untuk nota barang.');
        }

        $tenders = is_array($validated['tenders'] ?? null) ? $validated['tenders'] : [];
        $useTenders = ! $isPayLater && $tenders !== [];
        $gateway = $isPayLater ? null : ($validated['payment_gateway'] ?? null);
        $isCash = empty($gateway) && ! $isPayLater && ! $useTenders;

        $context = new DineSettlementContext(
            userId: $request->user()->id,
            customer: ! empty($validated['customer_id']) ? Customer::find($validated['customer_id']) : null,
            voucher: ! empty($validated['customer_voucher_id'])
                ? CustomerVoucher::find($validated['customer_voucher_id'])
                : null,
            manualDiscount: max(0, (int) ($validated['discount'] ?? 0)),
            requestedRedeemPoints: max(0, (int) ($validated['redeem_points'] ?? 0)),
            isPayLater: $isPayLater,
            dueDate: $validated['due_date'] ?? null,
            isCashPayment: $isCash,
            cashAmount: $isCash ? max(0, (int) ($validated['cash'] ?? 0)) : 0,
            paymentGateway: $gateway,
            useTenders: $useTenders,
            tenderInput: $tenders,
            bankAccountId: $validated['bank_account_id'] ?? null,
            note: $validated['note'] ?? null,
        );

        $result = $this->settlements->settle($dineBill, $context);

        if ($result->needsDiscountApproval) {
            return back()->with('info', 'Bill menunggu approval supervisor.');
        }

        return redirect()
            ->route('transactions.print', $result->transaction->invoice)
            ->with('success', 'Bill berhasil dibayar.');
    }

    protected function ensureBillOutlet(Request $request, DineBill $bill): void
    {
        $bill->loadMissing('session.table.area');
        $outlet = $this->outletAccess->activeOutlet($request);
        $outletId = $bill->session?->table?->area?->outlet_id;

        abort_if($outletId !== null && $outlet && (int) $outletId !== (int) $outlet->id, 404);
    }
}
