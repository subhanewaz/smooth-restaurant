<?php

/**
 * Menu REST routes.
 *
 * @package SmoothRestaurant
 */

declare(strict_types=1);

namespace SmoothRestaurant\Domains\Menu;

use SmoothRestaurant\Contracts\MenuItemRepositoryInterface;
use SmoothRestaurant\Contracts\MenuRepositoryInterface;
use SmoothRestaurant\Contracts\ModifierRepositoryInterface;
use SmoothRestaurant\Domains\Shared\DomainEvents;
use SmoothRestaurant\Database\Repositories\MenuRepository;
use SmoothRestaurant\Providers\MenuProvider;
use SmoothRestaurant\Providers\RestProvider;

/**
 * Class MenuRoutes
 *
 * Registers the public menu reads (paginated list + single tree) and the
 * capability-gated management writes. Constructed from container-bound
 * repositories — raw database access stays inside src/Database/. Callbacks accept the
 * WP_REST_Request in production and plain param arrays in unit tests, and
 * return plain arrays (WordPress serializes them to 200 JSON); errors carry
 * a machine-readable code plus message, with the HTTP status applied
 * through WP_REST_Response whenever the class is available.
 */
final class MenuRoutes
{
    use MenuRestSupport;

    /**
     * Constructor.
     *
     * @param MenuRepositoryInterface     $menus     Menus table repository.
     * @param MenuItemRepositoryInterface $items     Menu items table repository.
     * @param ModifierRepositoryInterface $modifiers Modifiers table repository.
     */
    public function __construct(
        private MenuRepositoryInterface $menus,
        private MenuItemRepositoryInterface $items,
        private ModifierRepositoryInterface $modifiers
    ) {
    }

    /**
     * Register the menu routes. No-op without WordPress.
     *
     * @return void
     */
    public function register(): void
    {
        if (! function_exists('register_rest_route')) {
            return;
        }

        $manage = RestProvider::capability(MenuProvider::MANAGE_CAP);

        // Endpoint lists mix numeric entries with the 'schema' key — the
        // shape register_rest_route() documents; the WP stub types $args as
        // array<string, mixed>, hence the local annotation.
        /** @var array<string, mixed> $collectionArgs */
        $collectionArgs = [
            [
                'methods' => 'GET',
                'callback' => [$this, 'listMenus'],
                'permission_callback' => '__return_true',
                'args' => $this->listArgs(),
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'createMenu'],
                'permission_callback' => $manage,
                'args' => $this->writeArgs(),
            ],
            'schema' => [$this, 'collectionSchema'],
        ];
        register_rest_route(RestProvider::NAMESPACE, '/menus', $collectionArgs);

        /** @var array<string, mixed> $singleArgs */
        $singleArgs = [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getMenu'],
                'permission_callback' => '__return_true',
                'args' => $this->idArgs(),
            ],
            [
                'methods' => ['PUT', 'PATCH'],
                'callback' => [$this, 'updateMenu'],
                'permission_callback' => $manage,
                'args' => $this->writeArgs(),
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'deleteMenu'],
                'permission_callback' => $manage,
                'args' => $this->idArgs(),
            ],
            'schema' => [$this, 'menuSchema'],
        ];
        register_rest_route(RestProvider::NAMESPACE, '/menus/(?P<id>\d+)', $singleArgs);
    }

    /**
     * List published menus, paginated.
     *
     * Emits a Cache-Control header through the response object when
     * available (public, cacheable collection).
     *
     * @param mixed $request WP_REST_Request in production, param array in unit tests.
     * @return mixed Response array, or WP_REST_Response when available.
     */
    public function listMenus(mixed $request): mixed
    {
        $params = self::params($request);
        $page = isset($params['page']) ? (int) $params['page'] : 1;
        $perPage = isset($params['per_page']) ? (int) $params['per_page'] : 10;
        $search = isset($params['search']) ? (string) $params['search'] : '';

        $rows = $this->menus->paginate($page, $perPage, $search, 'publish');

        $response = [
            'data' => $rows,
            'meta' => [
                'page' => max(1, $page),
                'per_page' => min(100, max(1, $perPage)),
            ],
        ];

        return $this->cached($response);
    }

    /**
     * Get one published menu as a tree (menu + items + modifiers).
     *
     * @param mixed $request WP_REST_Request in production, param array in unit tests.
     * @return mixed Response array, or WP_REST_Response when available.
     */
    public function getMenu(mixed $request): mixed
    {
        $params = self::params($request);
        $id = isset($params['id']) ? (int) $params['id'] : 0;

        $menu = 0 !== $id ? $this->menus->findById($id) : null;
        if (null === $menu || 'publish' !== ($menu['status'] ?? null)) {
            return $this->error('smooth_menu_not_found', 'Menu not found.', 404);
        }

        return ['data' => $this->tree((int) $menu['id'], $menu)];
    }

    /**
     * Create a menu row (capability-gated).
     *
     * @param mixed $request WP_REST_Request in production, param array in unit tests.
     * @return mixed Response array, or WP_REST_Response when available.
     */
    public function createMenu(mixed $request): mixed
    {
        $params = self::params($request);
        $name = trim((string) ($params['name'] ?? ''));
        if ('' === $name) {
            return $this->error('smooth_menu_missing_name', 'The name field is required.', 400);
        }

        $id = $this->menus->insert([
            'name' => $name,
            'slug' => $this->uniqueSlug(MenuRepository::generateSlug($name)),
            'description' => (string) ($params['description'] ?? ''),
            'status' => $this->status($params),
            'sort_order' => isset($params['sort_order']) ? (int) $params['sort_order'] : 0,
        ]);

        $menu = $this->menus->findById($id);
        $menu = \is_array($menu) ? $menu : ['id' => $id, 'name' => $name];
        DomainEvents::dispatch(DomainEvents::MENU_SAVED, ['id' => $id, 'menu' => $menu]);

        return $this->respond(['data' => $menu], 201);
    }

    /**
     * Update a menu row (capability-gated).
     *
     * @param mixed $request WP_REST_Request in production, param array in unit tests.
     * @return mixed Response array, or WP_REST_Response when available.
     */
    public function updateMenu(mixed $request): mixed
    {
        $params = self::params($request);
        $id = isset($params['id']) ? (int) $params['id'] : 0;

        $menu = 0 !== $id ? $this->menus->findById($id) : null;
        if (null === $menu) {
            return $this->error('smooth_menu_not_found', 'Menu not found.', 404);
        }

        $data = [];
        if (isset($params['name'])) {
            $name = trim((string) $params['name']);
            if ('' === $name) {
                return $this->error('smooth_menu_missing_name', 'The name field must not be empty.', 400);
            }
            $data['name'] = $name;
            $data['slug'] = $this->uniqueSlug(MenuRepository::generateSlug($name), (int) $menu['id']);
        }
        if (isset($params['description'])) {
            $data['description'] = (string) $params['description'];
        }
        if (isset($params['status'])) {
            $data['status'] = $this->status($params);
        }
        if (isset($params['sort_order'])) {
            $data['sort_order'] = (int) $params['sort_order'];
        }

        if ([] !== $data) {
            $this->menus->update((int) $menu['id'], $data);
        }

        $updated = $this->menus->findById((int) $menu['id']);
        $updated = \is_array($updated) ? $updated : $menu;
        DomainEvents::dispatch(DomainEvents::MENU_SAVED, ['id' => (int) $menu['id'], 'menu' => $updated]);

        return ['data' => $updated];
    }

    /**
     * Delete a menu row with its items and modifiers (capability-gated).
     *
     * @param mixed $request WP_REST_Request in production, param array in unit tests.
     * @return mixed Response array, or WP_REST_Response when available.
     */
    public function deleteMenu(mixed $request): mixed
    {
        $params = self::params($request);
        $id = isset($params['id']) ? (int) $params['id'] : 0;

        $menu = 0 !== $id ? $this->menus->findById($id) : null;
        if (null === $menu) {
            return $this->error('smooth_menu_not_found', 'Menu not found.', 404);
        }

        foreach (['publish', 'draft'] as $status) {
            foreach ($this->items->listByMenu((int) $menu['id'], $status) as $item) {
                $itemId = (int) ($item['id'] ?? 0);
                foreach (['publish', 'draft'] as $modifierStatus) {
                    foreach ($this->modifiers->listByItem($itemId, $modifierStatus) as $modifier) {
                        $this->modifiers->delete((int) ($modifier['id'] ?? 0));
                    }
                }
                $this->items->delete($itemId);
            }
        }
        $this->menus->delete((int) $menu['id']);

        return ['data' => ['deleted' => true, 'id' => (int) $menu['id']]];
    }

    /**
     * JSON-Schema for a single menu object.
     *
     * @return array<string, mixed>
     */
    public function menuSchema(): array
    {
        return [
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'title' => 'menu',
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'Menu row id.'],
                'name' => ['type' => 'string', 'description' => 'Display name.'],
                'slug' => ['type' => 'string', 'description' => 'Unique URL-safe slug.'],
                'description' => ['type' => 'string', 'description' => 'Long description.'],
                'status' => ['type' => 'string', 'enum' => ['publish', 'draft'], 'description' => 'Row status.'],
                'sort_order' => ['type' => 'integer', 'description' => 'Display order.'],
                'created_at' => ['type' => 'string', 'format' => 'date-time', 'description' => 'Creation time.'],
                'updated_at' => ['type' => 'string', 'format' => 'date-time', 'description' => 'Last update time.'],
            ],
            'required' => ['id', 'name', 'slug', 'status'],
        ];
    }

    /**
     * JSON-Schema for the menu collection response.
     *
     * @return array<string, mixed>
     */
    public function collectionSchema(): array
    {
        return [
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'title' => 'menu-collection',
            'type' => 'object',
            'properties' => [
                'data' => ['type' => 'array', 'items' => $this->menuSchema()],
                'meta' => [
                    'type' => 'object',
                    'properties' => [
                        'page' => ['type' => 'integer'],
                        'per_page' => ['type' => 'integer'],
                    ],
                    'required' => ['page', 'per_page'],
                ],
            ],
            'required' => ['data', 'meta'],
        ];
    }

    /**
     * Request args for the collection GET.
     *
     * @return array<string, mixed>
     */
    private function listArgs(): array
    {
        return [
            'page' => ['type' => 'integer', 'default' => 1, 'minimum' => 1, 'description' => 'Page number.'],
            'per_page' => [
                'type' => 'integer',
                'default' => 10,
                'minimum' => 1,
                'maximum' => 100,
                'description' => 'Rows per page.',
            ],
            'search' => [
                'type' => 'string',
                'description' => 'Substring matched against name and description.',
            ],
        ];
    }

    /**
     * Request args for routes carrying an id placeholder.
     *
     * @return array<string, mixed>
     */
    private function idArgs(): array
    {
        return [
            'id' => ['type' => 'integer', 'required' => true, 'description' => 'Menu row id.'],
        ];
    }

    /**
     * Request args for the management writes.
     *
     * @return array<string, mixed>
     */
    private function writeArgs(): array
    {
        return [
            'name' => ['type' => 'string', 'description' => 'Display name (required on create).'],
            'description' => ['type' => 'string', 'description' => 'Long description.'],
            'status' => ['type' => 'string', 'enum' => ['publish', 'draft'], 'description' => 'Row status.'],
            'sort_order' => ['type' => 'integer', 'description' => 'Display order.'],
        ];
    }

    /**
     * Assemble the publish-only tree for a menu row.
     *
     * Draft items and modifiers stay invisible on the public read; delete
     * cascades still cover both statuses.
     *
     * @param array<string, mixed> $menu Menu row.
     * @return array{menu: array<string, mixed>, items: list<array{item: array<string, mixed>,
     *     modifiers: list<array<string, mixed>>}>} Menu tree.
     */
    private function tree(int $menuId, array $menu): array
    {
        $items = $this->items->listByMenu($menuId);
        $modifiers = [];
        foreach ($items as $item) {
            foreach ($this->modifiers->listByItem((int) ($item['id'] ?? 0)) as $modifier) {
                $modifiers[] = $modifier;
            }
        }

        return MenuService::assemble($menu, $items, $modifiers);
    }

    /**
     * Find a free slug, suffixing (-2, -3, …) past collisions.
     *
     * Delegates to the shared MenuService::uniqueSlug() helper so REST and
     * wp-admin share one source of truth.
     *
     * @param int $ignoreId Row id allowed to keep its own slug (updates).
     */
    private function uniqueSlug(string $base, int $ignoreId = 0): string
    {
        return MenuService::uniqueSlug(
            function (string $slug): ?array {
                $row = $this->menus->findBySlug($slug);

                return \is_array($row) ? $row : null;
            },
            $base,
            $ignoreId
        );
    }

    /**
     * Collection payload with a Cache-Control header when WordPress is loaded.
     *
     * The header is a production edge (needs the response object); unit
     * context returns the payload array. Bruno CI asserts the live header.
     *
     * @param array<string, mixed> $data Payload.
     * @return mixed
     */
    private function cached(array $data): mixed
    {
        if (\class_exists('WP_REST_Response')) {
            $response = new \WP_REST_Response($data, 200);
            $response->header('Cache-Control', 'public, max-age=60');

            return $response;
        }

        return $data;
    }
}
