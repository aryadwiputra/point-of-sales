<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerSegment\IndexCustomerSegmentRequest;
use App\Http\Requests\CustomerSegment\SaveCustomerSegmentRequest;
use App\Http\Requests\CustomerSegment\StoreCustomerSegmentMemberRequest;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Services\CustomerSegments\CustomerSegmentIndexService;
use App\Services\CustomerSegments\CustomerSegmentManagementService;
use App\Services\CustomerSegments\CustomerSegmentShowQueryService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CustomerSegmentController extends Controller
{
    public function index(
        IndexCustomerSegmentRequest $request,
        CustomerSegmentIndexService $service
    ): Response {
        return Inertia::render('Dashboard/CustomerSegments/Index', $service->execute($request->filters()));
    }

    public function create(): Response
    {
        return Inertia::render('Dashboard/CustomerSegments/Create', [
            'segment' => null,
        ]);
    }

    public function store(
        SaveCustomerSegmentRequest $request,
        CustomerSegmentManagementService $service
    ): RedirectResponse {
        $service->create($request->validated());

        return redirect()
            ->route('customer-segments.index')
            ->with('success', 'Segment customer berhasil dibuat.');
    }

    public function show(
        CustomerSegment $customerSegment,
        CustomerSegmentShowQueryService $service
    ): Response {
        return Inertia::render('Dashboard/CustomerSegments/Show', $service->execute($customerSegment));
    }

    public function edit(CustomerSegment $customerSegment): Response
    {
        return Inertia::render('Dashboard/CustomerSegments/Edit', [
            'segment' => $customerSegment,
        ]);
    }

    public function update(
        SaveCustomerSegmentRequest $request,
        CustomerSegment $customerSegment,
        CustomerSegmentManagementService $service
    ): RedirectResponse {
        $service->update($customerSegment, $request->validated());

        return redirect()
            ->route('customer-segments.show', $customerSegment)
            ->with('success', 'Segment customer berhasil diperbarui.');
    }

    public function destroy(
        CustomerSegment $customerSegment,
        CustomerSegmentManagementService $service
    ): RedirectResponse {
        $service->delete($customerSegment);

        return redirect()
            ->route('customer-segments.index')
            ->with('success', 'Segment customer berhasil dihapus.');
    }

    public function storeMember(
        StoreCustomerSegmentMemberRequest $request,
        CustomerSegment $customerSegment,
        CustomerSegmentManagementService $service
    ): RedirectResponse {
        $service->addMember($customerSegment, $request->validated('customer_id'));

        return back()->with('success', 'Customer ditambahkan ke segment manual.');
    }

    public function destroyMember(
        CustomerSegment $customerSegment,
        Customer $customer,
        CustomerSegmentManagementService $service
    ): RedirectResponse {
        $service->removeMember($customerSegment, $customer);

        return back()->with('success', 'Customer dihapus dari segment manual.');
    }
}
