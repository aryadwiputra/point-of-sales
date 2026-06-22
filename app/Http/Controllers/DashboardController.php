<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Queries\DashboardSummaryQueryService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(DashboardSummaryQueryService $queryService): Response
    {
        return Inertia::render('Dashboard/Index', $queryService->execute());
    }
}
