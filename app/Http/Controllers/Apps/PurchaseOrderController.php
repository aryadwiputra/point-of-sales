<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseOrder\IndexPurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\StorePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrders\CancelPurchaseOrderService;
use App\Services\PurchaseOrders\CreatePurchaseOrderService;
use App\Services\PurchaseOrders\PlacePurchaseOrderService;
use App\Services\PurchaseOrders\PurchaseOrderCreateQueryService;
use App\Services\PurchaseOrders\PurchaseOrderIndexQueryService;
use App\Services\PurchaseOrders\PurchaseOrderShowQueryService;
use Inertia\Inertia;

class PurchaseOrderController extends Controller
{
    public function index(IndexPurchaseOrderRequest $request, PurchaseOrderIndexQueryService $service)
    {
        return Inertia::render('Dashboard/PurchaseOrders/Index', $service->execute($request->filters()));
    }

    public function create(PurchaseOrderCreateQueryService $service)
    {
        return Inertia::render('Dashboard/PurchaseOrders/Create', $service->execute());
    }

    public function store(StorePurchaseOrderRequest $request, CreatePurchaseOrderService $service)
    {
        $order = $service->execute($request->validated(), $request->user()->id);

        return redirect()
            ->route('purchase-orders.show', $order)
            ->with('success', 'Purchase order berhasil dibuat.');
    }

    public function show(PurchaseOrder $purchaseOrder, PurchaseOrderShowQueryService $service)
    {
        return Inertia::render('Dashboard/PurchaseOrders/Show', $service->execute($purchaseOrder));
    }

    public function placeOrder(PurchaseOrder $purchaseOrder, PlacePurchaseOrderService $service)
    {
        if (! $service->execute($purchaseOrder)) {
            return back()->with('error', 'Hanya PO dengan status draft yang bisa dipesan.');
        }

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('success', 'Purchase order berhasil dipesan.');
    }

    public function cancel(PurchaseOrder $purchaseOrder, CancelPurchaseOrderService $service)
    {
        if (! $service->execute($purchaseOrder)) {
            return back()->with('error', 'PO tidak dapat dibatalkan.');
        }

        return redirect()
            ->route('purchase-orders.index')
            ->with('success', 'Purchase order dibatalkan.');
    }
}
