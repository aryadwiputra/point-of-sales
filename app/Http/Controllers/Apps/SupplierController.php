<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\StoreSupplierRequest;
use App\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Services\Suppliers\CreateSupplierService;
use App\Services\Suppliers\DeleteSupplierService;
use App\Services\Suppliers\SupplierIndexQueryService;
use App\Services\Suppliers\UpdateSupplierService;
use Inertia\Inertia;

class SupplierController extends Controller
{
    public function index(SupplierIndexQueryService $service)
    {
        return Inertia::render('Dashboard/Suppliers/Index', [
            'suppliers' => $service->execute(),
        ]);
    }

    public function store(StoreSupplierRequest $request, CreateSupplierService $service)
    {
        $service->execute($request->validated());

        return back()->with('success', 'Supplier berhasil ditambahkan.');
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier, UpdateSupplierService $service)
    {
        $service->execute($supplier, $request->validated());

        return back()->with('success', 'Supplier berhasil diperbarui.');
    }

    public function destroy(Supplier $supplier, DeleteSupplierService $service)
    {
        if (! $service->execute($supplier)) {
            return back()->with('error', 'Supplier memiliki hutang, tidak dapat dihapus.');
        }

        return back()->with('success', 'Supplier dihapus.');
    }
}
