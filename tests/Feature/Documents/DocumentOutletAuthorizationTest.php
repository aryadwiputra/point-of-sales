<?php

namespace Tests\Feature\Documents;

use App\Models\Outlet;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DocumentOutletAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_invoice_document_rejects_transaction_from_another_outlet(): void
    {
        $outletA = Outlet::create(['code' => 'DOA', 'name' => 'Document A']);
        $outletB = Outlet::create(['code' => 'DOB', 'name' => 'Document B']);
        $warehouseB = Warehouse::create([
            'code' => 'WH-DOB',
            'name' => 'Warehouse B',
            'type' => 'branch',
            'is_active' => true,
            'sort_order' => 0,
            'outlet_id' => $outletB->id,
        ]);
        $user = User::factory()->create();
        $user->markEmailAsVerified();
        $user->outlets()->attach($outletA->id, ['is_default' => true]);
        $user->givePermissionTo(Permission::firstOrCreate([
            'name' => 'transactions-access',
            'guard_name' => 'web',
        ]));

        $transaction = Transaction::create([
            'cashier_id' => $user->id,
            'warehouse_id' => $warehouseB->id,
            'invoice' => 'INV-DOC-CROSS-OUTLET',
            'cash' => 0,
            'change' => 0,
            'discount' => 0,
            'grand_total' => 10000,
            'total' => 10000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $this->actingAs($user)
            ->get(route('pdf.transactions.invoice', $transaction->invoice))
            ->assertNotFound();
    }
}
