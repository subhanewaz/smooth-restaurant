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
 * Unit tests for the tables REST controller.
 *
 * Exercises the full response contract (status codes, next_states, qr_url)
 * against an in-memory repository. Real route registration on WordPress is
 * covered separately on wp-env.
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

    public function test_index_lists_tables_with_next_states_and_qr_url(): void
    {
        $tables = new InMemoryTableRepository();
        $tables->create('Patio', 4);

        $response = $this->controller($tables)->index();

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

    public function test_index_qr_url_defaults_to_home_menu(): void
    {
        $tables = new InMemoryTableRepository();
        $tables->create('Bar', 2);

        $response = $this->controller($tables)->index();

        $this->assertSame(
            'http://example.test/menu/?table=Bar',
            $response->get_data()[0]['qr_url']
        );
    }

    public function test_index_qr_url_respects_the_filter(): void
    {
        add_filter('smooth_qr_menu_url', static fn (): string => 'https://x.test/order');

        $tables = new InMemoryTableRepository();
        $tables->create('Bar', 2);

        $response = $this->controller($tables)->index();

        $this->assertSame('https://x.test/order?table=Bar', $response->get_data()[0]['qr_url']);
    }

    public function test_create_returns_201_with_the_new_table(): void
    {
        $response = $this->controller(new InMemoryTableRepository())->create(
            new WP_REST_Request(array( 'label' => '  Patio  ', 'seats' => 4 ))
        );

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(201, $response->get_status());
        $this->assertSame('Patio', $response->get_data()['label']);
        $this->assertSame(4, $response->get_data()['seats']);
        $this->assertSame(array( 'seated' ), $response->get_data()['next_states']);
    }

    public function test_create_defaults_seats_when_omitted(): void
    {
        $response = $this->controller(new InMemoryTableRepository())->create(
            new WP_REST_Request(array( 'label' => 'Patio' ))
        );

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(2, $response->get_data()['seats']);
    }

    public function test_create_rejects_a_missing_label(): void
    {
        $response = $this->controller(new InMemoryTableRepository())->create(
            new WP_REST_Request(array())
        );

        $this->assertError(400, TablesController::ERROR_INVALID, $response);
    }

    public function test_create_rejects_a_blank_label(): void
    {
        $response = $this->controller(new InMemoryTableRepository())->create(
            new WP_REST_Request(array( 'label' => '   ' ))
        );

        $this->assertError(400, TablesController::ERROR_INVALID, $response);
    }

    public function test_create_rejects_invalid_seats(): void
    {
        $response = $this->controller(new InMemoryTableRepository())->create(
            new WP_REST_Request(array( 'label' => 'Patio', 'seats' => 0 ))
        );

        $this->assertError(400, TablesController::ERROR_INVALID, $response);
    }

    public function test_create_rejects_a_duplicate_label(): void
    {
        $tables = new InMemoryTableRepository();
        $tables->create('Patio', 4);

        $response = $this->controller($tables)->create(
            new WP_REST_Request(array( 'label' => 'Patio', 'seats' => 2 ))
        );

        $this->assertError(409, TablesController::ERROR_DUPLICATE, $response);
    }

    public function test_update_state_moves_and_returns_next_states(): void
    {
        $tables = new InMemoryTableRepository();
        $id     = $tables->create('Patio', 4);

        $response = $this->controller($tables)->updateState(
            new WP_REST_Request(array( 'id' => $id, 'state' => 'seated' ))
        );

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());
        $this->assertSame('seated', $response->get_data()['state']);
        $this->assertSame(array( 'ordered', 'free' ), $response->get_data()['next_states']);
    }

    public function test_update_state_rejects_an_illegal_transition(): void
    {
        $tables = new InMemoryTableRepository();
        $id     = $tables->create('Patio', 4);

        $response = $this->controller($tables)->updateState(
            new WP_REST_Request(array( 'id' => $id, 'state' => 'ordered' ))
        );

        $this->assertError(400, TablesController::ERROR_INVALID, $response);
    }

    public function test_update_state_rejects_an_unknown_state(): void
    {
        $tables = new InMemoryTableRepository();
        $id     = $tables->create('Patio', 4);

        $response = $this->controller($tables)->updateState(
            new WP_REST_Request(array( 'id' => $id, 'state' => 'nope' ))
        );

        $this->assertError(400, TablesController::ERROR_INVALID, $response);
    }

    public function test_update_state_returns_404_for_unknown_id(): void
    {
        $response = $this->controller(new InMemoryTableRepository())->updateState(
            new WP_REST_Request(array( 'id' => 42, 'state' => 'seated' ))
        );

        $this->assertError(404, TablesController::ERROR_NOT_FOUND, $response);
    }

    public function test_delete_archives_the_table(): void
    {
        $tables = new InMemoryTableRepository();
        $id     = $tables->create('Patio', 4);

        $response = $this->controller($tables)->delete(new WP_REST_Request(array( 'id' => $id )));

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());
        $this->assertSame(array( 'id' => $id, 'archived' => true ), $response->get_data());
        $this->assertNull($tables->find($id));
        $this->assertSame(array(), $tables->activeTables());
    }

    public function test_delete_returns_404_for_unknown_id(): void
    {
        $response = $this->controller(new InMemoryTableRepository())->delete(
            new WP_REST_Request(array( 'id' => 42 ))
        );

        $this->assertError(404, TablesController::ERROR_NOT_FOUND, $response);
    }

    private function assertError(int $status, string $code, mixed $response): void
    {
        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertSame($code, $response->get_error_code());
        $this->assertSame($status, $response->get_error_data()['status']);
    }
}
