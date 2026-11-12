<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_plans_are_seeded_with_correct_quotas_and_features(): void
    {
        $starter = Plan::where('slug', 'starter')->first();
        $this->assertNotNull($starter);
        $this->assertEquals(1, $starter->max_outlets);
        $this->assertEquals(2, $starter->max_users);
        $this->assertEquals(500, $starter->max_products);
        $this->assertTrue($starter->hasFeature('basic_pos'));
        $this->assertFalse($starter->hasFeature('multi_warehouse'));

        $pro = Plan::where('slug', 'pro')->first();
        $this->assertNotNull($pro);
        $this->assertEquals(3, $pro->max_outlets);
        $this->assertEquals(10, $pro->max_users);
        $this->assertTrue($pro->hasFeature('multi_warehouse'));
        $this->assertTrue($pro->hasFeature('crm'));

        $enterprise = Plan::where('slug', 'enterprise')->first();
        $this->assertNotNull($enterprise);
        $this->assertTrue($enterprise->hasFeature('accounting_journal'));
        $this->assertTrue($enterprise->hasFeature('api_access'));
    }

    public function test_tenant_creation_and_relations(): void
    {
        $plan = Plan::where('slug', 'pro')->first();

        $owner = User::factory()->create([
            'name' => 'Owner Toko',
            'email' => 'owner@tokobaru.com',
        ]);

        $tenant = Tenant::create([
            'name' => 'Toko Baru',
            'subdomain' => 'tokobaru',
            'plan_id' => $plan->id,
            'owner_id' => $owner->id,
            'status' => 'active',
            'subscription_ends_at' => now()->addMonth(),
        ]);

        $owner->update(['tenant_id' => $tenant->id]);

        $this->assertTrue($tenant->isActive());
        $this->assertFalse($tenant->isExpired());
        $this->assertTrue($tenant->hasFeature('multi_warehouse'));
        $this->assertEquals($tenant->id, $owner->tenant->id);
    }

    public function test_tenant_subscription_recording(): void
    {
        $plan = Plan::where('slug', 'starter')->first();
        $tenant = Tenant::create([
            'name' => 'Kios Mini',
            'subdomain' => 'kiosmini',
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $sub = TenantSubscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'amount' => 99000,
            'payment_status' => 'paid',
            'payment_method' => 'qris',
            'gateway_reference' => 'ORDER-12345',
            'paid_at' => now(),
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $this->assertTrue($sub->isPaid());
        $this->assertEquals($tenant->id, $sub->tenant->id);
        $this->assertEquals(1, $tenant->subscriptions()->count());
    }

    public function test_core_tables_contain_tenant_id_column(): void
    {
        $checkedTables = [
            'users',
            'outlets',
            'warehouses',
            'categories',
            'products',
            'customers',
            'suppliers',
            'transactions',
            'transaction_details',
            'carts',
            'cashier_shifts',
            'stock_mutations',
            'settings',
        ];

        foreach ($checkedTables as $table) {
            $this->assertTrue(
                Schema::hasColumn($table, 'tenant_id'),
                "Table '{$table}' is missing 'tenant_id' column."
            );
        }
    }
}
