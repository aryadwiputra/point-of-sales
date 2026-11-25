<?php

namespace App\Services;

use App\Models\DineOrder;
use App\Models\KitchenStation;
use App\Models\KitchenStationCategory;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Product;
use App\Models\ProductKitchenStationOverride;
use App\Models\Setting;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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

    private function modifiersText(Collection $modifiers): ?string
    {
        $text = $modifiers->pluck('name')->filter()->implode(', ');

        return $text === '' ? null : Str::limit($text, 500, '');
    }
}
