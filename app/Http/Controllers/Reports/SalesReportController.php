<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\IndexSalesReportRequest;
use App\Services\Reports\SalesReportQueryService;
use Inertia\Inertia;
use Inertia\Response;

class SalesReportController extends Controller
{
    public function index(
        IndexSalesReportRequest $request,
        SalesReportQueryService $queryService
    ): Response {
        return Inertia::render(
            'Dashboard/Reports/Sales',
            $queryService->execute($request->filters())
        );
    }
}
