<?php

declare(strict_types=1);

namespace SmoothRestaurant\Tests\Unit\Rest;

use PHPUnit\Framework\TestCase;
use SmoothRestaurant\Domains\Tables\TableService;
use SmoothRestaurant\Exceptions\TableException;
use SmoothRestaurant\Rest\TablesController;
use SmoothRestaurant\Tests\Unit\Rest\Support\InMemoryTableRepository;
use WP_REST_Response;

/**
 * Unit tests for the tables action layer.
 *
 * The action methods (`listTables`, `createTable`, `changeState`,
 * `archiveTable`) speak only plain arrays and throw TableException, so the
 * whole behaviour contract is verified here without any WordPress runtime.
 * The `handle*` adapters are covered separately in {@see TablesControllerTest}.
 */
final class TablesActionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        sr_test_reset_stubs();
    }

    private function controller(InMemoryTableRepository $tables): TablesController
    {
        return new TablesController(new TableService(), $tables);
    }

    public function test_list_tables_returns_plain_arrays_with_the_full_shape(): void
    {
        $tables = new InMemoryTableRepository();
        $id     = $tables->create('Patio', 4);

        $result = $this->controller($tables)->listTables();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame(
            array('id', 'label', 'seats', 'state', 'next_states', 'qr_url'),
            array_keys($result[0])
        );
        $this->assertSame($id, $result[0]['id']);
        $this->assertSame('Patio', $result[0]['label']);
        $this->assertSame(4, $result[0]['seats']);
        $this->assertSame('free', $result[0]['state']);
        $this->assertSame(array('seated'), $result[0]['next_states']);
        $this->assertSame('http://example.test/menu/?table=Patio', $result[0]['qr_url']);
    }

    public function test_list_tables_excludes_archived_tables(): void
    {
        $tables = new InMemoryTableRepository();
        $id     = $tables->create('Patio', 4);
        $tables->archive($id);

        $this->assertSame(array(), $this->controller($tables)->listTables());
    }

    public function test_create_table_returns_the_created_row(): void
    {
        $result = $this->controller(new InMemoryTableRepository())->createTable(
            array('label' => '  Patio  ', 'seats' => 4)
        );

        $this->assertSame('Patio', $result['label']);
        $this->assertSame(4, $result['seats']);
        $this->assertSame('free', $result['state']);
        $this->assertSame(array('seated'), $result['next_states']);
    }

    public function test_create_table_defaults_seats_when_omitted(): void
    {
        $result = $this->controller(new InMemoryTableRepository())->createTable(
            array('label' => 'Patio')
        );

        $this->assertSame(2, $result['seats']);
    }

    public function test_create_table_accepts_numeric_string_seats(): void
    {
        $result = $this->controller(new InMemoryTableRepository())->createTable(
            array('label' => 'Patio', 'seats' => '6')
        );

        $this->assertSame(6, $result['seats']);
    }

    public function test_create_table_persists_the_row(): void
    {
        $tables = new InMemoryTableRepository();

        $created = $this->controller($tables)->createTable(array('label' => 'Bar', 'seats' => 2));

        $this->assertSame('Bar', $tables->activeRow($created['id'])['label']);
    }

    /**
     * @dataProvider invalidCreatePayloads
     *
     * @param array<string, mixed> $params Payload expected to be rejected.
     */
    public function test_create_table_rejects_invalid_payloads(array $params): void
    {
        $controller = $this->controller(new InMemoryTableRepository());

        try {
            $controller->createTable($params);
        } catch (TableException $exception) {
            $this->assertSame(TablesController::ERROR_INVALID, $exception->errorCode());

            return;
        }

        $this->fail('Expected a TableException.');
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function invalidCreatePayloads(): array
    {
        return array(
            'missing label'      => array(array()),
            'non-string label'   => array(array('label' => 42)),
            'blank label'        => array(array('label' => '   ')),
            'zero seats'         => array(array('label' => 'Patio', 'seats' => 0)),
            'negative seats'     => array(array('label' => 'Patio', 'seats' => -3)),
            'non-numeric seats'  => array(array('label' => 'Patio', 'seats' => 'many')),
            'array seats'        => array(array('label' => 'Patio', 'seats' => array(4))),
        );
    }

    public function test_create_table_rejects_a_duplicate_label_with_the_exists_code(): void
    {
        $tables = new InMemoryTableRepository();
        $tables->create('Patio', 4);

        $this->expectException(TableException::class);
        $this->expectExceptionMessage('already exists');

        try {
            $this->controller($tables)->createTable(array('label' => 'Patio', 'seats' => 2));
        } catch (TableException $exception) {
            $this->assertSame(TablesController::ERROR_EXISTS, $exception->errorCode());
            throw $exception;
        }
    }

    public function test_create_table_never_rejects_an_archived_label_collision(): void
    {
        $tables = new InMemoryTableRepository();
        $tables->archive($tables->create('Patio', 4));

        $created = $this->controller($tables)->createTable(array('label' => 'Patio'));

        $this->assertSame('Patio', $created['label']);
    }

    public function test_change_state_returns_the_updated_row(): void
    {
        $tables = new InMemoryTableRepository();
        $id     = $tables->create('Patio', 4);

        $result = $this->controller($tables)->changeState($id, array('state' => 'seated'));

        $this->assertSame('seated', $result['state']);
        $this->assertSame(array( 'ordered', 'free' ), $result['next_states']);
        $this->assertSame('seated', $tables->activeRow($id)['state']);
    }

    public function test_change_state_throws_not_found_for_an_unknown_id(): void
    {
        try {
            $this->controller(new InMemoryTableRepository())->changeState(42, array('state' => 'seated'));
        } catch (TableException $exception) {
            $this->assertSame(TablesController::ERROR_NOT_FOUND, $exception->errorCode());

            return;
        }

        $this->fail('Expected a TableException.');
    }

    public function test_change_state_throws_not_found_for_an_archived_table(): void
    {
        $tables = new InMemoryTableRepository();
        $id     = $tables->create('Patio', 4);
        $tables->archive($id);

        try {
            $this->controller($tables)->changeState($id, array('state' => 'seated'));
        } catch (TableException $exception) {
            $this->assertSame(TablesController::ERROR_NOT_FOUND, $exception->errorCode());

            return;
        }

        $this->fail('Expected a TableException.');
    }

    /**
     * @dataProvider invalidStatePayloads
     *
     * @param array<string, mixed> $params Payload expected to be rejected.
     */
    public function test_change_state_rejects_invalid_payloads(array $params): void
    {
        $tables = new InMemoryTableRepository();
        $id     = $tables->create('Patio', 4);

        try {
            $this->controller($tables)->changeState($id, $params);
        } catch (TableException $exception) {
            $this->assertSame(TablesController::ERROR_INVALID, $exception->errorCode());

            return;
        }

        $this->fail('Expected a TableException.');
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function invalidStatePayloads(): array
    {
        return array(
            'missing state'    => array(array()),
            'empty state'      => array(array('state' => '')),
            'unknown state'    => array(array('state' => 'nope')),
            'illegal jump'     => array(array('state' => 'ordered')),
            'illegal to paid'  => array(array('state' => 'paid')),
            'non-string state' => array(array('state' => 7)),
        );
    }

    public function test_change_state_leaves_the_row_untouched_when_rejected(): void
    {
        $tables = new InMemoryTableRepository();
        $id     = $tables->create('Patio', 4);

        try {
            $this->controller($tables)->changeState($id, array('state' => 'nope'));
        } catch (TableException) {
            // Expected.
        }

        $this->assertSame('free', $tables->activeRow($id)['state']);
    }

    public function test_archive_table_soft_deletes_the_row(): void
    {
        $tables = new InMemoryTableRepository();
        $id     = $tables->create('Patio', 4);

        $result = $this->controller($tables)->archiveTable($id);

        $this->assertSame(
            array(
                'id'       => $id,
                'archived' => true,
            ),
            $result
        );
        $this->assertNull($tables->find($id));
        $this->assertSame(array(), $tables->activeTables());
    }

    public function test_archive_table_throws_not_found_for_an_unknown_id(): void
    {
        try {
            $this->controller(new InMemoryTableRepository())->archiveTable(42);
        } catch (TableException $exception) {
            $this->assertSame(TablesController::ERROR_NOT_FOUND, $exception->errorCode());

            return;
        }

        $this->fail('Expected a TableException.');
    }

    public function test_qr_url_is_a_display_only_label_without_a_session_token(): void
    {
        $tables = new InMemoryTableRepository();
        $created = $this->controller($tables)->createTable(array('label' => 'Patio'));

        $this->assertStringNotContainsString('session', $created['qr_url']);
        $this->assertStringNotContainsString('token', $created['qr_url']);
        $this->assertSame('http://example.test/menu/?table=Patio', $created['qr_url']);
    }

    public function test_handle_list_wraps_the_action_in_a_200_response(): void
    {
        $tables = new InMemoryTableRepository();
        $tables->create('Patio', 4);

        $response = $this->controller($tables)->handleList();

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());
        $this->assertSame('Patio', $response->get_data()[0]['label']);
    }
}
