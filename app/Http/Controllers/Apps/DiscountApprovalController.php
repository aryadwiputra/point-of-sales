<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\DineBill;
use App\Models\DiscountApprovalLog;
use App\Models\Outlet;
use App\Models\Transaction;
use App\Services\AuditLogService;
use App\Services\DineBillService;
use App\Services\DineSessionService;
use App\Services\OutletAccessService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DiscountApprovalController extends Controller
{
    public function __construct(
        protected readonly AuditLogService $auditLogService,
        protected readonly OutletAccessService $outletAccessService,
        protected readonly DineBillService $billService,
        protected readonly DineSessionService $sessionService,
    ) {}

    public function pending()
    {
        $warehouseIds = $this->outletAccessService->warehousesFor(request()->user())->pluck('id');
        $includeLegacy = Outlet::active()->count() <= 1;

        $pending = Transaction::where('discount_approval_status', 'pending')
            ->where(function ($query) use ($warehouseIds, $includeLegacy) {
                if ($warehouseIds->isEmpty()) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereIn('warehouse_id', $warehouseIds);
                    if ($includeLegacy) {
                        $query->orWhereNull('warehouse_id');
                    }
                }
            })
            ->with(['cashier:id,name', 'customer:id,name', 'cashierShift:id,opened_at'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'invoice' => $t->invoice,
                'cashier' => $t->cashier?->name,
                'customer' => $t->customer?->name ?? 'Umum',
                'discount' => (int) $t->discount,
                'grand_total' => (int) $t->grand_total,
                'created_at' => $t->created_at?->toISOString(),
            ]);

        return Inertia::render('Dashboard/DiscountApprovals', [
            'pendingTransactions' => $pending,
        ]);
    }

    public function approve(Transaction $transaction)
    {
        abort_if($transaction->discount_approval_status !== 'pending', 404);
        $this->ensureOutletAccess($transaction);

        $this->logAndUpdate($transaction, 'approved');

        return back()->with('success', 'Diskon disetujui.');
    }

    public function deny(Request $request, Transaction $transaction)
    {
        abort_if($transaction->discount_approval_status !== 'pending', 404);
        $this->ensureOutletAccess($transaction);

        $this->logAndUpdate($transaction, 'denied', $request->notes);

        return back()->with('success', 'Diskon ditolak.');
    }

    protected function ensureOutletAccess(Transaction $transaction): void
    {
        abort_unless(
            $this->outletAccessService->canUseWarehouse(request()->user(), $transaction->warehouse),
            404
        );
    }

    protected function logAndUpdate(Transaction $transaction, string $status, ?string $notes = null): void
    {
        $bill = DineBill::where('transaction_id', $transaction->id)->first();

        \DB::transaction(function () use ($transaction, $status, $notes, $bill) {
            $paymentStatus = match ($transaction->payment_method) {
                'cash' => 'paid',
                'pay_later' => 'unpaid',
                default => 'pending',
            };

            $updates = [
                'discount_approval_status' => $status,
                'discount_approved_by' => auth()->id(),
                'discount_approved_at' => now(),
            ];

            $deniedDiscount = (int) $transaction->discount;

            if ($status === 'denied') {
                $updates['discount'] = 0;
                $updates['payment_status'] = 'unpaid';
            } else {
                $updates['payment_status'] = $paymentStatus;
            }

            $transaction->update($updates);

            if ($status === 'denied' && $deniedDiscount > 0) {
                $transaction->increment('grand_total', $deniedDiscount);
            }

            DiscountApprovalLog::where('transaction_id', $transaction->id)
                ->where('status', 'pending')
                ->update([
                    'status' => $status,
                    'responded_by' => auth()->id(),
                    'responded_at' => now(),
                    'notes' => $notes,
                ]);

            if (! $bill) {
                return;
            }

            if ($status === 'approved') {
                $bill->update([
                    'status' => DineBill::STATUS_SETTLED,
                    'settled_at' => now(),
                ]);
                $this->billService->completeOrdersForBill($bill->fresh());
                $this->sessionService->closeIfIdle($bill->session);
            } else {
                $bill->update([
                    'status' => DineBill::STATUS_OPEN,
                    'settled_at' => null,
                    'transaction_id' => null,
                    'discount_total' => 0,
                    'tax_total' => 0,
                    'grand_total' => 0,
                ]);
                $this->billService->recomputeTotals($bill->fresh());
            }
        });

        $this->auditLogService->log(
            event: 'discount_approval.'.$status,
            module: 'transactions',
            auditable: $transaction,
            description: "Diskon transaksi {$transaction->invoice} di".($status === 'approved' ? 'setujui' : 'tolak'),
            after: ['discount_approval_status' => $status],
        );
    }
}
