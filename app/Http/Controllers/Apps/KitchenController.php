<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\KitchenStation;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Setting;
use App\Services\AuditLogService;
use App\Services\KitchenTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KitchenController extends Controller
{
    public function __construct(
        private readonly KitchenTicketService $kitchenTicketService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $stations = $this->kitchenTicketService->stationsFor($user);

        return Inertia::render('Dashboard/Kitchen/Index', [
            'stations' => $stations->map(fn (KitchenStation $station) => [
                'id' => $station->id,
                'name' => $station->name,
                'is_active' => $station->is_active,
            ])->values(),
            'selectedStationId' => $request->integer('station') ?: null,
            'enabled' => Setting::getBool('kitchen_enabled', true),
            'canManage' => $user->can('kitchen-manage'),
        ]);
    }

    public function board(Request $request): JsonResponse
    {
        $stationId = $request->integer('station') ?: null;
        $tickets = $this->kitchenTicketService->boardFor($request->user(), $stationId);

        return response()->json([
            'enabled' => Setting::getBool('kitchen_enabled', true),
            'server_time' => now()->toIso8601String(),
            'tickets' => $tickets->map(fn (KitchenTicket $ticket) => $this->ticketPayload($ticket))->values(),
        ]);
    }

    public function updateItemStatus(Request $request, KitchenTicketItem $kitchenTicketItem): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:queued,cooking,ready'],
        ]);

        $item = $this->kitchenTicketService->updateItemStatus($kitchenTicketItem, $validated['status'], $request->user());
        $ticket = $item->ticket->load(['station', 'items', 'dineOrder.session.table']);

        return response()->json(['ticket' => $this->ticketPayload($ticket)]);
    }

    public function ticketCooking(Request $request, KitchenTicket $kitchenTicket): JsonResponse
    {
        $ticket = $this->kitchenTicketService->markTicketCooking($kitchenTicket, $request->user());
        $ticket->loadMissing('dineOrder.session.table');

        return response()->json(['ticket' => $this->ticketPayload($ticket)]);
    }

    public function ticketReady(Request $request, KitchenTicket $kitchenTicket): JsonResponse
    {
        $ticket = $this->kitchenTicketService->markTicketReady($kitchenTicket, $request->user());
        $ticket->loadMissing('dineOrder.session.table');

        return response()->json(['ticket' => $this->ticketPayload($ticket)]);
    }

    public function clearTicket(Request $request, KitchenTicket $kitchenTicket): JsonResponse
    {
        $ticket = $this->kitchenTicketService->clearTicket($kitchenTicket, $request->user());
        $ticket->loadMissing('dineOrder.session.table');

        return response()->json(['ticket' => $this->ticketPayload($ticket)]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate(['enabled' => ['required', 'boolean']]);
        $before = Setting::getBool('kitchen_enabled', true);

        Setting::set('kitchen_enabled', $validated['enabled'] ? '1' : '0', 'Kitchen display enabled');

        $this->auditLogService->log(
            'kitchen.setting_updated',
            'kitchen',
            null,
            'Pengaturan dapur diperbarui.',
            ['enabled' => $before],
            ['enabled' => $validated['enabled']],
        );

        return back()->with('success', 'Pengaturan dapur diperbarui.');
    }

    private function ticketPayload(KitchenTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'status' => $ticket->status,
            'order_type' => $ticket->order_type,
            'table_label' => $ticket->dine_order_id ? $ticket->dineOrder?->session?->table?->name : null,
            'station' => [
                'id' => $ticket->station->id,
                'name' => $ticket->station->name,
            ],
            'placed_at' => $ticket->placed_at?->toIso8601String(),
            'ready_at' => $ticket->ready_at?->toIso8601String(),
            'items' => $ticket->items->map(fn (KitchenTicketItem $item) => [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'qty' => $item->qty,
                'modifiers_text' => $item->modifiers_text,
                'note' => $item->note,
                'status' => $item->status,
                'started_at' => $item->started_at?->toIso8601String(),
                'ready_at' => $item->ready_at?->toIso8601String(),
            ])->values(),
        ];
    }
}
