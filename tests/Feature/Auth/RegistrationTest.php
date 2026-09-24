<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_returns_404_when_public_registration_is_disabled(): void
    {
        $response = $this->get('/register');

        $response->assertNotFound();
    }

    public function test_registration_screen_can_be_rendered_when_public_registration_is_enabled(): void
    {
        Config::set('security.auth.public_registration', true);

        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register_when_public_registration_is_enabled(): void
    {
        Config::set('security.auth.public_registration', true);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ] + $this->botGuardPayload());

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard.access', absolute: false));
    }

    public function test_register_request_is_throttled_when_public_registration_is_enabled(): void
    {
        Config::set('security.auth.public_registration', true);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->post('/register', [
                'name' => "Test User {$attempt}",
                'email' => "test{$attempt}@example.com",
                'password' => 'password',
                'password_confirmation' => 'password',
            ] + $this->botGuardPayload());
            auth()->logout();
        }

        $response = $this->post('/register', [
            'name' => 'Test User 4',
            'email' => 'test4@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ] + $this->botGuardPayload());

        $response->assertStatus(429);
    }

    public function test_register_request_is_blocked_when_bot_guard_is_invalid(): void
    {
        Config::set('security.auth.public_registration', true);

        $response = $this->from('/register')->post('/register', [
            'name' => 'Bot User',
            'email' => 'bot@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            config('security.bot_guard.honeypot_field') => '',
            config('security.bot_guard.token_field') => 'invalid-token',
        ]);

        $response->assertSessionHasErrors('human');
        $this->assertGuest();
    }

    public function test_login_page_hides_register_link_when_public_registration_is_disabled(): void
    {
        $response = $this->get('/login');

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('canRegister', false)
        );
    }
}
