<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Reports\SalesReportController as ReportsSalesReportController;
use App\Http\Traits\ApiResponder;
use App\Services\OutletAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesReportController extends ReportsSalesReportController
{
    use ApiResponder;

    /**
     * GET /api/v1/reports/sales
     */
    public function sales(Request $request, OutletAccessService $outletAccessService): JsonResponse
    {
        return $this->ok($this->reportData($request, $outletAccessService));
    }
}
