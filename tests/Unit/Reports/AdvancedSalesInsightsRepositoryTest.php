<?php

namespace Tests\Unit\Reports;

use App\Repositories\Reports\AdvancedSalesInsightsRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdvancedSalesInsightsRepositoryTest extends TestCase
{
    #[DataProvider('hourBucketExpressions')]
    public function test_hour_bucket_expression_matches_database_driver(string $driver, string $expression): void
    {
        $connection = Mockery::mock(ConnectionInterface::class);
        $connection->shouldReceive('getDriverName')->once()->andReturn($driver);
        DB::shouldReceive('connection')->once()->andReturn($connection);

        $this->assertSame(
            $expression,
            app(AdvancedSalesInsightsRepository::class)->hourBucketExpression()
        );
    }

    public static function hourBucketExpressions(): array
    {
        return [
            'SQLite' => ['sqlite', "CAST(strftime('%H', created_at) AS INTEGER)"],
            'PostgreSQL' => ['pgsql', 'CAST(EXTRACT(HOUR FROM created_at) AS INTEGER)'],
            'MySQL' => ['mysql', 'HOUR(created_at)'],
        ];
    }
}
