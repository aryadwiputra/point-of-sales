<?php

namespace Tests\Feature\Console;

use App\Models\Outlet;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedDemoCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_seeder_is_production_safe(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, Outlet::count());
        $this->assertSame(1, Warehouse::count());
        $this->assertSame('PUSAT', Warehouse::first()->code);
        $this->assertFalse((bool) Outlet::first()->is_sales_enabled);
        $this->assertNull(User::where('email', 'arya@gmail.com')->first());
        $this->assertSame(0, Product::count());
        $this->assertFalse(Setting::getBool('app_setup_completed'));
    }

    public function test_seed_demo_force_regenerates_full_demo_dataset(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->artisan('seed:demo', ['--force' => true])->assertSuccessful();

        $admin = User::where('email', 'arya@gmail.com')->first();

        $this->assertNotNull($admin);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue($admin->hasRole('super-admin'));

        $cashier = User::where('email', 'cashier@gmail.com')->first();

        $this->assertNotNull($cashier);
        $this->assertNotNull($cashier->email_verified_at);

        $this->assertGreaterThan(0, Product::count());
        $this->assertSame(4, Outlet::count());
        $this->assertSame(4, Warehouse::count());
        $this->assertTrue(Setting::getBool('app_setup_completed'));
        $this->assertFalse((bool) Warehouse::where('code', 'PUSAT')->first()->outlet->is_sales_enabled);
        $this->assertSame(0, Transaction::where('warehouse_id', Warehouse::where('code', 'PUSAT')->value('id'))->count());
        $this->assertGreaterThan(0, Transaction::whereNotNull('warehouse_id')->count());

        $counts = [
            'outlets' => Outlet::count(),
            'warehouses' => Warehouse::count(),
            'products' => Product::count(),
            'transactions' => Transaction::count(),
        ];

        $this->artisan('db:seed', ['--class' => 'DemoSeeder', '--force'])
            ->assertSuccessful();

        $this->assertSame($counts, [
            'outlets' => Outlet::count(),
            'warehouses' => Warehouse::count(),
            'products' => Product::count(),
            'transactions' => Transaction::count(),
        ]);
    }

    public function test_seed_demo_without_confirmation_aborts(): void
    {
        $this->artisan('seed:demo')
            ->expectsConfirmation('Continue?', 'no')
            ->assertFailed();

        $this->assertNull(User::where('email', 'arya@gmail.com')->first());
        $this->assertSame(0, Product::count());
    }
}
