<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class DemoDatabaseResetter
{
    public function reset(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Demo data reset is disabled in production.');
        }

        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $supportedDrivers = ['sqlite', 'mysql', 'mariadb'];

        if (! in_array($connection->getDriverName(), $supportedDrivers, true)) {
            throw new RuntimeException('Demo data reset is not supported for this database driver.');
        }

        $migrationTable = $connection->getTablePrefix().config('database.migrations.table', 'migrations');
        $protectedTables = array_filter([
            $migrationTable,
            ...array_map(
                fn (string $table) => $connection->getTablePrefix().$table,
                [
                    'settings',
                    'payment_settings',
                    'indonesia_provinces',
                    'indonesia_cities',
                    'indonesia_districts',
                    'indonesia_villages',
                ],
            ),
        ]);
        $database = $connection->getDatabaseName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $applicationSchema = DB::selectOne('SELECT DATABASE() as database_name')->database_name;

            if ($database !== $applicationSchema) {
                throw new RuntimeException('Refusing to reset because the configured database does not match the active schema.');
            }
        }

        if (preg_match('/(^|[_-])(production|prod|live)([_-]|$)/i', $database) === 1) {
            throw new RuntimeException('Demo data reset is disabled for production databases.');
        }

        $schema = Schema::connection($connection->getName());
        $tables = collect($schema->getTables())
            ->filter(fn (array $table) => ! in_array($driver, ['mysql', 'mariadb'], true) || $table['schema'] === $database)
            ->pluck('name')
            ->reject(fn (string $table) => in_array($table, $protectedTables, true))
            ->values()
            ->all();

        $deleteOrder = $this->deleteOrder($tables, $schema);

        $reset = function () use ($deleteOrder, $driver): void {
            $delete = function () use ($deleteOrder): void {
                foreach ($deleteOrder as $table) {
                    DB::table($table)->delete();
                }
            };

            if ($driver === 'sqlite') {
                $delete();

                return;
            }

            Schema::withoutForeignKeyConstraints($delete);
        };

        if ($driver === 'sqlite') {
            $reset();

            return;
        }

        DB::transaction($reset);
    }

    private function deleteOrder(array $tables, $schema): array
    {
        $tableSet = array_fill_keys($tables, true);
        $dependencies = [];

        foreach ($tables as $table) {
            foreach ($schema->getForeignKeys($table) as $foreignKey) {
                $parent = $foreignKey['foreign_table'];

                if (isset($tableSet[$parent]) && $parent !== $table) {
                    $dependencies[$parent][] = $table;
                }

                if ($parent === $table) {
                    throw new RuntimeException("Demo reset cannot order self-referencing table [{$table}].");
                }
            }
        }

        $ordered = [];
        $visiting = [];
        $visited = [];
        $visit = function (string $table) use (&$visit, &$ordered, &$visiting, &$visited, $dependencies): void {
            if (isset($visited[$table])) {
                return;
            }

            if (isset($visiting[$table])) {
                throw new RuntimeException('Demo reset cannot order cyclic foreign-key dependencies.');
            }

            $visiting[$table] = true;

            foreach ($dependencies[$table] ?? [] as $child) {
                $visit($child);
            }

            unset($visiting[$table]);
            $visited[$table] = true;
            $ordered[] = $table;
        };

        foreach ($tables as $table) {
            $visit($table);
        }

        return $ordered;
    }

    public function assertInventoryAggregate(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('product_warehouse')) {
            throw new RuntimeException('Demo seeding requires product and warehouse stock tables.');
        }

        $inconsistent = DB::table('products')
            ->leftJoin('product_warehouse', 'products.id', '=', 'product_warehouse.product_id')
            ->select('products.id', 'products.stock')
            ->selectRaw('COALESCE(SUM(product_warehouse.stock), 0) as warehouse_stock')
            ->groupBy('products.id', 'products.stock')
            ->havingRaw('products.stock != COALESCE(SUM(product_warehouse.stock), 0)')
            ->exists();

        if ($inconsistent) {
            throw new RuntimeException('Demo seeding produced inconsistent product and warehouse stock totals.');
        }
    }
}
