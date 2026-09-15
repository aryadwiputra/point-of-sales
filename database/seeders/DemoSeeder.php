<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Running full demo data seeder...');

        $this->call([
            DemoOutletSeeder::class,
            UserSeeder::class,
            SampleDataSeeder::class,
            OperationalCoreSeeder::class,
            FeatureCoverageSeeder::class,
            FeatureDemoSeeder::class,
        ]);

        Setting::set('app_setup_completed', true);

        $this->command?->info('Demo data seeder completed.');
        $this->command?->info('Admin: arya@gmail.com / password');
        $this->command?->info('Kasir: cashier@gmail.com / password');
    }
}
