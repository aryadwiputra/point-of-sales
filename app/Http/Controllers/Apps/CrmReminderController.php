<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\CrmReminder\IndexCrmReminderRequest;
use App\Services\CrmReminders\CrmReminderIndexQueryService;
use Inertia\Inertia;
use Inertia\Response;

class CrmReminderController extends Controller
{
    public function index(IndexCrmReminderRequest $request, CrmReminderIndexQueryService $service): Response
    {
        return Inertia::render('Dashboard/CrmReminders/Index', $service->execute($request->filters()));
    }
}
