<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Services\TenantContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubscriptionBillingTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $owner;

    protected Plan $proPlan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->proPlan = Plan::where('slug', 'pro')->firstOrFail();

        $this->tenant = Tenant::create([
            'name' => 'Toko Langganan',
            'subdomain' => 'langganan',
            'plan_id' => $this->proPlan->id,
            'status' => 'active',
            'subscription_ends_at' => now()->addDays(20),
        ]);

        $this->owner = User::factory()->create([
            'name' => 'Owner Langganan',
            'email' => 'owner@langganan.test',
            'tenant_id' => $this->tenant->id,
        ]);
        $this->owner->assignRole('super-admin');
        $this->owner->markEmailAsVerified();

        $this->tenant->update(['owner_id' => $this->owner->id]);

        TenantContext::set($this->tenant);
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_tenant_admin_can_view_subscription_page(): void
    {
        $response = $this->actingAs($this->owner)->get(route('subscription.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard/Subscription/Index')
            ->where('tenant.id', $this->tenant->id)
            ->has('plans')
            ->has('usage')
            ->has('subscriptions')
            ->has('gateways')
        );
    }

    public function test_free_plan_activates_immediately(): void
    {
        $freePlan = Plan::create([
            'name' => 'Free Test',
            'slug' => 'free-test',
            'description' => 'Paket gratis',
            'price_monthly' => 0,
            'price_yearly' => 0,
            'max_outlets' => 10,
            'max_users' => 10,
            'max_products' => 100,
            'max_customers' => 100,
            'features' => [],
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->owner)->post(route('subscription.checkout'), [
            'plan_id' => $freePlan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertSessionHas('success');

        $this->assertEquals($freePlan->id, $this->tenant->fresh()->plan_id);
        $this->assertEquals('active', $this->tenant->fresh()->status);

        $subscription = TenantSubscription::where('tenant_id', $this->tenant->id)->latest('id')->first();
        $this->assertNotNull($subscription);
        $this->assertEquals('paid', $subscription->payment_status);
        $this->assertNotNull($subscription->paid_at);
    }

    public function test_paid_plan_without_gateway_is_rejected(): void
    {
        config([
            'services.midtrans.server_key' => null,
            'services.xendit.secret_key' => null,
        ]);

        $before = TenantSubscription::count();

        $response = $this->actingAs($this->owner)->post(route('subscription.checkout'), [
            'plan_id' => Plan::where('slug', 'starter')->first()->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals($before, TenantSubscription::count());
        $this->assertEquals($this->proPlan->id, $this->tenant->fresh()->plan_id);
    }

    public function test_paid_plan_checkout_creates_pending_subscription_with_payment_url(): void
    {
        config(['services.midtrans.server_key' => 'test-server-key']);

        Http::fake([
            'app.sandbox.midtrans.com/*' => function ($request) {
                return Http::response([
                    'order_id' => $request['transaction_details']['order_id'],
                    'redirect_url' => 'https://pay.midtrans.test/abc123',
                    'token' => 'snap-token',
                    'status_code' => '201',
                ], 201);
            },
        ]);

        $starterPlan = Plan::where('slug', 'starter')->first();

        $response = $this->actingAs($this->owner)->post(route('subscription.checkout'), [
            'plan_id' => $starterPlan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertRedirect('https://pay.midtrans.test/abc123');

        $subscription = TenantSubscription::where('tenant_id', $this->tenant->id)->latest('id')->first();

        $this->assertNotNull($subscription);
        $this->assertEquals('pending', $subscription->payment_status);
        $this->assertEquals('midtrans', $subscription->payment_method);
        $this->assertEquals((int) round($starterPlan->price_monthly), (int) round($subscription->amount));
        $this->assertEquals('https://pay.midtrans.test/abc123', $subscription->payment_url);
        $this->assertEquals('SUB-'.$subscription->id, $subscription->gateway_reference);

        // Tenant keeps the old plan until payment is confirmed.
        $this->assertEquals($this->proPlan->id, $this->tenant->fresh()->plan_id);
    }

    public function test_midtrans_webhook_activates_pending_subscription(): void
    {
        config(['services.midtrans.server_key' => 'test-server-key']);

        $subscription = TenantSubscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => Plan::where('slug', 'starter')->first()->id,
            'billing_cycle' => 'yearly',
            'amount' => 1500000,
            'payment_status' => 'pending',
            'payment_method' => 'midtrans',
            'gateway_reference' => 'SUB-1',
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
        ]);

        $orderId = 'SUB-'.$subscription->id;
        $statusCode = '200';
        $grossAmount = '1500000.00';
        $signature = hash('sha512', $orderId.$statusCode.$grossAmount.'test-server-key');

        $response = $this->postJson(route('webhooks.subscription.midtrans'), [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
            'transaction_id' => 'midtrans-trx-1',
        ]);

        $response->assertOk()->assertJson(['status' => 'success']);

        $subscription->refresh();
        $this->assertEquals('paid', $subscription->payment_status);
        $this->assertNotNull($subscription->paid_at);

        $tenant = $this->tenant->fresh();
        $this->assertEquals($subscription->plan_id, $tenant->plan_id);
        $this->assertEquals('active', $tenant->status);
        $this->assertTrue($tenant->subscription_ends_at->isFuture());
    }

    public function test_midtrans_webhook_rejects_invalid_signature(): void
    {
        config(['services.midtrans.server_key' => 'test-server-key']);

        $response = $this->postJson(route('webhooks.subscription.midtrans'), [
            'order_id' => 'SUB-999',
            'status_code' => '200',
            'gross_amount' => '1000.00',
            'signature_key' => 'bogus',
            'transaction_status' => 'settlement',
        ]);

        $response->assertForbidden();
    }

    public function test_xendit_webhook_activates_pending_subscription(): void
    {
        config(['services.xendit.callback_token' => 'callback-token']);

        $subscription = TenantSubscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => Plan::where('slug', 'enterprise')->first()->id,
            'billing_cycle' => 'monthly',
            'amount' => 999000,
            'payment_status' => 'pending',
            'payment_method' => 'xendit',
            'gateway_reference' => 'SUB-2',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $response = $this->postJson(
            route('webhooks.subscription.xendit'),
            [
                'external_id' => 'SUB-'.$subscription->id,
                'status' => 'PAID',
                'id' => 'xendit-invoice-1',
            ],
            ['X-CALLBACK-TOKEN' => 'callback-token']
        );

        $response->assertOk()->assertJson(['status' => 'success']);

        $subscription->refresh();
        $this->assertEquals('paid', $subscription->payment_status);
        $this->assertEquals(
            Plan::where('slug', 'enterprise')->first()->id,
            $this->tenant->fresh()->plan_id
        );
    }

    public function test_expired_tenant_is_redirected_to_subscription_page(): void
    {
        $this->tenant->update([
            'status' => 'active',
            'subscription_ends_at' => now()->subDay(),
        ]);
        TenantContext::reset();

        $response = $this->actingAs($this->owner)->get(route('settings.store'));

        $response->assertRedirect(route('subscription.index'));
        $response->assertSessionHas('error');
        $this->assertEquals('expired', $this->tenant->fresh()->status);
    }

    public function test_expired_tenant_can_still_open_subscription_page(): void
    {
        $this->tenant->update([
            'status' => 'expired',
            'subscription_ends_at' => now()->subDay(),
        ]);
        TenantContext::reset();

        $response = $this->actingAs($this->owner)->get(route('subscription.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard/Subscription/Index')
            ->where('tenant.is_active', false)
        );
    }

    public function test_suspended_tenant_is_blocked(): void
    {
        $this->tenant->update(['status' => 'suspended']);
        TenantContext::reset();

        $response = $this->actingAs($this->owner)->get(route('settings.store'));

        $response->assertForbidden();
    }

    public function test_active_tenant_can_access_dashboard_routes(): void
    {
        $response = $this->actingAs($this->owner)->get(route('settings.store'));

        $response->assertOk();
    }

    public function test_cannot_checkout_current_plan(): void
    {
        $response = $this->actingAs($this->owner)->post(route('subscription.checkout'), [
            'plan_id' => $this->proPlan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('sudah berlangganan', (string) session('error'));
    }

    public function test_downgrade_blocked_when_usage_exceeds_plan_limits(): void
    {
        $tinyPlan = Plan::create([
            'name' => 'Tiny Test',
            'slug' => 'tiny-test',
            'description' => 'Paket kecil',
            'price_monthly' => 10000,
            'price_yearly' => 100000,
            'max_outlets' => 1,
            'max_users' => 0,
            'max_products' => 10,
            'max_customers' => 10,
            'features' => [],
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->owner)->post(route('subscription.checkout'), [
            'plan_id' => $tinyPlan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('pengguna', (string) session('error'));
        $this->assertEquals($this->proPlan->id, $this->tenant->fresh()->plan_id);
        $this->assertFalse(
            TenantSubscription::where('tenant_id', $this->tenant->id)->exists()
        );
    }

    public function test_subscription_service_only_reports_configured_gateways(): void
    {
        config([
            'services.midtrans.server_key' => null,
            'services.xendit.secret_key' => null,
        ]);

        $service = app(SubscriptionService::class);
        $this->assertSame([], $service->availableGateways());

        config(['services.xendit.secret_key' => 'xnd-key']);

        $this->assertSame(['xendit'], $service->availableGateways());
    }
}
