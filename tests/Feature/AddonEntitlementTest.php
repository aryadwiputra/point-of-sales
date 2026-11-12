<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantAddon;
use App\Models\User;
use App\Services\AddonService;
use App\Services\TenantContext;
use App\Support\AddonCodes;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddonEntitlementTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Plan $plan;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->plan = Plan::where('slug', 'pro')->firstOrFail();

        $this->tenant = Tenant::create([
            'name' => 'Toko Addon',
            'subdomain' => 'addonstest',
            'plan_id' => $this->plan->id,
            'status' => 'active',
        ]);

        $this->admin = User::factory()->create([
            'name' => 'Admin Addon',
            'email' => 'admin@addon.test',
            'tenant_id' => $this->tenant->id,
        ]);
        $this->admin->assignRole('super-admin');

        TenantContext::reset();
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    protected function service(): AddonService
    {
        TenantContext::set($this->tenant);

        return app(AddonService::class);
    }

    public function test_enterprise_plan_includes_finance_and_bihaju_features(): void
    {
        $enterprise = Plan::where('slug', 'enterprise')->firstOrFail();

        $this->assertTrue($enterprise->hasFeature(AddonCodes::FINANCE));
        $this->assertTrue($enterprise->hasFeature(AddonCodes::BIHAJU));
    }

    public function test_addon_not_available_when_plan_lacks_it_and_no_purchase(): void
    {
        // Paket Pro tidak menyertakan finance/bihaju.
        $service = $this->service();

        $this->assertFalse($service->isEntitled(AddonCodes::FINANCE));
        $this->assertFalse($service->isActive(AddonCodes::FINANCE));
        $this->assertFalse($service->isActive(AddonCodes::BIHAJU));
    }

    public function test_addon_becomes_active_after_explicit_activation(): void
    {
        $service = $this->service();

        $service->activate(AddonCodes::FINANCE, ['auto_submit' => true]);

        $this->assertTrue($service->isEntitled(AddonCodes::FINANCE));
        $this->assertTrue($service->isActive(AddonCodes::FINANCE));

        $addon = TenantAddon::where('tenant_id', $this->tenant->id)
            ->where('code', AddonCodes::FINANCE)
            ->firstOrFail();

        $this->assertSame(TenantAddon::STATUS_ACTIVE, $addon->status);
        $this->assertSame(true, $addon->config('auto_submit'));
        $this->assertNotNull($addon->activated_at);
    }

    public function test_deactivated_addon_falls_back_to_plan_feature(): void
    {
        $service = $this->service();
        $service->activate(AddonCodes::BIHAJU);
        $this->assertTrue($service->isActive(AddonCodes::BIHAJU));

        $service->deactivate(AddonCodes::BIHAJU);

        // Paket Pro tidak menyertakan bihaju → kembali nonaktif.
        $this->assertFalse($service->isActive(AddonCodes::BIHAJU));
    }

    public function test_suspended_addon_blocks_access_even_when_plan_includes_it(): void
    {
        // Pindahkan tenant ke Enterprise (memuat fitur finance).
        $enterprise = Plan::where('slug', 'enterprise')->firstOrFail();
        $this->tenant->update(['plan_id' => $enterprise->id]);

        $service = $this->service();
        $this->assertTrue($service->isActive(AddonCodes::FINANCE));

        TenantAddon::create([
            'tenant_id' => $this->tenant->id,
            'code' => AddonCodes::FINANCE,
            'status' => TenantAddon::STATUS_SUSPENDED,
        ]);

        $this->assertFalse($service->isActive(AddonCodes::FINANCE));
        $this->assertTrue($service->isEntitled(AddonCodes::FINANCE));
    }

    public function test_expired_addon_is_not_active(): void
    {
        $service = $this->service();

        $service->activate(AddonCodes::BIHAJU, [], now()->subDay());

        $this->assertFalse($service->isActive(AddonCodes::BIHAJU));
    }

    public function test_expired_tenant_blocks_addon_access(): void
    {
        $service = $this->service();
        $service->activate(AddonCodes::FINANCE);

        $this->tenant->update([
            'status' => 'active',
            'subscription_ends_at' => now()->subDay(),
        ]);

        TenantContext::set($this->tenant->fresh());

        $this->assertFalse($this->service()->isActive(AddonCodes::FINANCE));
    }

    public function test_plain_plan_features_still_work_through_addon_service(): void
    {
        // 'crm' bukan add-on — harus mengikuti paket (Pro memuat crm).
        $service = $this->service();

        $this->assertTrue($service->isActive('crm'));
        $this->assertFalse($service->isActive('some_unknown_feature'));
    }

    public function test_status_lists_every_addon_with_state(): void
    {
        $service = $this->service();
        $service->activate(AddonCodes::BIHAJU);

        $status = collect($service->status());

        $this->assertCount(count(AddonCodes::ALL), $status);
        $this->assertTrue($status->firstWhere('code', AddonCodes::BIHAJU)['active']);
        $this->assertFalse($status->firstWhere('code', AddonCodes::FINANCE)['active']);
        $this->assertNotEmpty($status->firstWhere('code', AddonCodes::FINANCE)['label']);
    }

    public function test_secret_is_encrypted_at_rest(): void
    {
        $service = $this->service();
        $service->activate(AddonCodes::BIHAJU, [], null);

        $addon = TenantAddon::where('tenant_id', $this->tenant->id)
            ->where('code', AddonCodes::BIHAJU)
            ->firstOrFail();

        $addon->secret = 'pk_live_rahasia';
        $addon->save();

        $raw = \Illuminate\Support\Facades\DB::table('tenant_addons')
            ->where('id', $addon->id)
            ->value('secret');

        $this->assertStringNotContainsString('pk_live_rahasia', (string) $raw);
        $this->assertNotEmpty((string) $raw);
        $this->assertSame('pk_live_rahasia', $addon->fresh()->secret);
    }
}
