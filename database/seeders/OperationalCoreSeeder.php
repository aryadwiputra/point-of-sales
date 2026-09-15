<?php

namespace Database\Seeders;

use App\Models\CashierShift;
use App\Models\CustomerCredit;
use App\Models\Profit;
use App\Models\SalesReturn;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashierShiftService;
use App\Services\StockMutationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OperationalCoreSeeder extends Seeder
{
    public function run(): void
    {
        if (
            ! Schema::hasTable('cashier_shifts')
            || ! Schema::hasTable('sales_returns')
            || ! Schema::hasTable('sales_return_items')
        ) {
            $this->command?->warn('Skipping OperationalCoreSeeder because required tables do not exist.');

            return;
        }

        $cashier = User::where('email', 'cashier@gmail.com')->first() ?? User::first();
        $supervisor = User::where('email', 'arya@gmail.com')->first() ?? $cashier;
        $warehouse = Warehouse::where('code', 'WH-MAL')->first();

        if (! $cashier || ! $supervisor || ! $warehouse) {
            $this->command?->warn('Skipping OperationalCoreSeeder because seed users are missing.');

            return;
        }

        $transactions = Transaction::with(['details.product', 'customer'])
            ->orderBy('id')
            ->get();

        if ($transactions->count() < 3) {
            $this->command?->warn('Skipping OperationalCoreSeeder because sample transactions are not available.');

            return;
        }

        $this->command?->info('Seeding cashier shifts and sales returns...');

        DB::transaction(function () use ($cashier, $supervisor, $warehouse, $transactions) {
            SalesReturn::query()->delete();
            CustomerCredit::query()->delete();
            CashierShift::query()->delete();

            Transaction::query()->update(['cashier_shift_id' => null]);

            $today = now();
            $twoDaysAgoOpen = $today->copy()->subDays(2)->setTime(8, 0);
            $twoDaysAgoClose = $today->copy()->subDays(2)->setTime(15, 30);
            $yesterdayOpen = $today->copy()->subDay()->setTime(9, 0);
            $yesterdayClose = $today->copy()->subDay()->setTime(17, 15);
            $todayOpen = $today->copy()->setTime(8, 0);

            $historicalShift = CashierShift::create([
                'user_id' => $cashier->id,
                'opened_by' => $supervisor->id,
                'opened_at' => $twoDaysAgoOpen,
                'warehouse_id' => $warehouse->id,
                'opening_cash' => 175000,
                'expected_cash' => 175000,
                'notes' => 'Shift pagi weekday untuk sample histori.',
                'status' => CashierShift::STATUS_OPEN,
            ]);

            $forceClosedShift = CashierShift::create([
                'user_id' => $cashier->id,
                'opened_by' => $cashier->id,
                'opened_at' => $yesterdayOpen,
                'warehouse_id' => $warehouse->id,
                'opening_cash' => 200000,
                'expected_cash' => 200000,
                'notes' => 'Shift sore yang nanti ditutup supervisor.',
                'status' => CashierShift::STATUS_OPEN,
            ]);

            $activeShift = CashierShift::create([
                'user_id' => $cashier->id,
                'opened_by' => $cashier->id,
                'opened_at' => $todayOpen,
                'warehouse_id' => $warehouse->id,
                'opening_cash' => 250000,
                'expected_cash' => 250000,
                'notes' => 'Shift aktif hari ini.',
                'status' => CashierShift::STATUS_OPEN,
            ]);

            $historicalTransactions = $transactions->take(2)->values();
            $forceClosedTransactions = $transactions->slice(2, 2)->values();
            $activeTransactions = $transactions->slice(4)->values();

            $this->assignTransactionsToShift($historicalTransactions, $historicalShift, $twoDaysAgoOpen);
            $this->assignTransactionsToShift($forceClosedTransactions, $forceClosedShift, $yesterdayOpen);
            $this->assignTransactionsToShift($activeTransactions, $activeShift, $todayOpen);

            $this->seedSalesReturns(
                transactions: $transactions,
                cashier: $cashier,
                historicalShift: $historicalShift,
                activeShift: $activeShift,
            );

            $this->closeShiftWithOffset(
                $historicalShift,
                $cashier,
                $twoDaysAgoClose,
                -5000,
                'Closing normal dengan selisih kurang kecil.',
                false,
            );

            $this->closeShiftWithOffset(
                $forceClosedShift,
                $supervisor,
                $yesterdayClose,
                7000,
                'Supervisor menutup shift terlambat dengan selisih lebih.',
                true,
            );
        });
    }

    private function assignTransactionsToShift(Collection $transactions, CashierShift $shift, $openedAt): void
    {
        foreach ($transactions->values() as $index => $transaction) {
            $timestamp = $openedAt->copy()->addHours($index + 1);

            $transaction->update([
                'cashier_shift_id' => $shift->id,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $transaction->details()->update([
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $transaction->profits()->update([
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }
    }

    private function seedSalesReturns(
        Collection $transactions,
        User $cashier,
        CashierShift $historicalShift,
        CashierShift $activeShift
    ): void {
        $stockMutationService = app(StockMutationService::class);

        $creditTransaction = $transactions
            ->first(fn (Transaction $transaction) => $transaction->customer_id !== null && $transaction->payment_method !== 'cash');

        if ($creditTransaction) {
            $detail = $creditTransaction->details->first();
            if ($detail && $detail->product) {
                $this->createCompletedSalesReturn(
                    transaction: $creditTransaction,
                    detail: $detail,
                    cashier: $cashier,
                    shift: $historicalShift,
                    type: 'store_credit',
                    returnReason: 'Pelanggan menerima credit note untuk item yang tidak sesuai.',
                    notes: 'Retur sample untuk module 3 dengan store credit.',
                    stockMutationService: $stockMutationService,
                );
            }
        }

        $cashTransaction = $transactions
            ->first(fn (Transaction $transaction) => $transaction->customer_id !== null && $transaction->payment_method === 'cash');

        if ($cashTransaction) {
            $detail = $cashTransaction->details->first();
            if ($detail && $detail->product) {
                $this->createCompletedSalesReturn(
                    transaction: $cashTransaction,
                    detail: $detail,
                    cashier: $cashier,
                    shift: $activeShift,
                    type: 'refund_cash',
                    returnReason: 'Barang dikembalikan dan dana dikembalikan tunai.',
                    notes: 'Retur sample untuk cash refund.',
                    stockMutationService: $stockMutationService,
                );
            }
        }

        $draftTransaction = $transactions
            ->first(fn (Transaction $transaction) => $transaction->customer_id !== null && $transaction->details->count() > 1);

        if ($draftTransaction) {
            $detail = $draftTransaction->details->last();
            if ($detail && $detail->product) {
                $unitPrice = (int) round($detail->price / max(1, $detail->qty));

                $draftReturn = SalesReturn::create([
                    'code' => $this->generateSalesReturnCode(),
                    'transaction_id' => $draftTransaction->id,
                    'customer_id' => $draftTransaction->customer_id,
                    'cashier_id' => $cashier->id,
                    'status' => 'draft',
                    'return_type' => 'refund_cash',
                    'refund_amount' => $unitPrice,
                    'credited_amount' => 0,
                    'total_return_amount' => $unitPrice,
                    'notes' => 'Draft retur sample yang belum difinalisasi.',
                ]);

                $draftReturn->items()->create([
                    'transaction_detail_id' => $detail->id,
                    'product_id' => $detail->product_id,
                    'qty_sold' => (int) $detail->qty,
                    'qty_returned_before' => 0,
                    'qty_return' => 1,
                    'unit_price' => $unitPrice,
                    'subtotal' => $unitPrice,
                    'return_reason' => 'Menunggu konfirmasi retur dari admin.',
                    'restock_to_inventory' => true,
                ]);
            }
        }
    }

    private function createCompletedSalesReturn(
        Transaction $transaction,
        TransactionDetail $detail,
        User $cashier,
        CashierShift $shift,
        string $type,
        string $returnReason,
        string $notes,
        StockMutationService $stockMutationService
    ): void {
        $product = $detail->product;
        if (! $product) {
            return;
        }

        $qtyReturn = min(1, (int) $detail->qty);
        $unitPrice = (int) round($detail->price / max(1, $detail->qty));
        $subtotal = $unitPrice * $qtyReturn;
        $completedAt = $shift->opened_at->copy()->addHours(3);

        $salesReturn = SalesReturn::create([
            'code' => $this->generateSalesReturnCode(),
            'transaction_id' => $transaction->id,
            'customer_id' => $transaction->customer_id,
            'cashier_id' => $cashier->id,
            'cashier_shift_id' => $shift->id,
            'status' => 'completed',
            'return_type' => $type,
            'refund_amount' => $type === 'refund_cash' ? $subtotal : 0,
            'credited_amount' => $type === 'store_credit' ? $subtotal : 0,
            'total_return_amount' => $subtotal,
            'notes' => $notes,
            'completed_at' => $completedAt,
            'created_at' => $completedAt,
            'updated_at' => $completedAt,
        ]);

        $salesReturn->items()->create([
            'transaction_detail_id' => $detail->id,
            'product_id' => $detail->product_id,
            'qty_sold' => (int) $detail->qty,
            'qty_returned_before' => 0,
            'qty_return' => $qtyReturn,
            'unit_price' => $unitPrice,
            'subtotal' => $subtotal,
            'return_reason' => $returnReason,
            'restock_to_inventory' => true,
            'created_at' => $completedAt,
            'updated_at' => $completedAt,
        ]);

        $stockBefore = (int) $product->stock;
        $stockAfter = $stockBefore + $qtyReturn;
        $product->update(['stock' => $stockAfter]);

        $stockMutationService->recordSalesReturnRestock(
            product: $product,
            salesReturn: $salesReturn,
            stockBefore: $stockBefore,
            stockAfter: $stockAfter,
            reason: $returnReason,
            userId: $cashier->id,
        );

        $margin = ($unitPrice - (int) $product->buy_price) * $qtyReturn;
        Profit::create([
            'transaction_id' => $transaction->id,
            'total' => -$margin,
            'created_at' => $completedAt,
            'updated_at' => $completedAt,
        ]);

        if ($type === 'store_credit' && $transaction->customer_id) {
            CustomerCredit::create([
                'customer_id' => $transaction->customer_id,
                'sales_return_id' => $salesReturn->id,
                'amount' => $subtotal,
                'balance' => $subtotal,
                'notes' => 'Saldo toko sample dari retur '.$salesReturn->code,
                'created_at' => $completedAt,
                'updated_at' => $completedAt,
            ]);
        }
    }

    private function closeShiftWithOffset(
        CashierShift $shift,
        User $actor,
        $closedAt,
        int $differenceOffset,
        string $closeNotes,
        bool $forceClose
    ): void {
        $cashierShiftService = app(CashierShiftService::class);
        $summary = $cashierShiftService->calculateSummary($shift);
        $actualCash = max(0, $summary['expected_cash'] + $differenceOffset);

        $cashierShiftService->closeShift(
            shift: $shift,
            actor: $actor,
            actualCash: $actualCash,
            closeNotes: $closeNotes,
            forceClose: $forceClose,
        );

        $shift->refresh();
        $shift->timestamps = false;
        $shift->update([
            'opened_at' => $shift->opened_at,
            'closed_at' => $closedAt,
            'created_at' => $shift->opened_at,
            'updated_at' => $closedAt,
        ]);
    }

    private function generateSalesReturnCode(): string
    {
        do {
            $code = 'SR-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
        } while (SalesReturn::where('code', $code)->exists());

        return $code;
    }
}
