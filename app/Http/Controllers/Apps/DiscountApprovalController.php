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
            $transaction->update([
                'discount_approval_status' => $status,
                'discount_approved_by' => auth()->id(),
                'discount_approved_at' => now(),
                'payment_status' => $status === 'approved' ? 'paid' : 'paid',
            ]);

            if ($status === 'denied') {
                $transaction->decrement('grand_total', $transaction->discount ?? 0);
                $transaction->update(['discount' => 0]);
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
            event: 'discount_approval.' . $status,
            module: 'transactions',
            auditable: $transaction,
            description: "Diskon transaksi {$transaction->invoice} di" . ($status === 'approved' ? 'setujui' : 'tolak'),
            after: ['discount_approval_status' => $status],
        );
    }
}
