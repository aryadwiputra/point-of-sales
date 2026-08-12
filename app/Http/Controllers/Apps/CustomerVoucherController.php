<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerVoucher;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CustomerVoucherController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {}

    public function index(Request $request)
    {
        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
        ];

        $vouchers = CustomerVoucher::query()
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

        return Inertia::render('Dashboard/CustomerVouchers/Index', [
            'vouchers' => $vouchers,
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        return Inertia::render('Dashboard/CustomerVouchers/Create', [
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'no_telp', 'is_loyalty_member', 'loyalty_tier', 'loyalty_points']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateVoucher($request);

        $voucher = CustomerVoucher::create([
            ...$validated,
            'created_by' => $request->user()?->id,
            'code' => $validated['code'] ?: $this->generateVoucherCode(),
        ]);

        $this->auditLogService->log(
            event: 'customer_voucher.created',
            module: 'customer_vouchers',
            auditable: $voucher,
            description: 'Voucher customer dibuat.',
            after: $this->auditPayload($voucher->fresh('customer'))
        );

        return redirect()
            ->route('customer-vouchers.index')
            ->with('success', 'Voucher customer berhasil dibuat.');
    }

    public function edit(CustomerVoucher $customerVoucher)
    {
        return Inertia::render('Dashboard/CustomerVouchers/Edit', [
            'voucher' => $customerVoucher->load('customer:id,name,no_telp'),
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'no_telp', 'is_loyalty_member', 'loyalty_tier', 'loyalty_points']),
        ]);
    }

    public function update(Request $request, CustomerVoucher $customerVoucher)
    {
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

        return redirect()
            ->route('customer-vouchers.index')
            ->with('success', 'Voucher customer berhasil diperbarui.');
    }

    public function destroy(CustomerVoucher $customerVoucher)
    {
        $before = $this->auditPayload($customerVoucher);
        $customerVoucher->delete();

        $this->auditLogService->log(
            event: 'customer_voucher.deleted',
            module: 'customer_vouchers',
            auditable: $customerVoucher,
            description: 'Voucher customer dihapus.',
            before: $before
        );

        return back()->with('success', 'Voucher customer berhasil dihapus.');
    }

    private function validateVoucher(Request $request, ?CustomerVoucher $voucher = null): array
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('customer_vouchers', 'code')->ignore($voucher?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'discount_type' => ['required', Rule::in([
                CustomerVoucher::TYPE_FIXED_AMOUNT,
                CustomerVoucher::TYPE_PERCENTAGE,
            ])],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'minimum_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (
            $validated['discount_type'] === CustomerVoucher::TYPE_PERCENTAGE
            && (float) $validated['discount_value'] > 100
        ) {
            $request->validate([
                'discount_value' => ['max:100'],
            ]);
        }

        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);
        $validated['minimum_order'] = max(0, (int) ($validated['minimum_order'] ?? 0));

        return $validated;
    }

    private function auditPayload(CustomerVoucher $voucher): array
    {
        return [
            'customer_id' => $voucher->customer_id,
            'code' => $voucher->code,
            'name' => $voucher->name,
            'discount_type' => $voucher->discount_type,
            'discount_value' => (float) $voucher->discount_value,
            'minimum_order' => (int) $voucher->minimum_order,
            'is_active' => (bool) $voucher->is_active,
            'is_used' => (bool) $voucher->is_used,
            'starts_at' => optional($voucher->starts_at)?->toIso8601String(),
            'expires_at' => optional($voucher->expires_at)?->toIso8601String(),
            'used_at' => optional($voucher->used_at)?->toIso8601String(),
        ];
    }

    private function generateVoucherCode(): string
    {
        do {
            $code = 'VCR-'.Str::upper(Str::random(8));
        } while (CustomerVoucher::query()->where('code', $code)->exists());

        return $code;
    }
}
