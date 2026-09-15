<?php

namespace Database\Seeders;

use App\Models\Outlet;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class DemoOutletSeeder extends Seeder
{
    public function run(): void
    {
        $outlets = [
            ['code' => 'PUSAT', 'name' => 'Gudang Pusat', 'sales' => false, 'warehouse' => ['code' => 'PUSAT', 'name' => 'Gudang Pusat', 'type' => 'main']],
            ['code' => 'MAL', 'name' => 'Outlet Malabar', 'sales' => true, 'warehouse' => ['code' => 'WH-MAL', 'name' => 'Gudang Malabar', 'type' => 'branch']],
            ['code' => 'TKB', 'name' => 'Outlet Taman Kencana', 'sales' => true, 'warehouse' => ['code' => 'WH-TKB', 'name' => 'Gudang Taman Kencana', 'type' => 'branch']],
            ['code' => 'PUT', 'name' => 'Outlet Puter', 'sales' => true, 'warehouse' => ['code' => 'WH-PUT', 'name' => 'Gudang Puter', 'type' => 'branch']],
        ];

        foreach ($outlets as $data) {
            $outlet = Outlet::updateOrCreate(
                ['code' => $data['code']],
                ['name' => $data['name'], 'is_active' => true, 'is_sales_enabled' => $data['sales']],
            );

            Warehouse::updateOrCreate(
                ['code' => $data['warehouse']['code']],
                [
                    'outlet_id' => $outlet->id,
                    'name' => $data['warehouse']['name'],
                    'type' => $data['warehouse']['type'],
                    'is_active' => true,
                    'sort_order' => $data['code'] === 'PUSAT' ? 0 : 1,
                ],
            );
        }
    }
}
