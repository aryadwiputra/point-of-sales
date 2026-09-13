<?php

namespace Tests\Feature\Setup;

use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupWizardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    }

    private function validPayload(): array
    {
        return [
            'store_name' => 'Toko Berkah',
            'business_type' => 'food',
            'categories' => ['Makanan', 'Minuman'],
            'user_name' => 'Owner',
            'user_email' => 'owner@example.com',
            'password' => 'password123',
            'warehouse_code' => 'PUSAT',
            'warehouse_name' => 'Gudang Utama',
        ];
    }

    public function test_root_redirects_to_setup_before_install(): void
    {
        $this->get('/')->assertRedirect(route('setup.index'));
    }

    public function test_root_renders_welcome_after_install(): void
    {
        Setting::set('app_setup_completed', true);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Welcome'));
    }

    public function test_setup_page_is_accessible_before_install(): void
    {
        $this->get(route('setup.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Setup/Wizard')
                ->where('businessTypes.0', [
                    'key' => 'food',
                    'categories' => ['food', 'beverages', 'snacks', 'coffeeTea'],
                ])
                ->where('businessTypes.5', [
                    'key' => 'services',
                    'categories' => ['services', 'products', 'packages'],
                ]));
    }

    public function test_setup_page_redirects_after_install(): void
    {
        Setting::set('app_setup_completed', true);

        $this->get(route('setup.index'))->assertRedirect(route('login'));
    }

    public function test_setup_creates_admin_warehouse_and_categories(): void
    {
        $response = $this->post(route('setup.store'), $this->validPayload());

        $response->assertRedirect(route('login'));

        $user = User::where('email', 'owner@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('super-admin'));
        $this->assertTrue(Warehouse::where('code', 'PUSAT')->where('type', 'main')->exists());
        $this->assertSame(
            ['Makanan', 'Minuman'],
            Category::orderBy('id')->pluck('name')->all(),
        );
        $this->assertSame('Toko Berkah', Setting::get('store_name'));
        $this->assertSame('food', Setting::get('store_business_type'));
        $this->assertTrue(Setting::getBool('app_setup_completed'));
    }

    public function test_setup_requires_unique_email_and_warehouse_code(): void
    {
        User::create([
            'name' => 'Taken',
            'email' => 'owner@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->post(route('setup.store'), $this->validPayload())
            ->assertSessionHasErrors(['user_email']);
    }

    public function test_setup_rejects_unknown_business_type(): void
    {
        $payload = $this->validPayload();
        $payload['business_type'] = 'crypto';

        $this->post(route('setup.store'), $payload)
            ->assertSessionHasErrors(['business_type']);
    }

    public function test_index_returns_primary_warehouse_when_seeded(): void
    {
        $warehouse = Warehouse::create([
            'code' => 'PUSAT',
            'name' => 'Gudang Pusat',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        Setting::set('setup_warehouse_id', $warehouse->id);

        $this->get(route('setup.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('primaryWarehouse.id', $warehouse->id)
                ->where('primaryWarehouse.code', 'PUSAT')
                ->where('primaryWarehouse.name', 'Gudang Pusat'));
    }

    public function test_index_returns_null_primary_warehouse_when_not_seeded(): void
    {
        $this->get(route('setup.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('primaryWarehouse', null));
    }

    public function test_store_updates_existing_warehouse_when_warehouse_id_provided(): void
    {
        $warehouse = Warehouse::create([
            'code' => 'PUSAT',
            'name' => 'Gudang Pusat',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $payload = $this->validPayload();
        $payload['warehouse_id'] = $warehouse->id;
        $payload['warehouse_code'] = 'PUSAT';
        $payload['warehouse_name'] = 'Gudang Utama';

        $response = $this->post(route('setup.store'), $payload);
        $response->assertRedirect(route('login'));

        $warehouse->refresh();
        $this->assertSame('Gudang Utama', $warehouse->name);
        $this->assertSame('PUSAT', $warehouse->code);
        $this->assertSame($warehouse->id, (int) Setting::get('setup_warehouse_id'));
    }

    public function test_store_rejects_duplicate_warehouse_code_when_updating(): void
    {
        $warehouse1 = Warehouse::create([
            'code' => 'PUSAT',
            'name' => 'Gudang Satu',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $warehouse2 = Warehouse::create([
            'code' => 'CABANG',
            'name' => 'Cabang',
            'type' => 'branch',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $payload = $this->validPayload();
        $payload['warehouse_id'] = $warehouse2->id;
        $payload['warehouse_code'] = 'PUSAT';

        $this->post(route('setup.store'), $payload)
            ->assertSessionHasErrors(['warehouse_code']);
    }

    public function test_store_allows_renaming_same_warehouse_code(): void
    {
        $warehouse = Warehouse::create([
            'code' => 'PUSAT',
            'name' => 'Gudang Lama',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $payload = $this->validPayload();
        $payload['warehouse_id'] = $warehouse->id;
        $payload['warehouse_code'] = 'PUSAT';
        $payload['warehouse_name'] = 'Gudang Baru';

        $this->post(route('setup.store'), $payload)->assertRedirect(route('login'));

        $warehouse->refresh();
        $this->assertSame('Gudang Baru', $warehouse->name);
    }

    public function test_setup_warehouse_id_is_set_after_successful_setup(): void
    {
        $response = $this->post(route('setup.store'), $this->validPayload());
        $response->assertRedirect(route('login'));

        $warehouse = Warehouse::where('code', 'PUSAT')->first();
        $this->assertSame($warehouse->id, (int) Setting::get('setup_warehouse_id'));
    }
}
