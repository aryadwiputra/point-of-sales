<?php

namespace App\Http\Controllers\Apps;

use App\Data\CustomerVoucherData;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerVoucher;
use App\Services\CustomerVoucherService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerVoucherController extends Controller
{
    public function __construct(
        private readonly CustomerVoucherService $voucherService
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
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Dashboard/CustomerVouchers/Index', [
            'vouchers' => CustomerVoucherData::collection($vouchers),
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        return Inertia::render('Dashboard/CustomerVouchers/Create', [
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'no_telp', 'is_loyalty_member', 'loyalty_tier', 'loyalty_points']),
        ]);
    }

    public function store(CustomerVoucherData $request)
    {
        $this->voucherService->createVoucher($request, request()->user()?->id);

        return redirect()
            ->route('customer-vouchers.index')
            ->with('success', 'Voucher customer berhasil dibuat.');
    }

    public function edit(CustomerVoucher $customerVoucher)
    {
        return Inertia::render('Dashboard/CustomerVouchers/Edit', [
            'voucher' => CustomerVoucherData::fromModel($customerVoucher->load('customer:id,name,no_telp')),
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'no_telp', 'is_loyalty_member', 'loyalty_tier', 'loyalty_points']),
        ]);
    }

    public function update(CustomerVoucherData $request, CustomerVoucher $customerVoucher)
    {
        $this->voucherService->updateVoucher($customerVoucher, $request);

        return redirect()
            ->route('customer-vouchers.index')
            ->with('success', 'Voucher customer berhasil diperbarui.');
    }

    public function destroy(CustomerVoucher $customerVoucher)
    {
        $this->voucherService->deleteVoucher($customerVoucher);

        return back()->with('success', 'Voucher customer berhasil dihapus.');
    }
}
