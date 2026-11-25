<?php

namespace App\Services;

use App\Models\DineBill;
use App\Models\DineOrder;
use App\Models\DineOrderItem;
use App\Models\DineSession;
use App\Models\DiningTable;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DineSessionService
{
    public function __construct(private readonly OutletAccessService $outletAccess) {}

    public function open(
        DiningTable $table,
        ?int $guestCount = null,
        ?string $notes = null,
        ?int $openedBy = null
    ): DineSession {
        return DB::transaction(function () use ($table, $guestCount, $notes, $openedBy) {
            $locked = DiningTable::query()->lockForUpdate()->findOrFail($table->id);

            if (! $locked->is_active) {
                throw ValidationException::withMessages([
                    'table' => 'Meja tidak aktif.',
                ]);
            }

            $existing = DineSession::query()
                ->where('dine_table_id', $locked->id)
                ->where('status', DineSession::STATUS_OPEN)
                ->lockForUpdate()
                ->exists();

            if ($existing) {
                throw ValidationException::withMessages([
                    'table' => 'Meja sudah memiliki sesi aktif.',
                ]);
            }

            return DineSession::create([
                'dine_table_id' => $locked->id,
                'status' => DineSession::STATUS_OPEN,
                'guest_count' => $guestCount,
                'opened_by' => $openedBy,
                'opened_at' => now(),
                'notes' => $notes,
            ]);
        });
    }

    public function ensureOpenForTable(DiningTable $table, int $openedBy): DineSession
    {
        return DB::transaction(function () use ($table, $openedBy) {
            $locked = DiningTable::query()->lockForUpdate()->findOrFail($table->id);

            $session = DineSession::query()
                ->where('dine_table_id', $locked->id)
                ->where('status', DineSession::STATUS_OPEN)
                ->lockForUpdate()
                ->first();

            if ($session) {
                return $session;
            }

            return DineSession::create([
                'dine_table_id' => $locked->id,
                'status' => DineSession::STATUS_OPEN,
                'opened_by' => $openedBy,
                'opened_at' => now(),
            ]);
        });
    }

    public function lockOpenSessionForTable(DiningTable $table): ?DineSession
    {
        return DineSession::query()
            ->where('dine_table_id', $table->id)
            ->where('status', DineSession::STATUS_OPEN)
            ->lockForUpdate()
            ->first();
    }

    public function close(DineSession $session): void
    {
        DB::transaction(function () use ($session) {
            $locked = DineSession::query()->lockForUpdate()->findOrFail($session->id);

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages([
                    'session' => 'Sesi sudah ditutup.',
                ]);
            }

            if ($locked->bills()->whereIn('status', [
                DineBill::STATUS_OPEN,
                DineBill::STATUS_PENDING_APPROVAL,
            ])->exists()) {
                throw ValidationException::withMessages([
                    'session' => 'Sesi masih memiliki bill yang belum selesai.',
                ]);
            }

            $locked->update([
                'status' => DineSession::STATUS_CLOSED,
                'closed_at' => now(),
            ]);
        });
    }

    public function closeIfIdle(DineSession $session): bool
    {
        return DB::transaction(function () use ($session) {
            $locked = DineSession::query()->lockForUpdate()->findOrFail($session->id);

            if (! $locked->isOpen()) {
                return false;
            }

            if ($locked->bills()->whereIn('status', [
                DineBill::STATUS_OPEN,
                DineBill::STATUS_PENDING_APPROVAL,
            ])->exists()) {
                return false;
            }

            $locked->update([
                'status' => DineSession::STATUS_CLOSED,
                'closed_at' => now(),
            ]);

            return true;
        });
    }

    public function move(DineSession $session, DiningTable $target): DineSession
    {
        return DB::transaction(function () use ($session, $target) {
            $tableIds = collect([$session->dine_table_id, $target->id])->sort()->values()->all();

            DiningTable::query()
                ->whereIn('id', $tableIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $locked = DineSession::query()->lockForUpdate()->findOrFail($session->id);

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages([
                    'session' => 'Sesi sudah ditutup.',
                ]);
            }

            $this->assertNoPendingApproval($locked);

            $target = DiningTable::query()->findOrFail($target->id);

            if (! $target->is_active) {
                throw ValidationException::withMessages([
                    'table' => 'Meja tujuan tidak aktif.',
                ]);
            }

            $sourceOutlet = $locked->table?->area?->outlet_id;
            $targetOutlet = $target->area?->outlet_id;

            if ((int) $sourceOutlet !== (int) $targetOutlet) {
                throw ValidationException::withMessages([
                    'table' => 'Meja tujuan harus berada di outlet yang sama.',
                ]);
            }

            $occupied = DineSession::query()
                ->where('dine_table_id', $target->id)
                ->where('status', DineSession::STATUS_OPEN)
                ->exists();

            if ($occupied) {
                throw ValidationException::withMessages([
                    'table' => 'Meja tujuan sedang terpakai.',
                ]);
            }

            $locked->update(['dine_table_id' => $target->id]);

            return $locked->fresh();
        });
    }

    public function merge(DineSession $source, DineSession $target, ?int $actorId = null): DineSession
    {
        return DB::transaction(function () use ($source, $target) {
            $sessions = DineSession::query()
                ->whereIn('id', [$source->id, $target->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $source = $sessions->get($source->id);
            $target = $sessions->get($target->id);

            if (! $source?->isOpen() || ! $target?->isOpen()) {
                throw ValidationException::withMessages([
                    'session' => 'Kedua sesi harus aktif untuk digabung.',
                ]);
            }

            $this->assertSameOutlet($source, $target);
            $this->assertNoPendingApproval($source);
            $this->assertNoPendingApproval($target);

            $nextSequence = ((int) DineBill::query()
                ->where('dine_session_id', $target->id)
                ->max('sequence')) + 1;

            $sourceBills = DineBill::query()
                ->where('dine_session_id', $source->id)
                ->where('status', DineBill::STATUS_OPEN)
                ->orderBy('sequence')
                ->lockForUpdate()
                ->get();

            foreach ($sourceBills as $bill) {
                $bill->update([
                    'dine_session_id' => $target->id,
                    'sequence' => $nextSequence++,
                ]);
            }

            $movedBillIds = $sourceBills->pluck('id');
            $movedOrderIds = DineOrderItem::query()
                ->whereIn('dine_bill_id', $movedBillIds)
                ->pluck('dine_order_id')
                ->unique()
                ->values();

            if ($movedOrderIds->isNotEmpty()) {
                DineOrder::query()
                    ->whereIn('id', $movedOrderIds)
                    ->update(['dine_session_id' => $target->id]);
            }

            $source->update([
                'status' => DineSession::STATUS_CLOSED,
                'closed_at' => now(),
                'merged_into_session_id' => $target->id,
            ]);

            return $target->fresh();
        });
    }

    public function occupancyForTables(iterable $tables): array
    {
        $tableIds = collect($tables)->pluck('id')->all();

        return DineSession::query()
            ->with('bills')
            ->whereIn('dine_table_id', $tableIds)
            ->where('status', DineSession::STATUS_OPEN)
            ->get()
            ->mapWithKeys(function (DineSession $session) {
                return [
                    $session->dine_table_id => [
                        'session_id' => $session->id,
                        'guest_count' => $session->guest_count,
                        'opened_at' => $session->opened_at?->toISOString(),
                        'open_bill_count' => $session->bills->whereIn('status', [
                            DineBill::STATUS_OPEN,
                            DineBill::STATUS_PENDING_APPROVAL,
                        ])->count(),
                        'running_total' => (int) $session->bills
                            ->whereNotIn('status', [DineBill::STATUS_VOID])
                            ->sum('grand_total'),
                    ],
                ];
            })
            ->all();
    }

    public function assertOutletAccess(DiningTable $table, ?Outlet $outlet): void
    {
        $outletId = $table->area?->outlet_id;

        if ($outletId !== null && $outlet && (int) $outletId !== (int) $outlet->id) {
            throw ValidationException::withMessages([
                'outlet' => 'Meja berada di outlet yang berbeda dengan shift kasir.',
            ]);
        }
    }

    private function assertNoPendingApproval(DineSession $session): void
    {
        if ($session->bills()->where('status', DineBill::STATUS_PENDING_APPROVAL)->exists()) {
            throw ValidationException::withMessages([
                'session' => 'Sesi memiliki bill yang menunggu approval diskon.',
            ]);
        }
    }

    private function assertSameOutlet(DineSession $source, DineSession $target): void
    {
        $sourceOutlet = $source->table?->area?->outlet_id;
        $targetOutlet = $target->table?->area?->outlet_id;

        if ((int) $sourceOutlet !== (int) $targetOutlet) {
            throw ValidationException::withMessages([
                'session' => 'Sesi harus berada di outlet yang sama.',
            ]);
        }
    }
}
