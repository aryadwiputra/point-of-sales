<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
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

        $this->info('Seeding complete demo dataset...');
        $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

        $this->newLine();
        $this->info('Demo data regenerated! Login (password: "password"):');
        $this->line('  - arya@gmail.com     (super-admin, semua outlet)');
        $this->line('  - manager@gmail.com  (manager, outlet MAL + TKB)');
        $this->line('  - cashier@gmail.com  (kasir, outlet MAL)');

        return self::SUCCESS;
    }
}
