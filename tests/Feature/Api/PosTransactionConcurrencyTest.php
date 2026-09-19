<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class PosTransactionConcurrencyTest extends PosTransactionSyncTest
{
    public function test_unique_violation_on_client_uuid_resolves_as_duplicate(): void
    {
        $this->actingAsPosCashier();

        // Simulate the losing side of a race: another request (e.g. from a
        // second worker or a retry) has already committed a transaction with
        // this client_uuid between our pre-check and our checkout attempt.
        Transaction::create([
            'cashier_id' => $this->cashier->id,
            'invoice' => 'TRX-RACE00001',
            'cash' => 0,
            'change' => 0,
            'discount' => 0,
            'grand_total' => 20000,
            'client_uuid' => $this->syncPayload()['client_uuid'],
        ]);

        $response = $this->postJson('/api/v1/pos/transactions/sync', [
            'transactions' => [$this->syncPayload()],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.results.0.status', 'duplicate');

        $this->assertSame(1, Transaction::count());
        $this->assertEquals(50, $this->product->fresh()->stock);
        $this->assertSame(0, Cart::count());
    }

    public function test_unique_violation_with_different_fingerprint_resolves_as_conflict(): void
    {
        $this->actingAsPosCashier();

        $payload = $this->syncPayload();

        Transaction::create([
            'cashier_id' => $this->cashier->id,
            'invoice' => 'TRX-RACE00002',
            'cash' => 0,
            'change' => 0,
            'discount' => 0,
            'grand_total' => 20000,
            'client_uuid' => $payload['client_uuid'],
            'sync_fingerprint' => hash('sha256', 'different-payload'),
        ]);

        $response = $this->postJson('/api/v1/pos/transactions/sync', [
            'transactions' => [$payload],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.results.0.status', 'conflict');

        $this->assertSame(1, Transaction::count());
        $this->assertEquals(50, $this->product->fresh()->stock);
    }

    public function test_client_uuid_unique_index_blocks_double_insert(): void
    {
        $data = [
            'cashier_id' => $this->cashier->id,
            'invoice' => 'TRX-RACE00003',
            'cash' => 0,
            'change' => 0,
            'discount' => 0,
            'grand_total' => 1000,
            'client_uuid' => '550e8400-e29b-41d4-a716-446655440000',
        ];

        Transaction::create($data);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::beginTransaction();
        try {
            Transaction::create($data);
        } finally {
            DB::rollBack();
        }
    }

    protected function actingAsPosCashier(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->cashier, ['*']);

        app(\App\Services\CashierShiftService::class)->openShift(
            cashier: $this->cashier,
            actor: $this->cashier,
            openingCash: 100000,
            notes: null,
            warehouseId: $this->warehouse->id
        );
    }
}
