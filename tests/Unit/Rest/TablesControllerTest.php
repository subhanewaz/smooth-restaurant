<?php

declare(strict_types=1);

namespace SmoothRestaurant\Tests\Unit\Rest;

use PHPUnit\Framework\TestCase;
use SmoothRestaurant\Domains\Tables\TableService;
use SmoothRestaurant\Rest\TablesController;
use SmoothRestaurant\Tests\Unit\Rest\Support\InMemoryTableRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Unit tests for the tables REST controller adapters.
 *
 * Verifies the request/response boundary only: each `handle*` adapter delegates
 * to its action and translates a TableException into the documented error code
 * and HTTP status. The action-layer contract itself is covered in
 * {@see TablesActionsTest}.
 */
final class TablesControllerTest extends TestCase
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

    public function test_handle_list_returns_200_with_next_states_and_qr_url(): void
    {
        $tables = new InMemoryTableRepository();
        $tables->create('Patio', 4);

        $response = $this->controller($tables)->handleList();

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertCount(1, $data);
        $this->assertSame('Patio', $data[0]['label']);
        $this->assertSame(4, $data[0]['seats']);
        $this->assertSame('free', $data[0]['state']);
        $this->assertSame(array( 'seated' ), $data[0]['next_states']);
        $this->assertSame('http://example.test/menu/?table=Patio', $data[0]['qr_url']);
    }

    public function test_handle_list_qr_url_defaults_to_home_menu(): void
    {
        $tables = new InMemoryTableRepository();
        $tables->create('Bar', 2);

        $response = $this->controller($tables)->handleList();

        $this->assertSame(
            'http://example.test/menu/?table=Bar',
            $response->get_data()[0]['qr_url']
        );
    }

    public function test_handle_list_qr_url_respects_the_filter(): void
    {
        add_filter('smooth_qr_menu_url', static fn (): string => 'https://x.test/order');

        $tables = new InMemoryTableRepository();
        $tables->create('Bar', 2);

        $response = $this->controller($tables)->handleList();

        $this->assertSame('https://x.test/order?table=Bar', $response->get_data()[0]['qr_url']);
    }

    public function test_handle_create_returns_201_with_the_new_table(): void
    {
        $response = $this->controller(new InMemoryTableRepository())->handleCreate(
            new WP_REST_Request(array( 'label' => '  Patio  ', 'seats' => 4 ))
        );

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(201, $response->get_status());
        $this->assertSame('Patio', $response->get_data()['label']);
        $this->assertSame(4, $response->get_data()['seats']);
        $this->assertSame(array( 'seated' ), $response->get_data()['next_states']);
    }

    public function test_handle_create_defaults_seats_when_omitted(): void
    {
        $response = $this->controller(new InMemoryTableRepository())->handleCreate(
            new WP_REST_Request(array( 'label' => 'Patio' ))
        );

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(2, $response->get_data()['seats']);
    }

    public function test_handle_create_rejects_a_missing_label(): void
    {
        $response = $this->controller(new InMemoryTableRepository())->handleCreate(
            new WP_REST_Request(array())
        );

        $this->assertError(400, TablesController::ERROR_INVALID, $response);
    }

    public function test_handle_create_rejects_a_blank_label(): void
    {
        $response = $this->controller(new InMemoryTableRepository())->handleCreate(
            new WP_REST_Request(array( 'label' => '   ' ))
        );

        $this->assertError(400, TablesController::ERROR_INVALID, $response);
    }

    public function test_handle_create_rejects_invalid_seats(): void
    {
        $response = $this->controller(new InMemoryTableRepository())->handleCreate(
            new WP_REST_Request(array( 'label' => 'Patio', 'seats' => 0 ))
        );

        $this->assertError(400, TablesController::ERROR_INVALID, $response);
    }

    public function test_handle_create_rejects_a_duplicate_label(): void
    {
        $tables = new InMemoryTableRepository();
        $tables->create('Patio', 4);

        $response = $this->controller($tables)->handleCreate(
            new WP_REST_Request(array( 'label' => 'Patio', 'seats' => 2 ))
        );

        $this->assertError(409, TablesController::ERROR_EXISTS, $response);
    }

    public function test_handle_change_state_moves_and_returns_next_states(): void
    {
        $tables = new InMemoryTableRepository();
        $id     = $tables->create('Patio', 4);

        $response = $this->controller($tables)->handleChangeState(
            new WP_REST_Request(array( 'id' => $id, 'state' => 'seated' ))
        );

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());
        $this->assertSame('seated', $response->get_data()['state']);
        $this->assertSame(array( 'ordered', 'free' ), $response->get_data()['next_states']);
    }

    public function test_handle_change_state_rejects_an_illegal_transition(): void
    {
        $tables = new InMemoryTableRepository();
        $id     = $tables->create('Patio', 4);

        $response = $this->controller($tables)->handleChangeState(
            new WP_REST_Request(array( 'id' => $id, 'state' => 'ordered' ))
        );

        $this->assertError(400, TablesController::ERROR_INVALID, $response);
    }

    public function test_handle_change_state_rejects_an_unknown_state(): void
    {
        $tables = new InMemoryTableRepository();
        $id     = $tables->create('Patio', 4);

        $response = $this->controller($tables)->handleChangeState(
            new WP_REST_Request(array( 'id' => $id, 'state' => 'nope' ))
        );

        $this->assertError(400, TablesController::ERROR_INVALID, $response);
    }

    public function test_handle_change_state_rejects_a_missing_state(): void
    {
        $tables = new InMemoryTableRepository();
        $id     = $tables->create('Patio', 4);

        $response = $this->controller($tables)->handleChangeState(
            new WP_REST_Request(array( 'id' => $id ))
        );

        $this->assertError(400, TablesController::ERROR_INVALID, $response);
    }

    public function test_handle_change_state_returns_404_for_unknown_id(): void
    {
        $response = $this->controller(new InMemoryTableRepository())->handleChangeState(
            new WP_REST_Request(array( 'id' => 42, 'state' => 'seated' ))
        );

        $this->assertError(404, TablesController::ERROR_NOT_FOUND, $response);
    }

    public function test_handle_archive_archives_the_table(): void
    {
        $tables = new InMemoryTableRepository();
        $id     = $tables->create('Patio', 4);

        $response = $this->controller($tables)->handleArchive(new WP_REST_Request(array( 'id' => $id )));

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());
        $this->assertSame(array( 'id' => $id, 'archived' => true ), $response->get_data());
        $this->assertNull($tables->find($id));
        $this->assertSame(array(), $tables->activeTables());
    }

    public function test_handle_archive_returns_404_for_unknown_id(): void
    {
        $response = $this->controller(new InMemoryTableRepository())->handleArchive(
            new WP_REST_Request(array( 'id' => 42 ))
        );

        $this->assertError(404, TablesController::ERROR_NOT_FOUND, $response);
    }

    public function test_handle_archive_returns_404_when_the_id_is_not_numeric(): void
    {
        $response = $this->controller(new InMemoryTableRepository())->handleArchive(
            new WP_REST_Request(array( 'id' => 'abc' ))
        );

        $this->assertError(404, TablesController::ERROR_NOT_FOUND, $response);
    }

    public function test_error_codes_are_stable_across_the_surface(): void
    {
        $this->assertSame('smooth_table_invalid', TablesController::ERROR_INVALID);
        $this->assertSame('smooth_table_not_found', TablesController::ERROR_NOT_FOUND);
        $this->assertSame('smooth_table_exists', TablesController::ERROR_EXISTS);
    }

    private function assertError(int $status, string $code, mixed $response): void
    {
        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertSame($code, $response->get_error_code());
        $this->assertSame($status, $response->get_error_data()['status']);
    }
}
