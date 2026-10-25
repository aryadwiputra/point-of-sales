<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedDemoCommand extends Command
{
    protected $signature = 'seed:demo {--force : Skip confirmation prompt}';

    protected $description = 'Regenerate the full demo dataset (users, sample transactions, operational & feature demo data)';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Demo seeding is disabled in production.');

            return self::FAILURE;
        }

        $connection = DB::connection();
        $database = $connection->getDatabaseName();
        $driver = $connection->getDriverName();

        if (! in_array($driver, ['sqlite', 'mysql', 'mariadb'], true)) {
            $this->error('Demo seeding is not supported for this database driver.');

            return self::FAILURE;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $activeSchema = DB::selectOne('SELECT DATABASE() as database_name')->database_name;

            if ($activeSchema !== $database) {
                $this->error('Configured database does not match the active MySQL schema; refusing to reset.');

                return self::FAILURE;
            }
        }

        if (! $this->option('force')) {
            $this->warn("This will erase application data in database [{$database}] and regenerate the demo dataset. Uploaded files are not deleted.");

            if (! $this->confirm('Continue?')) {
                $this->info('Aborted.');

                return self::FAILURE;
            }
        }

        $this->info("Resetting and seeding complete demo dataset in [{$database}]...");
        $result = $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

        if ($result !== self::SUCCESS) {
            return $result;
        }

        $this->newLine();
        $this->info('Demo data regenerated! Login (password: "password"):');
        $this->line('  - arya@gmail.com     (super-admin, semua outlet)');
        $this->line('  - manager@gmail.com  (manager, outlet MAL + TKB)');
        $this->line('  - cashier@gmail.com  (kasir, outlet MAL)');

        return self::SUCCESS;
    }
}
