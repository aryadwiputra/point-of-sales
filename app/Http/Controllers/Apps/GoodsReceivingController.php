<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\GoodsReceiving\CreateGoodsReceivingRequest;
use App\Http\Requests\GoodsReceiving\IndexGoodsReceivingRequest;
use App\Http\Requests\GoodsReceiving\StoreGoodsReceivingRequest;
use App\Models\GoodsReceiving;
use App\Services\GoodsReceivings\CreateGoodsReceivingService;
use App\Services\GoodsReceivings\GoodsReceivingCreateQueryService;
use App\Services\GoodsReceivings\GoodsReceivingIndexQueryService;
use App\Services\GoodsReceivings\GoodsReceivingShowQueryService;
use Inertia\Inertia;

class GoodsReceivingController extends Controller
{
    public function index(IndexGoodsReceivingRequest $request, GoodsReceivingIndexQueryService $service)
    {
        return Inertia::render('Dashboard/GoodsReceivings/Index', $service->execute($request->filters()));
    }

    public function create(CreateGoodsReceivingRequest $request, GoodsReceivingCreateQueryService $service)
    {
        return Inertia::render(
            'Dashboard/GoodsReceivings/Create',
            $service->execute($request->integer('purchase_order_id') ?: null)
        );
    }

    public function store(StoreGoodsReceivingRequest $request, CreateGoodsReceivingService $service)
    {
        $result = $service->execute($request->validated(), $request->user()->id);

        if ($result['error']) {
            return back()->with('error', $result['error']);
        }

        return redirect()
            ->route('goods-receivings.show', $result['receiving'])
            ->with('success', 'Penerimaan barang berhasil dicatat.');
    }

    public function show(GoodsReceiving $goodsReceiving, GoodsReceivingShowQueryService $service)
    {
        return Inertia::render('Dashboard/GoodsReceivings/Show', $service->execute($goodsReceiving));
    }
}
