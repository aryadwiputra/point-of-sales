<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Reports\AdvancedSalesInsightsController as InsightsController;
use App\Http\Traits\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvancedSalesInsightsController extends InsightsController
{
    use ApiResponder;

    /**
     * GET /api/v1/reports/insights
     */
    public function insights(Request $request): JsonResponse
    {
        return $this->ok($this->reportData($request));
    }
}
