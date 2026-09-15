<?php

namespace Tests\Feature\Inventory;

use App\Models\CashierShift;
use App\Models\Outlet;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\OutletAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveOutletTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_switch_between_assigned_outlets(): void
    {
        [$first, $second] = $this->outlets();
        $user = User::factory()->create();
        $user->outlets()->attach([$first->id => ['is_default' => true], $second->id => ['is_default' => false]]);

        $this->actingAs($user)
            ->withSession(['active_outlet_id' => $first->id])
            ->post(route('outlet.switch'), ['outlet_id' => $second->id])
            ->assertRedirect();

        $this->assertSame($second->id, session('active_outlet_id'));
        $this->assertSame($second->id, app(OutletAccessService::class)->activeOutlet(request())->id);
    }

    public function test_active_shift_locks_outlet_switch(): void
    {
        [$first, $second] = $this->outlets();
        $user = User::factory()->create();
        $user->outlets()->attach([$first->id => ['is_default' => true], $second->id => ['is_default' => false]]);
        $warehouse = Warehouse::create([
            'outlet_id' => $first->id,
            'code' => 'WH-A',
            'name' => 'Warehouse A',
            'type' => 'branch',
            'is_active' => true,
        ]);
        CashierShift::create([
            'user_id' => $user->id,
            'opened_by' => $user->id,
            'warehouse_id' => $warehouse->id,
            'opened_at' => now(),
            'opening_cash' => 0,
            'expected_cash' => 0,
            'status' => CashierShift::STATUS_OPEN,
        ]);

        $this->actingAs($user)
            ->post(route('outlet.switch'), ['outlet_id' => $second->id])
            ->assertStatus(422);
    }

    private function outlets(): array
    {
        return [
            Outlet::create(['code' => 'OUT-A', 'name' => 'Outlet A', 'is_active' => true]),
            Outlet::create(['code' => 'OUT-B', 'name' => 'Outlet B', 'is_active' => true]),
        ];
    }
}
