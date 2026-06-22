<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Notification\MarkLowStockNotificationReadRequest;
use App\Services\Notifications\MarkAllLowStockNotificationsReadService;
use App\Services\Notifications\MarkLowStockNotificationReadService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Mark a single low-stock notification as read for the current user.
     */
    public function markLowStockRead(
        MarkLowStockNotificationReadRequest $request,
        MarkLowStockNotificationReadService $service
    ) {
        $service->execute($request->user()->id, (int) $request->validated('product_id'));

        return back()->with('status', 'notification-read');
    }

    /**
     * Mark all low-stock notifications as read for the current user.
     */
    public function markAllLowStockRead(Request $request, MarkAllLowStockNotificationsReadService $service)
    {
        if (! $service->execute($request->user()->id)) {
            return back();
        }

        return back()->with('status', 'notification-read-all');
    }
}
