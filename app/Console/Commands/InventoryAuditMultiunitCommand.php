<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryAuditMultiunitCommand extends Command
{
    protected $signature = 'inventory:audit-multiuint';

    protected $description = 'Read-only audit of stock drift caused by multi-unit (conversion_factor > 1) sales returns and expired transactions';

    public function handle(): int
    {
        $this->info('Multi-unit stock audit (read-only)');

        $salesReturns = $this->salesReturnCandidates();
        $expirations = $this->expirationCandidates();
        $batchDrift = $this->batchDivergence();

        $this->newLine();
        $this->reportSalesReturns($salesReturns);
        $this->reportExpirations($expirations);
        $this->reportBatchDrift($batchDrift);

        if ($salesReturns->isEmpty() && $expirations->isEmpty() && $batchDrift->isEmpty()) {
            $this->newLine();
            $this->info('No multi-unit drift candidates found.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('Read-only audit — no data was modified.');

        return self::SUCCESS;
    }

    /**
     * Completed sales returns that restocked with a conversion factor > 1.
     * Before the fix these restocked selling-unit qty instead of base units.
     */
    private function salesReturnCandidates(): Collection
    {
        return DB::table('sales_return_items as sri')
            ->join('sales_returns as sr', 'sr.id', '=', 'sri.sales_return_id')
            ->join('transaction_details as td', 'td.id', '=', 'sri.transaction_detail_id')
            ->join('products as p', 'p.id', '=', 'sri.product_id')
            ->where('sr.status', 'completed')
            ->where('sri.restock_to_inventory', true)
            ->where('td.conversion_factor', '>', 1)
            ->orderBy('sr.id')
            ->select(
                'sr.code',
                'sri.product_id',
                'p.title',
                'sri.qty_return',
                'td.conversion_factor',
            )
            ->get();
    }

    /**
     * Auto-expired gateway transactions whose details carry a conversion factor > 1.
     * Before the fix these restocked selling-unit qty instead of base units.
     */
    private function expirationCandidates(): Collection
    {
        return DB::table('transaction_details as td')
            ->join('transactions as t', 't.id', '=', 'td.transaction_id')
            ->join('products as p', 'p.id', '=', 'td.product_id')
            ->whereIn('t.payment_method', ['midtrans', 'xendit', 'qris', 'bank_transfer'])
            ->where('t.payment_status', 'failed')
            ->where('t.note', 'like', '%Dibatalkan otomatis%')
            ->where('td.conversion_factor', '>', 1)
            ->orderBy('t.id')
            ->select(
                't.invoice',
                'td.product_id',
                'p.title',
                'td.qty',
                'td.conversion_factor',
            )
            ->get();
    }

    /**
     * Warehouses where the pivot stock diverges from the sum of its batch ledger.
     * Only considers products that actually have batch rows in that warehouse.
     */
    private function batchDivergence(): Collection
    {
        return DB::table('product_warehouse as pw')
            ->join('products as p', 'p.id', '=', 'pw.product_id')
            ->leftJoin('product_batches as pb', function ($join) {
                $join->on('pb.product_id', '=', 'pw.product_id')
                    ->on('pb.warehouse_id', '=', 'pw.warehouse_id');
            })
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('product_batches as pbx')
                    ->whereColumn('pbx.product_id', 'pw.product_id')
                    ->whereColumn('pbx.warehouse_id', 'pw.warehouse_id');
            })
            ->groupBy('pw.product_id', 'pw.warehouse_id', 'p.title', 'pw.stock')
            ->havingRaw('pw.stock <> COALESCE(SUM(pb.stock), 0)')
            ->orderBy('pw.product_id')
            ->select(
                'pw.product_id',
                'pw.warehouse_id',
                'p.title',
                'pw.stock as pivot_stock',
                DB::raw('COALESCE(SUM(pb.stock), 0) as batch_stock'),
            )
            ->get();
    }

    private function reportSalesReturns(Collection $rows): void
    {
        $this->warn("Sales returns restocked with a conversion factor > 1: {$rows->count()}");

        foreach ($rows as $row) {
            $shortfall = (int) round((int) $row->qty_return * ((float) $row->conversion_factor - 1));

            $this->line(sprintf(
                '  %s — product #%d %s, qty_return %d x factor %s (estimated under-restock: %d base unit)',
                $row->code,
                (int) $row->product_id,
                $row->title,
                (int) $row->qty_return,
                (float) $row->conversion_factor,
                $shortfall,
            ));
        }
    }

    private function reportExpirations(Collection $rows): void
    {
        $this->warn("Expired gateway transactions with a conversion factor > 1: {$rows->count()}");

        foreach ($rows as $row) {
            $shortfall = (int) round((int) $row->qty * ((float) $row->conversion_factor - 1));

            $this->line(sprintf(
                '  %s — product #%d %s, qty %d x factor %s (estimated under-restock: %d base unit)',
                $row->invoice,
                (int) $row->product_id,
                $row->title,
                (int) $row->qty,
                (float) $row->conversion_factor,
                $shortfall,
            ));
        }
    }

    private function reportBatchDrift(Collection $rows): void
    {
        $this->warn("Warehouse pivot vs batch ledger divergence: {$rows->count()}");

        foreach ($rows as $row) {
            $this->line(sprintf(
                '  product #%d %s, warehouse #%d — pivot: %d, batch total: %d',
                (int) $row->product_id,
                $row->title,
                (int) $row->warehouse_id,
                (int) $row->pivot_stock,
                (int) $row->batch_stock,
            ));
        }
    }
}
