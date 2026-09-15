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

    public function test_strict_audit_passes_for_clean_system_seed(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->artisan('outlet:audit', ['--strict' => true])
            ->assertSuccessful();
    }

    public function test_legacy_audit_is_read_only(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->artisan('outlet:legacy-audit')
            ->expectsOutputToContain('Legacy outlet audit (read-only; no backfill performed)')
            ->assertSuccessful();
    }
}
