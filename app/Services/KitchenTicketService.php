<?php

namespace App\Services;

use App\Models\DineOrder;
use App\Models\DineOrderItem;
use App\Models\KitchenStation;
use App\Models\KitchenStationCategory;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Product;
use App\Models\ProductKitchenStationOverride;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KitchenTicketService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly OutletAccessService $outletAccessService,
    ) {}

    public function routeFor(Product $product, ?int $outletId): ?KitchenStation
    {
        $override = ProductKitchenStationOverride::query()
            ->with('station')
            ->where('product_id', $product->id)
            ->where('outlet_id', $outletId)
            ->whereHas('station', fn ($query) => $query->where('is_active', true))
            ->get()
            ->sortBy(fn (ProductKitchenStationOverride $row) => sprintf('%05d-%05d', $row->station->sort_order, $row->station->id))
            ->first();

        if ($override) {
            return $override->station;
        }

        if (! $product->category_id) {
            return null;
        }

        $mapping = KitchenStationCategory::query()
            ->with('station')
            ->where('category_id', $product->category_id)
            ->where('outlet_id', $outletId)
            ->whereHas('station', fn ($query) => $query->where('is_active', true))
            ->get()
            ->sortBy(fn (KitchenStationCategory $row) => sprintf('%05d-%05d', $row->station->sort_order, $row->station->id))
            ->first();

        return $mapping?->station;
    }

    public function createForDineOrder(DineOrder $order, ?int $outletId = null): void
    {
        if (! Setting::getBool('kitchen_enabled', true)) {
            return;
        }

        $order->loadMissing([
            'items.product',
            'items.modifiers',
            'session.table.area',
            'table.area',
        ]);

        $resolvedOutletId = $order->session?->table?->area?->outlet_id
            ?? $order->table?->area?->outlet_id
            ?? $outletId;

        $groups = [];

        foreach ($order->items as $item) {
            if ($item->isVoided() || ! $item->product) {
                continue;
            }

            $station = $this->routeFor($item->product, $resolvedOutletId);

            if (! $station) {
                $this->auditLogService->log(
                    'kitchen.ticket_skipped',
                    'kitchen',
                    $item->product,
                    'Item tidak memiliki station dapur.',
                    null,
                    null,
                    [
                        'product_id' => $item->product_id,
                        'outlet_id' => $resolvedOutletId,
                        'source' => 'dine_order',
                        'source_id' => $order->id,
                    ],
                );

                continue;
            }

            $groups[$station->id]['station'] = $station;
            $groups[$station->id]['items'][] = $item;
        }

        foreach ($groups as $stationId => $group) {
            $ticket = KitchenTicket::firstOrCreate(
                [
                    'dine_order_id' => $order->id,
                    'kitchen_station_id' => $stationId,
                ],
                [
                    'outlet_id' => $resolvedOutletId,
                    'transaction_id' => null,
                    'status' => KitchenTicket::STATUS_OPEN,
                    'order_type' => 'dine_in',
                    'placed_at' => now(),
                ],
            );

            foreach ($group['items'] as $item) {
                KitchenTicketItem::firstOrCreate(
                    ['dine_order_item_id' => $item->id],
                    [
                        'kitchen_ticket_id' => $ticket->id,
                        'transaction_detail_id' => null,
                        'product_name' => Str::limit($item->product->title, 150, ''),
                        'qty' => (int) $item->qty,
                        'modifiers_text' => $this->modifiersText($item->modifiers),
                        'note' => $item->note ? Str::limit($item->note, 255, '') : null,
                        'status' => KitchenTicketItem::STATUS_QUEUED,
                    ],
                );
            }
        }
    }

    public function createForTransaction(Transaction $transaction): void
    {
        if (! Setting::getBool('kitchen_enabled', true)) {
            return;
        }

        if (! in_array($transaction->order_type, ['takeaway', 'delivery'], true)) {
            return;
        }

        $transaction->loadMissing(['details.product', 'details.modifiers', 'warehouse.outlet']);

        $outletId = $transaction->warehouse?->outlet_id;
        $groups = [];

        foreach ($transaction->details as $detail) {
            if (! $detail->product) {
                continue;
            }

            $station = $this->routeFor($detail->product, $outletId);

            if (! $station) {
                $this->auditLogService->log(
                    'kitchen.ticket_skipped',
                    'kitchen',
                    $detail->product,
                    'Item tidak memiliki station dapur.',
                    null,
                    null,
                    [
                        'product_id' => $detail->product_id,
                        'outlet_id' => $outletId,
                        'source' => 'transaction',
                        'source_id' => $transaction->id,
                    ],
                );

                continue;
            }

            $groups[$station->id]['station'] = $station;
            $groups[$station->id]['items'][] = $detail;
        }

        foreach ($groups as $stationId => $group) {
            $ticket = KitchenTicket::firstOrCreate(
                [
                    'transaction_id' => $transaction->id,
                    'kitchen_station_id' => $stationId,
                ],
                [
                    'dine_order_id' => null,
                    'outlet_id' => $outletId,
                    'status' => KitchenTicket::STATUS_OPEN,
                    'order_type' => $transaction->order_type,
                    'placed_at' => now(),
                ],
            );

            foreach ($group['items'] as $detail) {
                KitchenTicketItem::firstOrCreate(
                    ['transaction_detail_id' => $detail->id],
                    [
                        'kitchen_ticket_id' => $ticket->id,
                        'dine_order_item_id' => null,
                        'product_name' => Str::limit($detail->product->title, 150, ''),
                        'qty' => (int) $detail->qty,
                        'modifiers_text' => $this->modifiersText($detail->modifiers),
                        'note' => $detail->note ? Str::limit($detail->note, 255, '') : null,
                        'status' => KitchenTicketItem::STATUS_QUEUED,
                    ],
                );
            }
        }
    }

    public function stationsFor(User $user): Collection
    {
        if ($user->hasRole('super-admin')) {
            return KitchenStation::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        }

        if ($user->can('kitchen-manage')) {
            $outletIds = $this->outletAccessService->accessibleOutlets($user)->pluck('id');

            return KitchenStation::query()
                ->where(fn ($query) => $query->whereNull('outlet_id')->orWhereIn('outlet_id', $outletIds))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        }

        return $user->kitchenStations()
            ->orderBy('kitchen_stations.sort_order')
            ->orderBy('kitchen_stations.name')
            ->get();
    }

    public function userCanAccessStation(User $user, KitchenStation $station): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->can('kitchen-manage')) {
            if ($station->outlet_id === null) {
                return true;
            }

            return $this->outletAccessService->accessibleOutlets($user)->contains('id', $station->outlet_id);
        }

        return $station->users()->whereKey($user->id)->exists();
    }

    public function boardFor(User $user, ?int $stationId = null): Collection
    {
        $query = KitchenTicket::query()
            ->with(['station', 'items', 'dineOrder.session.table'])
            ->whereIn('status', [KitchenTicket::STATUS_OPEN, KitchenTicket::STATUS_READY]);

        if ($stationId !== null) {
            $station = KitchenStation::query()->findOrFail($stationId);

            abort_unless($this->userCanAccessStation($user, $station), 403);

            $query->where('kitchen_station_id', $station->id);
        } else {
            $query->whereIn('kitchen_station_id', $this->stationsFor($user)->pluck('id'));
        }

        return $query->orderBy('placed_at')->get();
    }

    public function updateItemStatus(KitchenTicketItem $item, string $status, User $user): KitchenTicketItem
    {
        return DB::transaction(function () use ($item, $status, $user) {
            $locked = KitchenTicketItem::query()
                ->with('ticket.station')
                ->whereKey($item->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($this->userCanAccessStation($user, $locked->ticket->station), 403);
            $this->assertTicketEditable($locked->ticket);
            $this->applyItemStatus($locked, $status);
            $this->recomputeTicket($locked->ticket);

            return $locked->fresh(['ticket']);
        });
    }

    public function markTicketCooking(KitchenTicket $ticket, User $user): KitchenTicket
    {
        return DB::transaction(function () use ($ticket, $user) {
            $locked = KitchenTicket::query()->with('station')->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->userCanAccessStation($user, $locked->station), 403);
            $this->assertTicketEditable($locked);

            foreach ($locked->items()->where('status', KitchenTicketItem::STATUS_QUEUED)->get() as $item) {
                $item->update([
                    'status' => KitchenTicketItem::STATUS_COOKING,
                    'started_at' => $item->started_at ?? now(),
                ]);
            }

            $this->recomputeTicket($locked);

            return $locked->fresh(['items', 'station']);
        });
    }

    public function markTicketReady(KitchenTicket $ticket, User $user): KitchenTicket
    {
        return DB::transaction(function () use ($ticket, $user) {
            $locked = KitchenTicket::query()->with('station')->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->userCanAccessStation($user, $locked->station), 403);
            $this->assertTicketEditable($locked);

            foreach ($locked->items()->whereIn('status', [KitchenTicketItem::STATUS_QUEUED, KitchenTicketItem::STATUS_COOKING])->get() as $item) {
                $item->update([
                    'status' => KitchenTicketItem::STATUS_READY,
                    'started_at' => $item->started_at ?? now(),
                    'ready_at' => now(),
                ]);
            }

            $this->recomputeTicket($locked);

            return $locked->fresh(['items', 'station']);
        });
    }

    public function clearTicket(KitchenTicket $ticket, User $user): KitchenTicket
    {
        return DB::transaction(function () use ($ticket, $user) {
            $locked = KitchenTicket::query()->with('station')->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->userCanAccessStation($user, $locked->station), 403);

            if ($locked->status !== KitchenTicket::STATUS_READY) {
                throw ValidationException::withMessages(['ticket' => 'Tiket hanya bisa dibersihkan setelah semua item siap.']);
            }

            $locked->update(['status' => KitchenTicket::STATUS_CLOSED, 'closed_at' => now()]);

            $this->auditLogService->log(
                'kitchen.ticket_cleared',
                'kitchen',
                $locked,
                'Tiket dapur dibersihkan.',
                null,
                ['status' => KitchenTicket::STATUS_CLOSED],
                ['user_id' => $user->id],
                $user,
            );

            return $locked->fresh(['items', 'station']);
        });
    }

    public function cancelForDineOrderItem(DineOrderItem $item, string $reason, int $userId): void
    {
        DB::transaction(function () use ($item, $reason, $userId) {
            $ticketItem = KitchenTicketItem::query()
                ->with('ticket.station')
                ->where('dine_order_item_id', $item->id)
                ->lockForUpdate()
                ->first();

            if (! $ticketItem || $ticketItem->status === KitchenTicketItem::STATUS_CANCELLED) {
                return;
            }

            $wasClosed = $ticketItem->ticket->status === KitchenTicket::STATUS_CLOSED;

            $ticketItem->update([
                'status' => KitchenTicketItem::STATUS_CANCELLED,
                'cancel_reason' => Str::limit(trim($reason), 255, ''),
                'cancelled_at' => now(),
            ]);

            if ($wasClosed) {
                $remaining = $ticketItem->ticket->items()->where('status', '!=', KitchenTicketItem::STATUS_CANCELLED)->exists();

                if (! $remaining) {
                    $ticketItem->ticket->update(['status' => KitchenTicket::STATUS_CANCELLED]);
                }
            } else {
                $this->recomputeTicket($ticketItem->ticket);
            }

            $this->auditLogService->log(
                'kitchen.ticket_item_cancelled',
                'kitchen',
                $ticketItem,
                'Item dapur dibatalkan.',
                null,
                ['reason' => $reason],
                ['user_id' => $userId, 'ticket_id' => $ticketItem->kitchen_ticket_id],
            );
        });
    }

    private function applyItemStatus(KitchenTicketItem $item, string $status): void
    {
        $allowed = [
            KitchenTicketItem::STATUS_QUEUED => [KitchenTicketItem::STATUS_COOKING],
            KitchenTicketItem::STATUS_COOKING => [KitchenTicketItem::STATUS_READY, KitchenTicketItem::STATUS_QUEUED],
            KitchenTicketItem::STATUS_READY => [KitchenTicketItem::STATUS_COOKING],
            KitchenTicketItem::STATUS_CANCELLED => [],
        ];

        if (! in_array($status, $allowed[$item->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => 'Perubahan status item tidak valid.']);
        }

        $attributes = ['status' => $status];

        if ($status === KitchenTicketItem::STATUS_COOKING) {
            $attributes['started_at'] = $item->started_at ?? now();
            $attributes['ready_at'] = null;
        }

        if ($status === KitchenTicketItem::STATUS_READY) {
            $attributes['ready_at'] = now();
            $attributes['started_at'] = $item->started_at ?? now();
        }

        if ($status === KitchenTicketItem::STATUS_QUEUED) {
            $attributes['started_at'] = null;
            $attributes['ready_at'] = null;
        }

        $item->update($attributes);
    }

    private function recomputeTicket(KitchenTicket $ticket): void
    {
        $items = $ticket->items()->get();
        $active = $items->where('status', '!=', KitchenTicketItem::STATUS_CANCELLED);

        if ($active->isEmpty()) {
            $ticket->update([
                'status' => KitchenTicket::STATUS_CANCELLED,
                'ready_at' => null,
                'closed_at' => $ticket->closed_at ?? now(),
            ]);

            return;
        }

        if ($active->every(fn (KitchenTicketItem $item) => $item->status === KitchenTicketItem::STATUS_READY)) {
            $ticket->update([
                'status' => KitchenTicket::STATUS_READY,
                'ready_at' => $ticket->ready_at ?? now(),
                'closed_at' => null,
            ]);

            return;
        }

        $ticket->update([
            'status' => KitchenTicket::STATUS_OPEN,
            'ready_at' => null,
            'closed_at' => null,
        ]);
    }

    private function assertTicketEditable(KitchenTicket $ticket): void
    {
        if (in_array($ticket->status, [KitchenTicket::STATUS_CLOSED, KitchenTicket::STATUS_CANCELLED], true)) {
            throw ValidationException::withMessages(['ticket' => 'Tiket sudah ditutup.']);
        }
    }

    private function modifiersText(Collection $modifiers): ?string
    {
        $text = $modifiers->pluck('name')->filter()->implode(', ');

        return $text === '' ? null : Str::limit($text, 500, '');
    }
}
