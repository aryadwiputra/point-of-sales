<?php

namespace Tests\Feature\Inventory;

use App\Models\Outlet;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutletTest extends TestCase
{
    use RefreshDatabase;

    public function test_pusat_warehouse_is_attached_to_pusat_outlet_by_database_seeder(): void
    {
        $this->seed();

        $outlet = Outlet::where('code', 'PUSAT')->first();
        $warehouse = Warehouse::where('code', 'PUSAT')->first();

        $this->assertNotNull($outlet);
        $this->assertNotNull($warehouse);
        $this->assertSame($outlet->id, $warehouse->outlet_id);
        $this->assertTrue($warehouse->outlet->is($outlet));
        $this->assertTrue($outlet->warehouses->contains($warehouse));
    }
}
