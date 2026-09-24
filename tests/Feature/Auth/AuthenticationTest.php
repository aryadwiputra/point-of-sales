<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\BotGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ] + $this->botGuardPayload());

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard.access', absolute: false));
    }

    public function test_unverified_users_can_still_authenticate(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ] + $this->botGuardPayload());

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard.access', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ] + $this->botGuardPayload());

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_login_request_is_blocked_when_submitted_too_fast_without_valid_bot_guard_age(): void
    {
        $user = User::factory()->create();

        $payload = BotGuard::payload();

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            $payload['honeypot_field'] => '',
            $payload['token_field'] => $payload['token'],
        ]);

        $response->assertSessionHasErrors('human');
        $this->assertGuest();
    }

    public function test_security_headers_are_present_on_login_screen(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=(), accelerometer=(), gyroscope=()'
        );
    }

    public function test_unverified_user_can_access_dashboard_route(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/dashboard/access');

        $response->assertOk();
    }

    public function test_production_security_warnings_are_shared_to_dashboard(): void
    {
        config()->set('app.env', 'production');
        config()->set('app.debug', true);
        config()->set('app.url', 'http://localhost');
        config()->set('session.secure', false);

        $user = User::factory()->create();
        $user->assignRole(Role::create([
            'name' => 'super-admin',
            'guard_name' => 'web',
        ]));

        $response = $this->actingAs($user)->get('/dashboard/access');

        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard/Access')
            ->has('security.warnings', 3)
            ->where('security.warnings.0.key', 'app_debug')
        );
    }

    public function test_absolute_session_lifetime_forces_reauthentication(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->withSession([
                'security.session_started_at' => now()->subHours(13)->timestamp,
            ])
            ->actingAs($user)
            ->get('/dashboard/access');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
