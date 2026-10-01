<?php

/**
 * Tables REST controller.
 *
 * @package SmoothRestaurant
 */

declare(strict_types=1);

namespace SmoothRestaurant\Rest;

use SmoothRestaurant\Contracts\RestaurantTableRepositoryInterface;
use SmoothRestaurant\Domains\Tables\TableService;
use SmoothRestaurant\Domains\Tables\TableState;
use SmoothRestaurant\Exceptions\TableException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class TablesController
 *
 * Owns the Free tables REST surface: list, create, change state, and archive.
 *
 * The class is deliberately two-layered:
 * - Action methods (`listTables`, `createTable`, `changeState`, `archiveTable`)
 *   speak only plain arrays and throw {@see TableException}, so the whole
 *   behaviour contract is unit-testable without WordPress.
 * - `handle*` adapters take a `WP_REST_Request` and return a `WP_REST_Response`
 *   or `WP_Error`, translating the thrown exception into an HTTP status.
 *
 * Responses enrich every table with domain-derived `next_states` and a
 * display-only `qr_url` so clients never re-implement rules or URL logic.
 * Free never creates a table session.
 */
final class TablesController
{
    /**
     * Error code for an invalid payload, state, or transition. Maps to HTTP 400.
     */
    public const ERROR_INVALID = 'smooth_table_invalid';

    /**
     * Error code for an unknown (or archived) table id. Maps to HTTP 404.
     */
    public const ERROR_NOT_FOUND = 'smooth_table_not_found';

    /**
     * Error code for a label already used by an active table. Maps to HTTP 409.
     */
    public const ERROR_EXISTS = 'smooth_table_exists';

    /**
     * @deprecated Use ERROR_EXISTS instead.
     */
    public const ERROR_DUPLICATE = self::ERROR_EXISTS;

    /**
     * Seat count applied when a create request omits `seats`.
     */
    private const DEFAULT_SEATS = 2;

    /**
     * @param TableService                      $tables     Pure table domain service.
     * @param RestaurantTableRepositoryInterface $repository Table repository.
     */
    public function __construct(
        private TableService $tables,
        private RestaurantTableRepositoryInterface $repository
    ) {
    }

    // -- Actions: plain arrays in, plain arrays out -----------------------------------------------

    /**
     * List every active table.
     *
     * @return list<array<string, mixed>>
     */
    public function listTables(): array
    {
        $tables = array();
        foreach ($this->repository->activeTables() as $row) {
            $tables[] = $this->present($row);
        }

        return $tables;
    }

    /**
     * Create a table from `{label, seats}`.
     *
     * @param array<string, mixed> $params Request parameters.
     * @return array<string, mixed> The created table.
     * @throws TableException When the label is invalid, seats are invalid, or the label already exists.
     */
    public function createTable(array $params): array
    {
        $rawLabel = $params['label'] ?? null;
        if (! is_string($rawLabel)) {
            throw new TableException('A table label is required.', self::ERROR_INVALID);
        }

        try {
            $label = $this->tables->normalizeLabel($rawLabel);
        } catch (TableException $exception) {
            throw new TableException($exception->getMessage(), self::ERROR_INVALID);
        }

        $seats = $this->resolveSeats($params['seats'] ?? null);
        if (null === $seats) {
            throw new TableException('Seats must be a positive whole number.', self::ERROR_INVALID);
        }

        if ($this->repository->labelExists($label)) {
            throw new TableException(
                sprintf('A table labelled "%s" already exists.', $label),
                self::ERROR_EXISTS
            );
        }

        $id  = $this->repository->create($label, $seats);
        $row = $this->repository->find($id);
        if (null === $row) {
            throw new TableException('The created table could not be loaded.', self::ERROR_NOT_FOUND);
        }

        return $this->present($row);
    }

    /**
     * Move a table to a new state, validating against the state graph.
     *
     * @param int                  $id     Table id.
     * @param array<string, mixed> $params Request parameters carrying `state`.
     * @return array<string, mixed> The updated table.
     * @throws TableException When the table is missing, the state is unknown, or the move is illegal.
     */
    public function changeState(int $id, array $params): array
    {
        $row = $this->repository->find($id);
        if (null === $row) {
            throw new TableException(sprintf('Table %d was not found.', $id), self::ERROR_NOT_FOUND);
        }

        $target = $params['state'] ?? null;
        if (! is_string($target)) {
            throw new TableException('A target state is required.', self::ERROR_INVALID);
        }

        try {
            $state = $this->tables->move((string) $row['state'], $target);
        } catch (TableException $exception) {
            throw new TableException($exception->getMessage(), self::ERROR_INVALID);
        }

        $this->repository->updateState($id, $state->value);

        return $this->present($this->repository->find($id) ?? $row);
    }

    /**
     * Archive (soft-delete) a table.
     *
     * @param int $id Table id.
     * @return array{id: int, archived: bool}
     * @throws TableException When the table is not found.
     */
    public function archiveTable(int $id): array
    {
        if (null === $this->repository->find($id)) {
            throw new TableException(sprintf('Table %d was not found.', $id), self::ERROR_NOT_FOUND);
        }

        $this->repository->archive($id);

        return array(
            'id'       => $id,
            'archived' => true,
        );
    }

    // -- Adapters: request/response boundary --------------------------------------------------------

    /**
     * REST adapter for {@see listTables()}.
     */
    public function handleList(): WP_REST_Response
    {
        return new WP_REST_Response($this->listTables(), 200);
    }

    /**
     * REST adapter for {@see createTable()}.
     */
    public function handleCreate(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return $this->respond(
            fn (): array => $this->createTable($request->get_params()),
            201
        );
    }

    /**
     * REST adapter for {@see changeState()}.
     */
    public function handleChangeState(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = $this->requestId($request);

        return $this->respond(
            fn (): array => $this->changeState($id, $request->get_params()),
            200
        );
    }

    /**
     * REST adapter for {@see archiveTable()}.
     */
    public function handleArchive(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = $this->requestId($request);

        return $this->respond(
            fn (): array => $this->archiveTable($id),
            200
        );
    }

    // -- Legacy adapters (backward compatibility with existing tests/providers) ---------------------

    /**
     * @deprecated Use handleList() instead.
     */
    public function index(): WP_REST_Response
    {
        return $this->handleList();
    }

    /**
     * @deprecated Use handleCreate() instead.
     */
    public function create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return $this->handleCreate($request);
    }

    /**
     * @deprecated Use handleChangeState() instead.
     */
    public function updateState(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return $this->handleChangeState($request);
    }

    /**
     * @deprecated Use handleArchive() instead.
     */
    public function delete(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return $this->handleArchive($request);
    }

    // -- Internals ---------------------------------------------------------------------------------

    /**
     * Run an action and wrap the result, mapping a TableException to WP_Error.
     *
     * @param callable(): array<string, mixed> $action Action to run.
     * @param int                              $status Success status code.
     * @return WP_REST_Response|WP_Error
     */
    private function respond(callable $action, int $status): WP_REST_Response|WP_Error
    {
        try {
            return new WP_REST_Response($action(), $status);
        } catch (TableException $exception) {
            return $this->toError($exception);
        }
    }

    /**
     * Extract a positive table id from the route/request.
     *
     * @param WP_REST_Request $request Incoming request.
     * @return int Table id, 0 when absent or not numeric.
     */
    private function requestId(WP_REST_Request $request): int
    {
        $id = $request->get_param('id');

        return is_numeric($id) ? (int) $id : 0;
    }

    /**
     * Translate a TableException into a WP_Error with the matching status.
     *
     * @param TableException $exception Thrown by an action.
     * @return WP_Error
     */
    private function toError(TableException $exception): WP_Error
    {
        $message = $exception->getMessage();

        return match ($exception->errorCode()) {
            self::ERROR_EXISTS    => $this->error(409, self::ERROR_EXISTS, $message),
            self::ERROR_NOT_FOUND => $this->error(404, self::ERROR_NOT_FOUND, $message),
            default               => $this->error(400, self::ERROR_INVALID, $message),
        };
    }

    /**
     * Resolve the `seats` parameter, defaulting when omitted.
     *
     * @param mixed $value Raw request value.
     * @return int|null Positive seat count, or null when the value is invalid.
     */
    private function resolveSeats(mixed $value): ?int
    {
        if (null === $value || '' === $value) {
            return self::DEFAULT_SEATS;
        }

        if (is_int($value)) {
            $seats = $value;
        } elseif (is_string($value) && ctype_digit($value)) {
            $seats = (int) $value;
        } else {
            return null;
        }

        return $seats >= 1 ? $seats : null;
    }

    /**
     * Shape a repository row into the REST table object.
     *
     * @param array<string, mixed> $row Repository row.
     * @return array<string, mixed>
     */
    private function present(array $row): array
    {
        $state = TableState::tryFrom((string) ($row['state'] ?? '')) ?? TableState::Free;
        $label = (string) ($row['label'] ?? '');

        return array(
            'id'          => (int) ($row['id'] ?? 0),
            'label'       => $label,
            'seats'       => (int) ($row['seats'] ?? 0),
            'state'       => $state->value,
            'next_states' => array_map(
                static fn (TableState $next): string => $next->value,
                $state->nextStates()
            ),
            'qr_url'      => $this->tables->menuUrl($this->menuBaseUrl(), $label),
        );
    }

    /**
     * Resolve the base menu URL for QR links.
     *
     * Defaults to `home_url('/menu/')` and can be redirected with the
     * `smooth_qr_menu_url` filter.
     *
     * @return string
     */
    private function menuBaseUrl(): string
    {
        $default = function_exists('home_url') ? home_url('/menu/') : '/menu/';

        /**
         * Filters the base menu URL encoded into Free table QR cards.
         *
         * The `?table=` label is appended by the plugin for display only; the
         * menu owner decides whether to consume it. Return a full URL; an empty
         * or non-string value falls back to the default.
         *
         * @since 0.1.0
         *
         * @param string $url Base menu URL. Defaults to `home_url('/menu/')`.
         *
         * @example
         * add_filter( 'smooth_qr_menu_url', static fn (): string => home_url( '/order/' ) );
         */
        $filtered = apply_filters('smooth_qr_menu_url', $default);

        return is_string($filtered) && '' !== $filtered ? $filtered : $default;
    }

    /**
     * Build a WP_Error carrying an HTTP status.
     *
     * @param int    $status  HTTP status code.
     * @param string $code    Machine-readable error code.
     * @param string $message Human-readable message.
     * @return WP_Error
     */
    private function error(int $status, string $code, string $message): WP_Error
    {
        return new WP_Error($code, $message, array('status' => $status));
    }
}
