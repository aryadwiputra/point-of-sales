<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\DiscountApprovalLog;
use App\Models\Transaction;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DiscountApprovalController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {}

    public function pending()
    {
        $pending = Transaction::where('discount_approval_status', 'pending')
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

        $this->logAndUpdate($transaction, 'approved');

        return back()->with('success', 'Diskon disetujui.');
    }

    public function deny(Request $request, Transaction $transaction)
    {
        abort_if($transaction->discount_approval_status !== 'pending', 404);

        $this->logAndUpdate($transaction, 'denied', $request->notes);

        return back()->with('success', 'Diskon ditolak.');
    }

    private function logAndUpdate(Transaction $transaction, string $status, ?string $notes = null): void
    {
        \DB::transaction(function () use ($transaction, $status, $notes) {
            // ponytail: approval only settles the approval state — payment stays tied to the actual method.
            // grand_total already excludes the manual discount (LoyaltyService), so denying re-adds it.
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
