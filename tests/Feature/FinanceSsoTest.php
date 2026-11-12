<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AddonService;
use App\Services\FinanceSsoService;
use App\Services\TenantContext;
use App\Support\AddonCodes;
use App\Support\HmacJwt;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class FinanceSsoTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $admin;

    protected FinanceSsoService $sso;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        config([
            'finance.sso_secret' => 'test-sso-secret',
            // Selalu override agar tes tidak tergantung FINANCE_BRIDGE_SECRET di .env lokal.
            'finance.bridge_secret' => 'test-sso-secret',
            'finance.url' => 'http://finance.test',
        ]);

        $enterprise = Plan::where('slug', 'enterprise')->firstOrFail();

        $this->tenant = Tenant::create([
            'name' => 'Toko Finance',
            'subdomain' => 'financetest',
            'plan_id' => $enterprise->id,
            'status' => 'active',
        ]);

        $this->admin = User::factory()->create([
            'name' => 'Admin Finance',
            'email' => 'admin@finance.test',
            'tenant_id' => $this->tenant->id,
        ]);
        $this->admin->assignRole('super-admin');

        $this->sso = app(FinanceSsoService::class);
        TenantContext::reset();
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_token_round_trips_and_carries_entitlements(): void
    {
        TenantContext::set($this->tenant);

        $token = $this->sso->issue($this->tenant, $this->admin);
        $claims = $this->sso->verify($token);

        $this->assertSame($this->tenant->id, $claims['tenant_id']);
        $this->assertSame($this->admin->id, (int) $claims['sub']);
        $this->assertSame('bpos-finance', $claims['aud']);
        $this->assertContains(AddonCodes::FINANCE, $claims['entitlements']);
        $this->assertContains(AddonCodes::BIHAJU, $claims['entitlements']);
        $this->assertArrayHasKey('exp', $claims);
    }

    public function test_tampered_token_is_rejected(): void
    {
        TenantContext::set($this->tenant);

        $token = $this->sso->issue($this->tenant, $this->admin);
        $parts = explode('.', $token);

        // Ubah payload tanpa menandatangani ulang.
        $tampered = $parts[0].'.'.HmacJwt::base64UrlEncode('{"tenant_id":999}').'.'.$parts[2];

        $this->expectException(RuntimeException::class);
        $this->sso->verify($tampered);
    }

    public function test_token_without_finance_entitlement_is_rejected(): void
    {
        $token = HmacJwt::encode([
            'tenant_id' => $this->tenant->id,
            'entitlements' => ['basic_pos'],
            'exp' => now()->addMinutes(5)->timestamp,
        ], 'test-sso-secret');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('entitlement finance');
        $this->sso->verify($token);
    }

    public function test_expired_token_is_rejected(): void
    {
        $token = HmacJwt::encode([
            'tenant_id' => $this->tenant->id,
            'entitlements' => [AddonCodes::FINANCE],
            'exp' => now()->subMinute()->timestamp,
        ], 'test-sso-secret');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('kedaluwarsa');
        $this->sso->verify($token);
    }

    public function test_token_signed_with_other_secret_is_rejected(): void
    {
        $token = HmacJwt::encode([
            'tenant_id' => $this->tenant->id,
            'entitlements' => [AddonCodes::FINANCE],
            'exp' => now()->addMinutes(5)->timestamp,
        ], 'secret-lain');

        $this->expectException(RuntimeException::class);
        $this->sso->verify($token);
    }

    public function test_launch_url_points_to_finance_app_with_token(): void
    {
        TenantContext::set($this->tenant);

        $url = $this->sso->launchUrl($this->tenant, $this->admin);

        $this->assertStringStartsWith('http://finance.test/sso?token=', $url);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertArrayHasKey('token', $query);

        $claims = $this->sso->verify($query['token']);
        $this->assertSame($this->tenant->id, $claims['tenant_id']);
    }

    public function test_entitlements_come_from_plan_and_active_addons(): void
    {
        TenantContext::set($this->tenant);

        $plan = Plan::where('slug', 'pro')->firstOrFail();
        $this->tenant->update(['plan_id' => $plan->id]);
        TenantContext::set($this->tenant->fresh());

        // Paket Pro: finance/bihaju tidak ada.
        $before = $this->sso->entitlements($this->tenant);
        $this->assertNotContains(AddonCodes::FINANCE, $before);

        app(AddonService::class)->activate(AddonCodes::FINANCE);

        $after = $this->sso->entitlements($this->tenant->fresh());
        $this->assertContains(AddonCodes::FINANCE, $after);
    }

    public function test_bridge_headers_carry_bearer_and_signature(): void
    {
        TenantContext::set($this->tenant);

        $headers = $this->sso->bridgeHeaders($this->tenant);

        $this->assertStringStartsWith('Bearer ', $headers['Authorization']);
        $this->assertNotEmpty($headers['X-Finance-Signature']);

        $expected = hash_hmac('sha256', (string) $this->tenant->id, 'test-sso-secret');
        $this->assertSame($expected, $headers['X-Finance-Signature']);
    }
}
