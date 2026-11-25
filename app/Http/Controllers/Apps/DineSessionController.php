<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\DineBill;
use App\Models\DineSession;
use App\Models\DiningTable;
use App\Services\DineBillService;
use App\Services\DineSessionService;
use App\Services\OutletAccessService;
use Illuminate\Http\Request;

class DineSessionController extends Controller
{
    public function __construct(
        protected readonly DineSessionService $sessions,
        protected readonly DineBillService $bills,
        protected readonly OutletAccessService $outletAccess,
    ) {}

    public function open(Request $request, DiningTable $dineTable)
    {
        $this->ensureTableOutlet($request, $dineTable);
        $validated = $request->validate([
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $session = $this->sessions->open(
            $dineTable,
            $validated['guest_count'] ?? null,
            $validated['notes'] ?? null,
            $request->user()->id,
        );
        $this->bills->createNextBill($session, $request->user()->id);

        return back()->with('success', 'Meja berhasil dibuka.');
    }

    public function close(Request $request, DineSession $dineSession)
    {
        $this->ensureSessionOutlet($request, $dineSession);

        foreach ($dineSession->bills()->where('status', DineBill::STATUS_OPEN)->get() as $bill) {
            $this->bills->voidIfEmpty($bill);
        }

        $this->sessions->close($dineSession);

        return back()->with('success', 'Sesi meja ditutup.');
    }

    public function move(Request $request, DineSession $dineSession)
    {
        $this->ensureSessionOutlet($request, $dineSession);
        $validated = $request->validate([
            'dine_table_id' => ['required', 'integer', 'exists:dine_tables,id'],
        ]);
        $target = DiningTable::findOrFail($validated['dine_table_id']);
        $this->ensureTableOutlet($request, $target);
        $this->sessions->move($dineSession, $target);

        return back()->with('success', 'Sesi dipindahkan ke meja tujuan.');
    }

    public function merge(Request $request, DineSession $dineSession)
    {
        $this->ensureSessionOutlet($request, $dineSession);
        $validated = $request->validate([
            'target_session_id' => ['required', 'integer', 'exists:dine_sessions,id'],
        ]);
        $target = DineSession::findOrFail($validated['target_session_id']);
        $this->ensureSessionOutlet($request, $target);
        $this->sessions->merge($dineSession, $target, $request->user()->id);

        return back()->with('success', 'Sesi berhasil digabung.');
    }

    protected function ensureTableOutlet(Request $request, DiningTable $table): void
    {
        $outlet = $this->outletAccess->activeOutlet($request);
        $outletId = $table->area?->outlet_id;

        abort_if($outletId !== null && $outlet && (int) $outletId !== (int) $outlet->id, 404);
    }

    protected function ensureSessionOutlet(Request $request, DineSession $session): void
    {
        $session->loadMissing('table.area');
        $this->ensureTableOutlet($request, $session->table);
    }
}
