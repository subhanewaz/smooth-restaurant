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
 * Responses enrich every table with domain-derived `next_states` and a
 * display-only `qr_url` so clients never re-implement rules or URL logic.
 * Free never creates a table session.
 */
final class TablesController
{
    /**
     * Error code for an invalid payload, state, or transition.
     */
    public const ERROR_INVALID = 'smooth_table_invalid';

    /**
     * Error code for an unknown (or archived) table id.
     */
    public const ERROR_NOT_FOUND = 'smooth_table_not_found';

    /**
     * Error code for a label already used by an active table.
     */
    public const ERROR_DUPLICATE = 'smooth_table_duplicate';

    /**
     * Seat count applied when a create request omits `seats`.
     */
    private const DEFAULT_SEATS = 2;

    /**
     * @param TableService                     $tables Pure table domain service.
     * @param RestaurantTableRepositoryInterface $repository Table repository.
     */
    public function __construct(
        private TableService $tables,
        private RestaurantTableRepositoryInterface $repository
    ) {
    }

    /**
     * List active tables with `next_states` and `qr_url`.
     */
    public function index(): WP_REST_Response
    {
        $tables = array();
        foreach ($this->repository->activeTables() as $row) {
            $tables[] = $this->present($row);
        }

        return new WP_REST_Response($tables, 200);
    }

    /**
     * Create a table from `{label, seats}`.
     */
    public function create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $rawLabel = $request->get_param('label');
        if (! \is_string($rawLabel)) {
            return $this->error(400, self::ERROR_INVALID, 'A table label is required.');
        }

        try {
            $label = $this->tables->normalizeLabel($rawLabel);
        } catch (TableException $exception) {
            return $this->error(400, self::ERROR_INVALID, $exception->getMessage());
        }

        $seats = $this->normalizeSeats($request->get_param('seats'));
        if (null === $seats) {
            return $this->error(400, self::ERROR_INVALID, 'Seats must be a positive whole number.');
        }

        if ($this->repository->labelExists($label)) {
            return $this->error(
                409,
                self::ERROR_DUPLICATE,
                \sprintf('A table labelled "%s" already exists.', $label)
            );
        }

        $id  = $this->repository->create($label, $seats);
        $row = $this->repository->find($id);
        if (null === $row) {
            return $this->error(404, self::ERROR_NOT_FOUND, 'The created table could not be loaded.');
        }

        return new WP_REST_Response($this->present($row), 201);
    }

    /**
     * Move a table to a new state, validating against the state graph.
     */
    public function updateState(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id  = (int) $request->get_param('id');
        $row = $this->repository->find($id);
        if (null === $row) {
            return $this->notFound($id);
        }

        $target = $request->get_param('state');
        if (! \is_string($target)) {
            return $this->error(400, self::ERROR_INVALID, 'A target state is required.');
        }

        try {
            $state = $this->tables->move((string) $row['state'], $target);
        } catch (TableException $exception) {
            return $this->error(400, self::ERROR_INVALID, $exception->getMessage());
        }

        $this->repository->updateState($id, $state->value);

        return new WP_REST_Response($this->present($this->repository->find($id) ?? $row), 200);
    }

    /**
     * Archive (soft-delete) a table.
     */
    public function delete(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int) $request->get_param('id');
        if (null === $this->repository->find($id)) {
            return $this->notFound($id);
        }

        $this->repository->archive($id);

        return new WP_REST_Response(
            array(
                'id'       => $id,
                'archived' => true,
            ),
            200
        );
    }

    /**
     * Shape a repository row into the REST table object.
     *
     * @param array<string, mixed> $row Repository row.
     * @return array<string, mixed>
     */
    private function present(array $row): array
    {
        $state = TableState::tryFrom((string) ( $row['state'] ?? '' )) ?? TableState::Free;
        $label = (string) ( $row['label'] ?? '' );

        return array(
            'id'          => (int) ( $row['id'] ?? 0 ),
            'label'       => $label,
            'seats'       => (int) ( $row['seats'] ?? 0 ),
            'state'       => $state->value,
            'next_states' => \array_map(
                static fn (TableState $next): string => $next->value,
                $state->nextStates()
            ),
            'qr_url'      => $this->tables->menuUrl($this->menuBaseUrl(), $label),
        );
    }

    /**
     * Normalize a `seats` parameter.
     *
     * @param mixed $value Raw request value.
     * @return int|null Positive seat count, or null when invalid.
     */
    private function normalizeSeats(mixed $value): ?int
    {
        if (null === $value || '' === $value) {
            return self::DEFAULT_SEATS;
        }

        if (\is_int($value)) {
            $seats = $value;
        } elseif (\is_string($value) && \ctype_digit($value)) {
            $seats = (int) $value;
        } else {
            return null;
        }

        return $seats >= 1 ? $seats : null;
    }

    /**
     * Resolve the base menu URL for QR links.
     *
     * Defaults to `home_url('/menu/')`.
     *
     * @return string
     */
    private function menuBaseUrl(): string
    {
        $default = \function_exists('home_url') ? \home_url('/menu/') : '/menu/';

        /**
         * Filters the base menu URL encoded into Free table QR cards.
         *
         * The `?table=` label is appended by the plugin for display only; the
         * menu owner decides whether to consume it.
         *
         * @since 0.1.0
         *
         * @param string $url Base menu URL. Defaults to home_url('/menu/').
         *
         * @example
         * add_filter( 'smooth_qr_menu_url', fn() => home_url( '/order/' ) );
         */
        $filtered = apply_filters('smooth_qr_menu_url', $default);

        return \is_string($filtered) && '' !== $filtered ? $filtered : $default;
    }

    /**
     * Build a 404 error for a table id.
     */
    private function notFound(int $id): WP_Error
    {
        return $this->error(404, self::ERROR_NOT_FOUND, \sprintf('Table %d was not found.', $id));
    }

    /**
     * Build a WP_Error carrying an HTTP status.
     */
    private function error(int $status, string $code, string $message): WP_Error
    {
        return new WP_Error($code, $message, array( 'status' => $status ));
    }
}
