<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupplierResource;
use App\Http\Traits\ApiResponder;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    use ApiResponder;

    /**
     * GET /api/v1/suppliers
     */
    public function index(Request $request): JsonResponse
    {
        $suppliers = Supplier::query()
            ->when($request->string('search')->toString(), function ($q, $s) {
                $q->where(function ($sub) use ($s) {
                    $sub->where('name', 'like', "%{$s}%")
                        ->orWhere('phone', 'like', "%{$s}%");
                });
            })
            ->orderBy('name')
            ->paginate($this->perPage());

        return $this->paginated($suppliers, SupplierResource::collection($suppliers));
    }

    /**
     * POST /api/v1/suppliers
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
        ]);

        $supplier = Supplier::create([
            ...$validated,
            'phone' => $validated['phone'] ?? '',
            'email' => $validated['email'] ?? '',
            'address' => $validated['address'] ?? '',
        ]);

        return $this->created(new SupplierResource($supplier), 'Supplier berhasil dibuat');
    }

    /**
     * GET /api/v1/suppliers/{supplier}
     */
    public function show(Request $request, Supplier $supplier): JsonResponse
    {
        return $this->ok(new SupplierResource($supplier));
    }

    /**
     * PUT/PATCH /api/v1/suppliers/{supplier}
     */
    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
        ]);

        $supplier->update($validated);

        return $this->ok(new SupplierResource($supplier), 'Supplier berhasil diperbarui');
    }

    /**
     * DELETE /api/v1/suppliers/{supplier}
     */
    public function destroy(Request $request, Supplier $supplier): JsonResponse
    {
        $supplier->delete();

        return $this->noContent();
    }
}
