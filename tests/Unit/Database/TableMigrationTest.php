<?php

declare(strict_types=1);

namespace SmoothRestaurant\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use SmoothRestaurant\Database\MigrationRunner;
use SmoothRestaurant\Database\Repositories\RestaurantTableRepository;
use SmoothRestaurant\Database\Repositories\TableSessionRepository;

/**
 * Unit tests for the smooth_tables schema migration.
 *
 * The Free QR cards change creates the restaurant tables table only. It must
 * never create smooth_table_sessions: Free links carry no session token.
 *
 * ActivatorTest runs migrations without WordPress, so the migration must be a
 * safe no-op when the WordPress environment is absent (dbDelta unavailable).
 */
final class TableMigrationTest extends TestCase
{
    private string $stored = '0.0.0';

    protected function setUp(): void
    {
        parent::setUp();
        $this->stored = '0.0.0';
    }

    public function test_target_version_is_bumped(): void
    {
        $this->assertSame('0.2.0', MigrationRunner::TARGET_VERSION);
    }

    public function test_defaults_register_the_smooth_tables_migration(): void
    {
        $this->assertSame(['0.2.0'], \array_keys(MigrationRunner::defaults()));
    }

    public function test_schema_repositories_create_only_smooth_tables(): void
    {
        $repositories = MigrationRunner::schemaRepositories();

        $this->assertSame([RestaurantTableRepository::class], $repositories);
        $this->assertNotContains(TableSessionRepository::class, $repositories);
    }

    public function test_defaults_migration_is_a_safe_noop_without_wordpress(): void
    {
        $runner = new MigrationRunner(
            MigrationRunner::defaults(),
            function (): string {
                return $this->stored;
            },
            function (string $version): void {
                $this->stored = $version;
            }
        );

        $result = $runner->migrate();

        $this->assertSame('0.2.0', $result);
        $this->assertSame('0.2.0', $this->stored);
    }
}
