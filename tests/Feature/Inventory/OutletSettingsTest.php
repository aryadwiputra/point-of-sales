<?php

namespace Tests\Feature\Inventory;

use App\Models\Outlet;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ThermalPrintService;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OutletSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_outlet_setting_overrides_global_value_and_global_value_remains_fallback(): void
    {
        $outlet = Outlet::create(['code' => 'MAL', 'name' => 'Malabar']);

        Setting::set('store_name', 'Cafe Pusat');
        Setting::setForOutlet('store_name', 'Cafe Malabar', $outlet);

        $this->assertSame('Cafe Malabar', Setting::getForOutlet('store_name', $outlet));
        $this->assertSame('Cafe Pusat', Setting::get('store_name'));
        $this->assertSame('Cafe Pusat', Setting::getForOutlet('store_name', null));
    }

    public function test_settings_page_uses_assigned_outlets_default_profile(): void
    {
        $outlet = Outlet::create(['code' => 'MAL', 'name' => 'Malabar']);
        $user = User::factory()->create();
        $user->markEmailAsVerified();
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'dashboard-access', 'guard_name' => 'web']));
        $user->outlets()->attach($outlet->id, ['is_default' => true]);
        Setting::set('store_name', 'Cafe Pusat');
        Setting::setForOutlet('store_name', 'Cafe Malabar', $outlet);

        $this->actingAs($user)
            ->get(route('settings.store'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Settings/Store')
                ->where('settings.store_name', 'Cafe Malabar'));
    }

    public function test_receipt_uses_transaction_warehouse_outlet_profile(): void
    {
        $outlet = Outlet::create(['code' => 'MAL', 'name' => 'Malabar']);
        $warehouse = Warehouse::create([
            'code' => 'MAL',
            'name' => 'Malabar',
            'type' => 'branch',
            'outlet_id' => $outlet->id,
            'is_active' => true,
        ]);
        Setting::set('store_name', 'Cafe Pusat');
        Setting::setForOutlet('store_name', 'Cafe Malabar', $outlet);

        $transaction = new Transaction(['warehouse_id' => $warehouse->id]);
        $transaction->setRelation('warehouse', $warehouse->load('outlet'));

        $text = app(ThermalPrintService::class)->generateReceiptText($transaction);

        $this->assertStringContainsString('CAFE MALABAR', $text);
        $this->assertStringNotContainsString('CAFE PUSAT', $text);
    }

    public function test_target_and_whatsapp_use_active_outlet_with_global_fallback(): void
    {
        $outletA = Outlet::create(['code' => 'MAL', 'name' => 'Malabar', 'is_active' => true]);
        $outletB = Outlet::create(['code' => 'TKB', 'name' => 'Taman Kencana', 'is_active' => true]);
        $user = User::factory()->create();
        $user->markEmailAsVerified();
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'dashboard-access', 'guard_name' => 'web']));
        $user->outlets()->attach([$outletA->id => ['is_default' => true], $outletB->id => ['is_default' => false]]);

        Setting::set('monthly_sales_target', '100000');
        Setting::setForOutlet('monthly_sales_target', '250000', $outletA);
        Setting::set('wa_service_url', 'https://global-wa.test');
        Setting::set('wa_enabled', '1');
        Setting::setForOutlet('wa_service_url', 'https://malabar-wa.test', $outletA);
        Setting::setForOutlet('wa_enabled', '1', $outletA);

        $this->actingAs($user)
            ->get(route('settings.target'))
            ->assertInertia(fn (Assert $page) => $page->where('settings.monthly_sales_target', '250000'));

        Http::fake(['https://malabar-wa.test/*' => Http::response(['connected' => true])]);
        $this->assertTrue(app(WhatsAppService::class)->status($outletA)['connected']);
        Http::assertSent(fn ($request) => $request->url() === 'https://malabar-wa.test/status');

        $this->assertSame('100000', Setting::getForOutlet('monthly_sales_target', $outletB));
        $this->assertSame('https://global-wa.test', Setting::getForOutlet('wa_service_url', $outletB));
    }
}
