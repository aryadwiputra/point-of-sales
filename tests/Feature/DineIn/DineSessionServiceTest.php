<?php

namespace Tests\Feature\DineIn;

use App\Models\DineArea;
use App\Models\DineBill;
use App\Models\DineSession;
use App\Models\DiningTable;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DineSessionService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DineSessionServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private DiningTable $table;

    private DiningTable $target;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);
        $this->admin = User::where('email', 'arya@gmail.com')->first();
        $this->admin->markEmailAsVerified();

        Warehouse::create([
            'code' => 'PUSAT',
            'name' => 'Gudang Pusat',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $area = DineArea::create(['name' => 'Indoor', 'is_active' => true]);
        $this->table = DiningTable::create([
            'dine_area_id' => $area->id,
            'name' => 'Meja 1',
            'is_active' => true,
            'capacity' => 4,
        ]);
        $this->target = DiningTable::create([
            'dine_area_id' => $area->id,
            'name' => 'Meja 2',
            'is_active' => true,
            'capacity' => 4,
        ]);
    }

    public function test_open_rejects_a_second_open_session_on_the_same_table(): void
    {
        $service = app(DineSessionService::class);
        $service->open($this->table, 2, 'Dinner', $this->admin->id);

        $this->expectException(ValidationException::class);
        $service->open($this->table, 3, null, $this->admin->id);
    }

    public function test_occupancy_lists_open_session_totals(): void
    {
        $service = app(DineSessionService::class);
        $session = $service->open($this->table, 4, null, $this->admin->id);
        DineBill::create([
            'dine_session_id' => $session->id,
            'sequence' => 1,
            'status' => DineBill::STATUS_OPEN,
            'subtotal' => 25000,
            'grand_total' => 25000,
        ]);

        $occupancy = $service->occupancyForTables([$this->table]);

        $this->assertSame($session->id, $occupancy[$this->table->id]['session_id']);
        $this->assertSame(4, $occupancy[$this->table->id]['guest_count']);
        $this->assertSame(1, $occupancy[$this->table->id]['open_bill_count']);
        $this->assertSame(25000, $occupancy[$this->table->id]['running_total']);
    }

    public function test_move_rejects_an_occupied_target_table(): void
    {
        $service = app(DineSessionService::class);
        $source = $service->open($this->table, 2, null, $this->admin->id);
        $service->open($this->target, 2, null, $this->admin->id);

        $this->expectException(ValidationException::class);
        $service->move($source, $this->target);
    }

    public function test_merge_moves_open_bills_renumbers_and_closes_source(): void
    {
        $service = app(DineSessionService::class);
        $source = $service->open($this->table, 2, null, $this->admin->id);
        $target = $service->open($this->target, 2, null, $this->admin->id);

        $sourceBill = DineBill::create([
            'dine_session_id' => $source->id,
            'sequence' => 1,
            'status' => DineBill::STATUS_OPEN,
        ]);
        DineBill::create([
            'dine_session_id' => $target->id,
            'sequence' => 1,
            'status' => DineBill::STATUS_SETTLED,
        ]);
        DineBill::create([
            'dine_session_id' => $target->id,
            'sequence' => 2,
            'status' => DineBill::STATUS_VOID,
        ]);

        $merged = $service->merge($source, $target, $this->admin->id);

        $this->assertSame(DineSession::STATUS_CLOSED, $source->fresh()->status);
        $this->assertSame($target->id, $source->fresh()->merged_into_session_id);
        $this->assertSame($merged->id, $sourceBill->fresh()->dine_session_id);
        $this->assertSame(3, $sourceBill->fresh()->sequence);
    }

    public function test_close_rejects_session_with_open_bill_and_allows_idle_close(): void
    {
        $service = app(DineSessionService::class);
        $session = $service->open($this->table, 2, null, $this->admin->id);
        DineBill::create([
            'dine_session_id' => $session->id,
            'sequence' => 1,
            'status' => DineBill::STATUS_OPEN,
        ]);

        try {
            $service->close($session);
            $this->fail('Expected ValidationException for session with open bill.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('session', $exception->errors());
        }

        $session->bills()->update(['status' => DineBill::STATUS_SETTLED]);
        $session->bills()->update(['status' => DineBill::STATUS_VOID]);
        $service->close($session->fresh());

        $this->assertSame(DineSession::STATUS_CLOSED, $session->fresh()->status);
        $this->assertNotNull($session->fresh()->closed_at);
        $this->assertSame($this->table->id, $this->table->id);
    }
}
