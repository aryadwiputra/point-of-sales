<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Apps\DiscountApprovalController as AppsDiscountApprovalController;
use App\Http\Traits\ApiResponder;
use App\Models\Outlet;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscountApprovalController extends AppsDiscountApprovalController
{
    use ApiResponder;

    /**
     * GET /api/v1/discount-approvals
     * Pending discount approvals for the current user's outlets.
     */
    public function listPending(Request $request): JsonResponse
    {
        $warehouseIds = $this->outletAccessService->warehousesFor($request->user())->pluck('id');
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

        return $this->ok([
            'pending' => $pending->all(),
        ]);
    }

    /**
     * POST /api/v1/discount-approvals/{transaction}/approve
     * Approve the discount on a pending transaction.
     */
    public function approve(Transaction $transaction): JsonResponse
    {
        abort_if($transaction->discount_approval_status !== 'pending', 404);
        $this->ensureOutletAccess($transaction);

        $this->logAndUpdate($transaction, 'approved');

        return $this->ok($transaction->fresh(), 'Diskon disetujui.');
    }

    /**
     * POST /api/v1/discount-approvals/{transaction}/deny
     * Deny the discount on a pending transaction; discount is re-added to grand total.
     */
    public function deny(Request $request, Transaction $transaction): JsonResponse
    {
        abort_if($transaction->discount_approval_status !== 'pending', 404);
        $this->ensureOutletAccess($transaction);

        $this->logAndUpdate($transaction, 'denied', $request->votes);

        return $this->ok($transaction->fresh(), 'Diskon ditolak.');
    }
}
