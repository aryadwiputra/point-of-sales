<?php

namespace Tests\Feature\Console;

use App\Models\Outlet;
use App\Models\PaymentSetting;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

        $manager = User::where('email', 'manager@gmail.com')->first();

        $this->assertNotNull($manager);
        $this->assertTrue($manager->hasRole('manager'));
        $this->assertFalse($manager->hasRole('super-admin'));
        $this->assertSame(2, $manager->outlets()->count());

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

        $this->assertSame(0, DB::table('stock_mutations')->where('reference_type', 'demo_reset_sentinel')->count());
        $this->assertInventoryAggregatesMatchWarehouseStock();

        $this->assertSame($counts, [
            'outlets' => Outlet::count(),
            'warehouses' => Warehouse::count(),
            'products' => Product::count(),
            'transactions' => Transaction::count(),
        ]);
    }

    private function assertInventoryAggregatesMatchWarehouseStock(): void
    {
        $inconsistent = DB::table('products')
            ->leftJoin('product_warehouse', 'products.id', '=', 'product_warehouse.product_id')
            ->select('products.id', 'products.stock')
            ->selectRaw('COALESCE(SUM(product_warehouse.stock), 0) as warehouse_stock')
            ->groupBy('products.id', 'products.stock')
            ->havingRaw('products.stock != COALESCE(SUM(product_warehouse.stock), 0)')
            ->exists();

        $this->assertFalse($inconsistent);
    }

    public function test_demo_seed_resets_application_data_but_preserves_schema_and_reference_data(): void
    {
        DB::table('users')->insert([
            'name' => 'Existing user',
            'email' => 'existing@example.test',
            'password' => bcrypt('secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productId = DB::table('products')->insertGetId([
            'category_id' => DB::table('categories')->insertGetId(['name' => 'Stale category', 'created_at' => now(), 'updated_at' => now()]),
            'image' => '',
            'description' => '',
            'title' => 'Stale product',
            'stock' => 0,
            'buy_price' => 1,
            'sell_price' => 2,
            'barcode' => 'stale-product',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('stock_mutations')->insert([
            'product_id' => $productId,
            'reference_type' => 'demo_reset_sentinel',
            'mutation_type' => 'in',
            'qty' => 1,
            'stock_before' => 0,
            'stock_after' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Setting::set('custom_setting', 'keep-me');
        Setting::set('dine_in_enabled', '0');
        $paymentSetting = PaymentSetting::create([
            'default_gateway' => 'midtrans',
            'midtrans_enabled' => true,
            'midtrans_server_key' => 'preserved-secret',
        ]);
        $migrationCount = DB::table('migrations')->count();
        $provinceCount = DB::table('indonesia_provinces')->count();

        $this->artisan('seed:demo', ['--force' => true])->assertSuccessful();

        $this->assertSame(0, User::where('email', 'existing@example.test')->count());
        $this->assertSame(0, Product::where('title', 'Stale product')->count());
        $this->assertSame(0, DB::table('stock_mutations')->where('reference_type', 'demo_reset_sentinel')->count());
        $this->assertSame('keep-me', Setting::get('custom_setting'));
        $this->assertSame('0', Setting::get('dine_in_enabled'));
        $this->assertSame($migrationCount, DB::table('migrations')->count());
        $this->assertSame($provinceCount, DB::table('indonesia_provinces')->count());
        $this->assertSame(1, PaymentSetting::count());
        $this->assertSame('midtrans', PaymentSetting::first()->default_gateway);
        $this->assertSame('preserved-secret', PaymentSetting::first()->midtrans_server_key);
    }

    public function test_seed_demo_rejects_production_even_with_force(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('seed:demo', ['--force' => true])
            ->expectsOutput('Demo seeding is disabled in production.')
            ->assertFailed();

        $this->assertSame(0, Product::count());
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
