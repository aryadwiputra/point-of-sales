<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\StockMutationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpirePendingTransactionsCommand extends Command
{
    protected $signature = 'transactions:expire {--hours=24} {--dry-run}';

    protected $description = 'Expire gateway transactions stuck in pending payment and restock their items';

    public function handle(StockMutationService $stockMutationService): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $dryRun = (bool) $this->option('dry-run');

        $cutoff = now()->subHours($hours);

        $transactions = Transaction::query()
            ->whereIn('payment_method', ['midtrans', 'xendit', 'qris', 'bank_transfer'])
            ->where('payment_status', 'pending')
            ->where('created_at', '<', $cutoff)
            ->with(['details.product.components', 'details.batchAllocations'])
            ->orderBy('id')
            ->get();

        if ($transactions->isEmpty()) {
            $this->info('No pending gateway transactions older than '.$hours.' hours.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            foreach ($transactions as $transaction) {
                $this->line("[dry-run] {$transaction->invoice} ({$transaction->payment_method}, created {$transaction->created_at})");
            }
            $this->info($transactions->count().' transaction(s) would be expired.');

            return self::SUCCESS;
        }

        $expired = 0;

        foreach ($transactions as $transaction) {
            try {
                DB::transaction(function () use ($transaction, $stockMutationService, &$expired) {
                    $locked = Transaction::query()
                        ->lockForUpdate()
                        ->with(['details.product.components', 'details.batchAllocations'])
                        ->findOrFail($transaction->id);

                    // Lost the race with a webhook: status changed meanwhile.
                    if ($locked->payment_status !== 'pending') {
                        return;
                    }

                    foreach ($locked->details as $detail) {
                        $product = $detail->product;

                        if (! $product) {
                            continue;
                        }

                        $warehouseId = $locked->warehouse_id;

                        // Stock effect is in base units: selling qty x conversion factor.
                        $baseQty = $detail->baseQuantity();

                        $this->restock($product, $warehouseId, $baseQty);

                        foreach ($detail->batchAllocations as $allocation) {
                            DB::table('product_batches')
                                ->where('id', $allocation->product_batch_id)
                                ->increment('stock', $allocation->qty);
                        }

                        $stockMutationService->recordMutation(
                            product: $product,
                            warehouseId: $warehouseId,
                            referenceType: 'transaction_expire',
                            referenceId: $locked->id,
                            mutationType: 'in',
                            qty: $baseQty,
                            stockBefore: $this->stockAfterBefore($product, $warehouseId, $baseQty),
                            stockAfter: $this->stockAfterBefore($product, $warehouseId, 0),
                            notes: "Auto-expire pembayaran {$locked->invoice}",
                            userId: null,
                        );
                    }

                    $locked->update([
                        'payment_status' => 'failed',
                        'note' => trim(($locked->note ? $locked->note.' | ' : '').
                            'Dibatalkan otomatis: pembayaran tidak diterima dalam batas waktu.'),
                    ]);

                    $expired++;
                });
            } catch (\Throwable $e) {
                report($e);
                $this->error("Failed to expire {$transaction->invoice}: {$e->getMessage()}");
            }
        }

        $this->info("Expired {$expired} transaction(s).");

        return self::SUCCESS;
    }

    private function restock($product, ?int $warehouseId, int $qty): void
    {
        if ($product->is_composite) {
            foreach ($product->components as $component) {
                $componentQty = (int) round((float) $component->pivot->qty * $qty);
                $this->restock($component, $warehouseId, $componentQty);
            }

            return;
        }

        if ($warehouseId) {
            DB::table('product_warehouse')
                ->where('product_id', $product->id)
                ->where('warehouse_id', $warehouseId)
                ->increment('stock', $qty);
        }

        $product->increment('stock', $qty);
    }

    // ponytail: re-reads stock per detail; fine for an hourly batch of few rows
    private function stockAfterBefore($product, ?int $warehouseId, int $offset): int
    {
        $current = (int) ($warehouseId
            ? DB::table('product_warehouse')
                ->where('product_id', $product->id)
                ->where('warehouse_id', $warehouseId)
                ->value('stock')
            : $product->fresh()->stock);

        return $current - $offset;
    }
}
