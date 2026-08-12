<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Http\Traits\ApiResponder;
use App\Models\Customer;
use App\Services\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    use ApiResponder;

    public function __construct(
        private readonly LoyaltyService $loyaltyService
    ) {}

    /**
     * GET /api/v1/customers?search=&loyalty=&page=&per_page=
     */
    public function index(Request $request): JsonResponse
    {
        $customers = Customer::query()
            ->when($request->string('search')->toString(), function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('no_telp', 'like', "%{$search}%")
                        ->orWhere('member_code', 'like', "%{$search}%");
                });
            })
            ->when($request->string('loyalty')->toString() === 'member', fn ($q) => $q->where('is_loyalty_member', true))
            ->orderByDesc('created_at')
            ->paginate($this->perPage());

        return $this->paginated($customers, CustomerResource::collection($customers));
    }

    /**
     * POST /api/v1/customers
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'no_telp' => ['required', 'string', 'max:30', Rule::unique('customers', 'no_telp')],
            'address' => ['nullable', 'string'],
            'is_loyalty_member' => ['nullable', 'boolean'],
            'loyalty_tier' => ['nullable', 'string', Rule::in(array_keys($this->loyaltyService->tiers()))],
            'province_name' => ['nullable', 'string', 'max:255'],
            'regency_name' => ['nullable', 'string', 'max:255'],
            'district_name' => ['nullable', 'string', 'max:255'],
            'village_name' => ['nullable', 'string', 'max:255'],
        ]);

        $customer = Customer::create([
            'name' => $validated['name'],
            'no_telp' => $validated['no_telp'],
            'address' => $validated['address'] ?? '',
            'is_loyalty_member' => $validated['is_loyalty_member'] ?? false,
            'loyalty_tier' => $validated['loyalty_tier'] ?? 'regular',
            'province_name' => $validated['province_name'] ?? null,
            'regency_name' => $validated['regency_name'] ?? null,
            'district_name' => $validated['district_name'] ?? null,
            'village_name' => $validated['village_name'] ?? null,
        ]);

        if ($customer->is_loyalty_member) {
            $this->loyaltyService->ensureMembership($customer);
        }

        return $this->created(new CustomerResource($customer), 'Pelanggan berhasil dibuat');
    }

    /**
     * GET /api/v1/customers/{customer}
     */
    public function show(Request $request, Customer $customer): JsonResponse
    {
        return $this->ok(new CustomerResource($customer));
    }

    /**
     * PUT/PATCH /api/v1/customers/{customer}
     */
    public function update(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'no_telp' => ['sometimes', 'string', 'max:30', Rule::unique('customers', 'no_telp')->ignore($customer->id)],
            'address' => ['nullable', 'string'],
            'is_loyalty_member' => ['nullable', 'boolean'],
            'loyalty_tier' => ['nullable', 'string', Rule::in(array_keys($this->loyaltyService->tiers()))],
            'province_name' => ['nullable', 'string', 'max:255'],
            'regency_name' => ['nullable', 'string', 'max:255'],
            'district_name' => ['nullable', 'string', 'max:255'],
            'village_name' => ['nullable', 'string', 'max:255'],
        ]);

        $customer->update($validated);

        if (! empty($validated['is_loyalty_member'])) {
            $this->loyaltyService->ensureMembership($customer);
        }

        return $this->ok(new CustomerResource($customer->fresh()), 'Pelanggan berhasil diperbarui');
    }

    /**
     * DELETE /api/v1/customers/{customer}
     */
    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        $customer->delete();

        return $this->noContent();
    }
}
