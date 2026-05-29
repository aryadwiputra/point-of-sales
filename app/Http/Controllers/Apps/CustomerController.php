<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\IndexCustomerRequest;
use App\Http\Requests\Customer\StoreCustomerAjaxRequest;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\SyncCustomerSegmentsRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Requests\Customer\UpgradeCustomerMemberRequest;
use App\Models\Customer;
use App\Services\Customers\CreateCustomerService;
use App\Services\Customers\CustomerHistoryQueryService;
use App\Services\Customers\CustomerIndexQueryService;
use App\Services\Customers\CustomerPayloadService;
use App\Services\Customers\CustomerShowQueryService;
use App\Services\Customers\DeleteCustomerService;
use App\Services\Customers\SyncCustomerSegmentsService;
use App\Services\Customers\UpdateCustomerService;
use App\Services\Customers\UpgradeCustomerToMemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CustomerController extends Controller
{
    public function index(IndexCustomerRequest $request, CustomerIndexQueryService $service): Response
    {
        return Inertia::render(
            'Dashboard/Customers/Index',
            $service->execute($request->input('search'))
        );
    }

    public function create(CustomerPayloadService $service): Response
    {
        return Inertia::render('Dashboard/Customers/Create', $service->createPayload());
    }

    public function store(StoreCustomerRequest $request, CreateCustomerService $service): RedirectResponse
    {
        $service->execute($request->validated());

        return to_route('customers.index');
    }

    public function storeAjax(
        StoreCustomerAjaxRequest $request,
        CreateCustomerService $service,
        CustomerPayloadService $payloadService
    ): JsonResponse {
        try {
            $customer = $service->execute($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Pelanggan berhasil ditambahkan',
                'customer' => $payloadService->jsonCustomer($customer),
            ]);
        } catch (Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan pelanggan',
                'errors' => [],
            ], 500);
        }
    }

    public function edit(Customer $customer, CustomerPayloadService $service): Response
    {
        return Inertia::render('Dashboard/Customers/Edit', $service->editPayload($customer));
    }

    public function update(
        UpdateCustomerRequest $request,
        Customer $customer,
        UpdateCustomerService $service
    ): RedirectResponse {
        $service->execute($customer, $request->validated());

        return to_route('customers.index');
    }

    public function show(Customer $customer, CustomerShowQueryService $service): Response
    {
        return Inertia::render('Dashboard/Customers/Show', $service->execute($customer));
    }

    public function syncSegments(
        SyncCustomerSegmentsRequest $request,
        Customer $customer,
        SyncCustomerSegmentsService $service
    ): RedirectResponse {
        $service->execute($customer, $request->validated('segment_ids') ?? []);

        return back()->with('success', 'Segment manual customer berhasil diperbarui.');
    }

    public function destroy(Customer $customer, DeleteCustomerService $service): RedirectResponse
    {
        $service->execute($customer);

        return back();
    }

    public function getHistory(Customer $customer, CustomerHistoryQueryService $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            ...$service->historyPayload($customer),
        ]);
    }

    public function upgradeToMember(
        UpgradeCustomerMemberRequest $request,
        Customer $customer,
        UpgradeCustomerToMemberService $service,
        CustomerPayloadService $payloadService
    ): JsonResponse|RedirectResponse {
        $service->execute($customer, $request->validated());

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Pelanggan berhasil di-upgrade menjadi member.',
                'customer' => $payloadService->jsonCustomer($customer),
            ]);
        }

        return back()->with('success', 'Pelanggan berhasil di-upgrade menjadi member.');
    }
}
