<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\IndexProfitReportRequest;
use App\Services\Reports\ProfitReportQueryService;
use Inertia\Inertia;
use Inertia\Response;

class ProfitReportController extends Controller
{
    public function index(
        IndexProfitReportRequest $request,
        ProfitReportQueryService $queryService
    ): Response {
        return Inertia::render(
            'Dashboard/Reports/Profit',
            $queryService->execute($request->filters())
        );
    }
}
