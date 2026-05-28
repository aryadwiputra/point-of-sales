<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\StockMutation\IndexStockMutationRequest;
use App\Services\StockMutations\StockMutationIndexQueryService;
use Inertia\Inertia;
use Inertia\Response;

class StockMutationController extends Controller
{
    public function index(IndexStockMutationRequest $request, StockMutationIndexQueryService $service): Response
    {
        return Inertia::render('Dashboard/StockMutations/Index', $service->execute($request->filters()));
    }
}
