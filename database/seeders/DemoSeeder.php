<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Resetting application data...');

        app(DemoDatabaseResetter::class)->reset();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            PaymentSettingSeeder::class,
            DineInSettingsSeeder::class,
            DemoOutletSeeder::class,
            UserSeeder::class,
            SampleDataSeeder::class,
            OperationalCoreSeeder::class,
            FeatureCoverageSeeder::class,
            FeatureDemoSeeder::class,
        ]);

        Setting::set('app_setup_completed', true);

        app(DemoDatabaseResetter::class)->assertInventoryAggregate();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('Demo data seeder completed.');
        $this->command?->info('Admin   : arya@gmail.com / password (super-admin, semua outlet)');
        $this->command?->info('Manager : manager@gmail.com / password (outlet MAL + TKB)');
        $this->command?->info('Kasir   : cashier@gmail.com / password (outlet MAL)');
    }
}
