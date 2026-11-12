<?php

namespace Database\Seeders;

use App\Models\Outlet;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Services\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Laravolt\Indonesia\Models\Province;
use Laravolt\Indonesia\Seeds\IndonesiaSeeder;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->call([
            PlanSeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
            PaymentSettingSeeder::class,
            DineInSettingsSeeder::class,
        ]);

        if (! app()->environment('testing') && Province::count() === 0) {
            $this->call(IndonesiaSeeder::class);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $proPlan = Plan::where('slug', 'pro')->first();
        $defaultTenant = Tenant::firstOrCreate(
            ['subdomain' => 'default'],
            [
                'name' => 'Berkah Jaya Niaga',
                'plan_id' => $proPlan?->id,
                'status' => 'active',
                'trial_ends_at' => now()->addDays(30),
                'subscription_ends_at' => now()->addYear(),
            ]
        );
        TenantContext::set($defaultTenant);

        $this->seedDefaultWarehouse();

        TenantContext::reset();
    }

    private function seedDefaultWarehouse(): void
    {
        $pusat = Warehouse::firstOrCreate(
            ['code' => 'PUSAT'],
            [
                'name' => 'Gudang Pusat',
                'type' => 'main',
                'is_active' => true,
                'sort_order' => 0,
            ],
        );

        $outlet = Outlet::firstOrCreate(
            ['code' => 'PUSAT'],
            [
                'name' => $pusat->name,
                'is_active' => true,
                'is_sales_enabled' => false,
                'address' => $pusat->address,
                'phone' => $pusat->phone,
            ],
        );

        if (! $pusat->outlet_id) {
            $pusat->update(['outlet_id' => $outlet->id]);
        }

        if (! Setting::get('setup_warehouse_id')) {
            Setting::set('setup_warehouse_id', $pusat->id);
        }

        $products = DB::table('products')->select('id', 'stock')->get()
            ->map(fn ($product) => [
                'product_id' => $product->id,
                'warehouse_id' => $pusat->id,
                'stock' => $product->stock,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        if ($products) {
            DB::table('product_warehouse')->insertOrIgnore($products);
        }
    }
}
