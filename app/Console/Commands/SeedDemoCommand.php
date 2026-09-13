<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SeedDemoCommand extends Command
{
    protected $signature = 'seed:demo {--force : Skip confirmation prompt}';

    protected $description = 'Regenerate the full demo dataset (users, sample transactions, operational & feature demo data)';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->warn('This will TRUNCATE operational tables (transactions, products, customers, etc.) and regenerate demo data.');

            if (! $this->confirm('Continue?')) {
                $this->info('Aborted.');

                return self::FAILURE;
            }
        }

        $this->info('Seeding demo users...');
        $this->call('db:seed', ['--class' => 'UserSeeder', '--force' => true]);

        $this->info('Seeding sample data (requires internet for product images)...');
        $this->call('db:seed', ['--class' => 'SampleDataSeeder', '--force' => true]);

        $this->info('Seeding operational core data...');
        $this->call('db:seed', ['--class' => 'OperationalCoreSeeder', '--force' => true]);

        $this->info('Seeding feature coverage data...');
        $this->call('db:seed', ['--class' => 'FeatureCoverageSeeder', '--force' => true]);

        $this->info('Seeding feature demo data...');
        $this->call('db:seed', ['--class' => 'FeatureDemoSeeder', '--force' => true]);

        $this->newLine();
        $this->info('Demo data regenerated! Login: arya@gmail.com / cashier@gmail.com (password: "password")');

        return self::SUCCESS;
    }
}
