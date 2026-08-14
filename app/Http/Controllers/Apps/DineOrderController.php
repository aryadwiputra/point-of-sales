<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\DineOrder;
use App\Services\DineOrderService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DineOrderController extends Controller
{
    public function __construct(
        private DineOrderService $service,
    ) {}

    public function index()
    {
        $orders = DineOrder::with(['table.area', 'items.product'])
            ->whereIn('status', [DineOrder::STATUS_SUBMITTED, DineOrder::STATUS_ACCEPTED])
            ->latest()
            ->get();

        return Inertia::render('Dashboard/DineIn/Orders/Index', [
            'orders' => $orders,
        ]);
    }

    public function accept(DineOrder $dineOrder)
    {
        $this->service->accept($dineOrder);

        return back()->with('success', 'Pesanan diterima dan siap diproses di kasir.');
    }

    public function reject(Request $request, DineOrder $dineOrder)
    {
        $this->service->reject($dineOrder, $request->input('reason'));

        return back()->with('success', 'Pesanan ditolak.');
    }
}
