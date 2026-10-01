<?php

declare(strict_types=1);

namespace SmoothRestaurant\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use SmoothRestaurant\Database\Repositories\RestaurantTableRepository;
use SmoothRestaurant\Tests\Unit\Database\Support\RecordingWpdb;

/**
 * Unit tests for the restaurant tables repository query methods.
 *
 * Free QR cards intentionally avoid creating a table session: the repository
 * only exposes plain table CRUD over the smooth_tables row.
 */
final class RestaurantTableRepositoryTest extends TestCase
{
    public function test_schema_includes_state_column_defaulting_to_free(): void
    {
        $schema = (new RestaurantTableRepository(new RecordingWpdb()))->schema();

        $this->assertStringContainsString("state varchar(20) NOT NULL DEFAULT 'free'", $schema);
        $this->assertStringContainsString("status varchar(32) NOT NULL DEFAULT 'active'", $schema);
        $this->assertStringContainsString('KEY status (status)', $schema);
    }

    public function test_active_tables_returns_mapped_rows(): void
    {
        $db          = new RecordingWpdb();
        $db->results = [
            ['id' => '7', 'label' => 'Patio', 'seats' => '4', 'state' => 'free', 'status' => 'active'],
        ];

        $tables = (new RestaurantTableRepository($db))->activeTables();

        $this->assertSame(
            [['id' => 7, 'label' => 'Patio', 'seats' => 4, 'state' => 'free', 'status' => 'active']],
            $tables
        );
    }

    public function test_find_returns_a_mapped_row(): void
    {
        $db          = new RecordingWpdb();
        $db->results = [
            ['id' => '3', 'label' => 'Bar', 'seats' => '2', 'state' => 'seated', 'status' => 'active'],
        ];

        $table = (new RestaurantTableRepository($db))->find(3);

        $this->assertSame(
            ['id' => 3, 'label' => 'Bar', 'seats' => 2, 'state' => 'seated', 'status' => 'active'],
            $table
        );
    }

    public function test_find_returns_null_when_absent(): void
    {
        $this->assertNull((new RestaurantTableRepository(new RecordingWpdb()))->find(99));
    }

    public function test_create_inserts_an_active_free_table_and_returns_id(): void
    {
        $db = new RecordingWpdb();

        $id = (new RestaurantTableRepository($db))->create('Patio', 4);

        $this->assertSame(1, $id);
        $this->assertSame('wp_smooth_tables', $db->inserted[0]['table']);
        $this->assertSame(
            ['label' => 'Patio', 'seats' => 4, 'state' => 'free', 'status' => 'active'],
            $db->inserted[0]['data']
        );
    }

    public function test_create_accepts_an_explicit_state(): void
    {
        $db = new RecordingWpdb();

        $id = (new RestaurantTableRepository($db))->create('Patio', 4, 'seated');

        $this->assertSame(1, $id);
        $this->assertSame(
            ['label' => 'Patio', 'seats' => 4, 'state' => 'seated', 'status' => 'active'],
            $db->inserted[0]['data']
        );
    }

    public function test_update_state_targets_the_active_row(): void
    {
        $db = new RecordingWpdb();

        $updated = (new RestaurantTableRepository($db))->updateState(5, 'seated');

        $this->assertTrue($updated);
        $this->assertSame('wp_smooth_tables', $db->updated[0]['table']);
        $this->assertSame(['state' => 'seated'], $db->updated[0]['data']);
        $this->assertSame(['id' => 5, 'status' => 'active'], $db->updated[0]['where']);
    }

    public function test_archive_flips_status_to_archived(): void
    {
        $db = new RecordingWpdb();

        $archived = (new RestaurantTableRepository($db))->archive(5);

        $this->assertTrue($archived);
        $this->assertSame(['status' => 'archived'], $db->updated[0]['data']);
        $this->assertSame(['id' => 5, 'status' => 'active'], $db->updated[0]['where']);
    }

    public function test_label_exists_reflects_query_results(): void
    {
        $db     = new RecordingWpdb();
        $repository = new RestaurantTableRepository($db);

        $this->assertFalse($repository->labelExists('Patio'));

        $db->results = [['id' => '1']];

        $this->assertTrue($repository->labelExists('Patio'));
    }
}
