<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerVoucher\IndexCustomerVoucherRequest;
use App\Http\Requests\CustomerVoucher\StoreCustomerVoucherRequest;
use App\Http\Requests\CustomerVoucher\UpdateCustomerVoucherRequest;
use App\Models\CustomerVoucher;
use App\Services\CustomerVouchers\CreateCustomerVoucherService;
use App\Services\CustomerVouchers\CustomerVoucherIndexQueryService;
use App\Services\CustomerVouchers\CustomerVoucherPayloadService;
use App\Services\CustomerVouchers\DeleteCustomerVoucherService;
use App\Services\CustomerVouchers\UpdateCustomerVoucherService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CustomerVoucherController extends Controller
{
    public function index(IndexCustomerVoucherRequest $request, CustomerVoucherIndexQueryService $service): Response
    {
        return Inertia::render('Dashboard/CustomerVouchers/Index', $service->execute($request->filters()));
    }

    public function create(CustomerVoucherPayloadService $service): Response
    {
        return Inertia::render('Dashboard/CustomerVouchers/Create', $service->createPayload());
    }

    public function store(
        StoreCustomerVoucherRequest $request,
        CreateCustomerVoucherService $service
    ): RedirectResponse {
        $service->execute($request->normalizedData(), $request->user()?->id);

        return to_route('customer-vouchers.index')
            ->with('success', 'Voucher customer berhasil dibuat.');
    }

    public function edit(CustomerVoucher $customerVoucher, CustomerVoucherPayloadService $service): Response
    {
        return Inertia::render('Dashboard/CustomerVouchers/Edit', $service->editPayload($customerVoucher));
    }

    public function update(
        UpdateCustomerVoucherRequest $request,
        CustomerVoucher $customerVoucher,
        UpdateCustomerVoucherService $service
    ): RedirectResponse {
        $service->execute($customerVoucher, $request->normalizedData());

        return to_route('customer-vouchers.index')
            ->with('success', 'Voucher customer berhasil diperbarui.');
    }

    public function destroy(
        CustomerVoucher $customerVoucher,
        DeleteCustomerVoucherService $service
    ): RedirectResponse {
        $service->execute($customerVoucher);

        return back()->with('success', 'Voucher customer berhasil dihapus.');
    }
}
