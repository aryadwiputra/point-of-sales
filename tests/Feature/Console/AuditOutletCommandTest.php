<?php

namespace Tests\Feature\Console;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditOutletCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_command_is_read_only_and_reports_system_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->artisan('outlet:audit')
            ->expectsOutputToContain('Outlet data audit (read-only)')
            ->expectsOutputToContain('Outlets: 1')
            ->assertSuccessful();
    }
}
