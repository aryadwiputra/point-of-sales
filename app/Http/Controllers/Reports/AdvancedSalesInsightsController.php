<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\IndexAdvancedSalesInsightsRequest;
use App\Services\Reports\AdvancedSalesInsightsQueryService;
use Inertia\Inertia;
use Inertia\Response;

class AdvancedSalesInsightsController extends Controller
{
    public function index(
        IndexAdvancedSalesInsightsRequest $request,
        AdvancedSalesInsightsQueryService $queryService
    ): Response {
        return Inertia::render(
            'Dashboard/Reports/Insights',
            $queryService->execute($request->filters())
        );
    }
}
