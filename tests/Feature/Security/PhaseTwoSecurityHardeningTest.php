<?php

namespace Tests\Feature\Security;

use App\Models\Outlet;
use App\Models\PaymentSetting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\OutletAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PhaseTwoSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_secrets_are_encrypted_at_rest(): void
    {
        $setting = PaymentSetting::create([
            'default_gateway' => 'cash',
            'midtrans_server_key' => 'server-secret',
            'midtrans_client_key' => 'client-key',
            'xendit_secret_key' => 'xendit-secret',
            'xendit_callback_token' => 'callback-secret',
        ]);

        $raw = DB::table('payment_settings')->where('id', $setting->id)->first();

        $this->assertNotSame('server-secret', $raw->midtrans_server_key);
        $this->assertNotSame('xendit-secret', $raw->xendit_secret_key);
        $this->assertNotSame('callback-secret', $raw->xendit_callback_token);
    }

    public function test_payment_settings_page_does_not_expose_plaintext_secrets(): void
    {
        Permission::firstOrCreate(['name' => 'payment-settings-access', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo('payment-settings-access');

        PaymentSetting::create([
            'default_gateway' => 'cash',
            'midtrans_enabled' => true,
            'midtrans_server_key' => 'server-secret',
            'midtrans_client_key' => 'client-key',
            'xendit_enabled' => true,
            'xendit_secret_key' => 'xendit-secret',
            'xendit_callback_token' => 'callback-secret',
            'xendit_public_key' => 'public-key',
        ]);

        $response = $this->actingAs($user)->get(route('settings.payments.edit'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Settings/Payment')
            ->missing('setting.midtrans_server_key')
            ->missing('setting.xendit_secret_key')
            ->missing('setting.xendit_callback_token')
            ->where('paymentSettingSources.midtrans_server_key.configured', true)
            ->where('paymentSettingSources.xendit_secret_key.configured', true)
        );
    }

    public function test_env_override_takes_precedence_over_database_secret(): void
    {
        config()->set('services.midtrans.server_key', 'env-server-key');
        config()->set('services.xendit.secret_key', 'env-xendit-secret');
        config()->set('services.xendit.callback_token', 'env-callback-token');

        $setting = PaymentSetting::create([
            'default_gateway' => 'cash',
            'midtrans_server_key' => 'database-server-key',
            'xendit_secret_key' => 'database-xendit-secret',
            'xendit_callback_token' => 'database-callback-token',
        ]);

        $this->assertSame('env-server-key', $setting->midtransConfig()['server_key']);
        $this->assertSame('env-xendit-secret', $setting->xenditConfig()['secret_key']);
        $this->assertSame('env-callback-token', $setting->xenditConfig()['callback_token']);
        $this->assertSame('env', $setting->paymentSettingSources()['midtrans_server_key']['source']);
    }

    public function test_sanctum_token_expiration_is_configured(): void
    {
        $this->assertNotNull(config('sanctum.expiration'));
        $this->assertGreaterThan(0, config('sanctum.expiration'));
    }

    public function test_secure_headers_are_present_on_web_response(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Content-Security-Policy-Report-Only');
    }

    public function test_hsts_header_is_not_sent_outside_production(): void
    {
        $response = $this->get('/');

        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }

    public function test_legacy_single_outlet_bypass_can_be_disabled(): void
    {
        config()->set('security.outlet.legacy_single_outlet_bypass', false);

        $outlet = Outlet::create(['code' => 'PUSAT', 'name' => 'Pusat']);
        $warehouse = Warehouse::create([
            'code' => 'PUSAT', 'name' => 'Gudang Pusat', 'type' => 'main',
            'is_active' => true, 'sort_order' => 0, 'outlet_id' => $outlet->id,
        ]);

        $this->assertFalse(
            app(OutletAccessService::class)
                ->canUseWarehouse(User::factory()->create(), $warehouse)
        );
    }
}
