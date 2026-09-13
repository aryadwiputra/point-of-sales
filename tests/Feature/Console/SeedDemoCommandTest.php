<?php

namespace Tests\Feature\Console;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedDemoCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_demo_force_regenerates_full_demo_dataset(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->artisan('seed:demo', ['--force' => true])->assertSuccessful();

        $admin = User::where('email', 'arya@gmail.com')->first();

        $this->assertNotNull($admin);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue($admin->hasRole('super-admin'));

        $cashier = User::where('email', 'cashier@gmail.com')->first();

        $this->assertNotNull($cashier);
        $this->assertNotNull($cashier->email_verified_at);

        $this->assertGreaterThan(0, Product::count());
    }

    public function test_seed_demo_without_confirmation_aborts(): void
    {
        $this->artisan('seed:demo')
            ->expectsConfirmation('Continue?', 'no')
            ->assertFailed();

        $this->assertNull(User::where('email', 'arya@gmail.com')->first());
        $this->assertSame(0, Product::count());
    }
}
