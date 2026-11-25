<?php

namespace App\Console\Commands;

use App\Models\DineBill;
use App\Models\DineOrder;
use App\Models\DineSession;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillDineBills extends Command
{
    protected $signature = 'dine:backfill-bills {--dry-run}';

    protected $description = 'Create closed sessions and settled historical bills for legacy accepted dine-in orders';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $count = 0;

        DineOrder::query()
            ->where('status', DineOrder::STATUS_ACCEPTED)
            ->whereNotNull('transaction_id')
            ->whereNull('dine_session_id')
            ->with(['items', 'table.area'])
            ->orderBy('id')
            ->chunkById(100, function ($orders) use ($dryRun, &$count) {
                foreach ($orders as $order) {
                    if (DineBill::where('transaction_id', $order->transaction_id)->exists()) {
                        continue;
                    }

                    $transaction = Transaction::find($order->transaction_id);

                    if (! $transaction) {
                        continue;
                    }

                    if ($dryRun) {
                        $this->line("Would backfill order #{$order->id} ({$transaction->invoice})");
                        $count++;

                        continue;
                    }

                    DB::transaction(function () use ($order, $transaction) {
                        $session = DineSession::create([
                            'dine_table_id' => $order->dine_table_id,
                            'status' => DineSession::STATUS_CLOSED,
                            'opened_by' => $order->cashier_id,
                            'opened_at' => $order->submitted_at ?? $order->created_at ?? now(),
                            'closed_at' => $transaction->created_at ?? now(),
                        ]);

                        $bill = DineBill::create([
                            'dine_session_id' => $session->id,
                            'sequence' => 1,
                            'status' => DineBill::STATUS_SETTLED,
                            'customer_id' => $order->customer_id,
                            'subtotal' => (int) $order->subtotal,
                            'discount_total' => (int) $transaction->discount,
                            'tax_total' => (int) $transaction->tax_total,
                            'grand_total' => (int) $transaction->grand_total,
                            'transaction_id' => $transaction->id,
                            'settled_at' => $transaction->created_at ?? now(),
                            'created_by' => $order->cashier_id,
                        ]);

                        $order->items()->update(['dine_bill_id' => $bill->id]);
                        $order->update([
                            'dine_session_id' => $session->id,
                            'source' => DineOrder::SOURCE_QR,
                            'status' => DineOrder::STATUS_COMPLETED,
                        ]);
                    });

                    $count++;
                }
            });

        $this->info(($dryRun ? 'Dry run: ' : 'Backfilled ').$count.' order(s).');

        return self::SUCCESS;
    }
}
