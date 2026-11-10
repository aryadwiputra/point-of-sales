<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Reports\ProfitReportController as ReportsProfitReportController;
use App\Http\Traits\ApiResponder;
use App\Services\OutletAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfitReportController extends ReportsProfitReportController
{
    use ApiResponder;

    /**
     * GET /api/v1/reports/profits
     */
    public function profits(Request $request, OutletAccessService $outletAccessService): JsonResponse
    {
        return $this->ok($this->reportData($request, $outletAccessService));
    }
}
