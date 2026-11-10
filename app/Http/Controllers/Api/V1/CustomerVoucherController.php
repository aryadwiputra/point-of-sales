<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Apps\CustomerVoucherController as AppsCustomerVoucherController;
use App\Http\Traits\ApiResponder;
use App\Models\Customer;
use App\Models\CustomerVoucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerVoucherController extends AppsCustomerVoucherController
{
    use ApiResponder;

    /**
     * GET /api/v1/customer-vouchers
     */
    public function listVouchers(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
        ];

        $outlet = $this->outletAccessService->activeOutlet($request);
        $vouchers = CustomerVoucher::query()
            ->where(function ($query) use ($outlet) {
                $query->whereNull('outlet_id');
                if ($outlet) {
                    $query->orWhere('outlet_id', $outlet->id);
                }
            })
            ->with(['customer:id,name,no_telp', 'creator:id,name'])
            ->when($filters['search'], function ($query, $search) {
                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('code', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%')
                        ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($filters['status'], function ($query, $status) {
                match ($status) {
                    'active' => $query->where('is_active', true)->where('is_used', false),
                    'scheduled' => $query->where('is_active', true)->where('is_used', false)->whereNotNull('starts_at')->where('starts_at', '>', now()),
                    'expired' => $query->whereNotNull('expires_at')->where('expires_at', '<', now()),
                    'used' => $query->where('is_used', true),
                    'inactive' => $query->where('is_active', false),
                    default => null,
                };
            })
            ->latest()
            ->paginate($this->perPage())
            ->withQueryString();

        return $this->paginated($vouchers);
    }

    /**
     * GET /api/v1/customer-vouchers/create
     */
    public function createData(): JsonResponse
    {
        return $this->ok([
            'customers' => Customer::orderBy('name')->get([
                'id', 'name', 'no_telp', 'is_loyalty_member', 'loyalty_tier', 'loyalty_points',
            ]),
            'discount_types' => [
                ['value' => CustomerVoucher::TYPE_FIXED_AMOUNT, 'label' => 'Nominal'],
                ['value' => CustomerVoucher::TYPE_PERCENTAGE, 'label' => 'Persentase'],
            ],
        ]);
    }

    /**
     * POST /api/v1/customer-vouchers
     */
    public function storeVoucher(Request $request): JsonResponse
    {
        $validated = $this->validateVoucher($request);

        $voucher = CustomerVoucher::create([
            ...$validated,
            'outlet_id' => $this->outletAccessService->activeOutlet($request)?->id,
            'created_by' => $request->user()?->id,
            'code' => empty($validated['code']) ? $this->generateVoucherCode() : $validated['code'],
        ]);

        $this->auditLogService->log(
            event: 'customer_voucher.created',
            module: 'customer_vouchers',
            auditable: $voucher,
            description: 'Voucher customer dibuat.',
            after: $this->auditPayload($voucher->fresh('customer'))
        );

        return $this->created($voucher->fresh('customer'));
    }

    /**
     * GET /api/v1/customer-vouchers/{customerVoucher}
     */
    public function showVoucher(CustomerVoucher $customerVoucher): JsonResponse
    {
        abort_unless($this->canUseVoucher($customerVoucher), 404);

        return $this->ok($customerVoucher->load('customer:id,name,no_telp', 'creator:id,name'));
    }

    /**
     * PUT /api/v1/customer-vouchers/{customerVoucher}
     */
    public function updateVoucher(Request $request, CustomerVoucher $customerVoucher): JsonResponse
    {
        abort_unless($this->canUseVoucher($customerVoucher), 404);

        $before = $this->auditPayload($customerVoucher);
        $validated = $this->validateVoucher($request, $customerVoucher);

        $customerVoucher->update([
            ...$validated,
            'code' => $validated['code'] ?: $customerVoucher->code,
        ]);

        $this->auditLogService->log(
            event: 'customer_voucher.updated',
            module: 'customer_vouchers',
            auditable: $customerVoucher,
            description: 'Voucher customer diperbarui.',
            before: $before,
            after: $this->auditPayload($customerVoucher->fresh('customer'))
        );

        return $this->ok($customerVoucher->fresh('customer'));
    }

    /**
     * DELETE /api/v1/customer-vouchers/{customerVoucher}
     */
    public function destroyVoucher(CustomerVoucher $customerVoucher): JsonResponse
    {
        abort_unless($this->canUseVoucher($customerVoucher), 404);

        $before = $this->auditPayload($customerVoucher);
        $customerVoucher->delete();

        $this->auditLogService->log(
            event: 'customer_voucher.deleted',
            module: 'customer_vouchers',
            auditable: $customerVoucher,
            description: 'Voucher customer dihapus.',
            before: $before
        );

        return $this->noContent();
    }
}
