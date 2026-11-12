<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravolt\Indonesia\Models\City;
use Laravolt\Indonesia\Models\District;
use Laravolt\Indonesia\Models\Province;
use Laravolt\Indonesia\Models\Village;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    }

    public function test_can_view_create_customer_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        $user->markEmailAsVerified();

        Province::create(['code' => '32', 'name' => 'JAWA BARAT']);

        $response = $this->actingAs($user)->get(route('customers.create'));

        $response->assertOk();
    }

    public function test_can_query_regions_cascade(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        $user->markEmailAsVerified();

        Province::create(['code' => '32', 'name' => 'JAWA BARAT']);
        City::create(['code' => '3273', 'province_code' => '32', 'name' => 'KOTA BANDUNG']);
        District::create(['code' => '327301', 'city_code' => '3273', 'name' => 'SUKASARI']);
        Village::create(['code' => '3273011001', 'district_code' => '327301', 'name' => 'SUKARASA']);

        $resRegencies = $this->actingAs($user)->getJson(route('regions.regencies', ['province_id' => '32']));
        $resRegencies->assertOk()->assertJsonFragment(['code' => '3273', 'name' => 'KOTA BANDUNG']);

        $resDistricts = $this->actingAs($user)->getJson(route('regions.districts', ['regency_id' => '3273']));
        $resDistricts->assertOk()->assertJsonFragment(['code' => '327301', 'name' => 'SUKASARI']);

        $resVillages = $this->actingAs($user)->getJson(route('regions.villages', ['district_id' => '327301']));
        $resVillages->assertOk()->assertJsonFragment(['code' => '3273011001', 'name' => 'SUKARASA']);
    }

    public function test_can_store_customer_with_address_regions(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        $user->markEmailAsVerified();

        Province::create(['code' => '32', 'name' => 'JAWA BARAT']);
        City::create(['code' => '3273', 'province_code' => '32', 'name' => 'KOTA BANDUNG']);
        District::create(['code' => '327301', 'city_code' => '3273', 'name' => 'SUKASARI']);
        Village::create(['code' => '3273011001', 'district_code' => '327301', 'name' => 'SUKARASA']);

        $response = $this->actingAs($user)->post(route('customers.store'), [
            'name' => 'Budi Santoso',
            'no_telp' => '081234567890',
            'address' => 'Jl. Gegerkalong No. 10',
            'province_id' => '32',
            'regency_id' => '3273',
            'district_id' => '327301',
            'village_id' => '3273011001',
        ]);

        $response->assertRedirect(route('customers.index'));
        $this->assertDatabaseHas('customers', [
            'name' => 'Budi Santoso',
            'no_telp' => '081234567890',
            'province_name' => 'JAWA BARAT',
            'regency_name' => 'KOTA BANDUNG',
            'district_name' => 'SUKASARI',
            'village_name' => 'SUKARASA',
        ]);
    }
}
