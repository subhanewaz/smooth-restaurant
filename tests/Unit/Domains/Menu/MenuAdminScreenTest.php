<?php

declare(strict_types=1);

namespace SmoothRestaurant\Tests\Unit\Domains\Menu;

use PHPUnit\Framework\TestCase;
use SmoothRestaurant\Contracts\MenuItemRepositoryInterface;
use SmoothRestaurant\Contracts\MenuRepositoryInterface;
use SmoothRestaurant\Contracts\ModifierRepositoryInterface;
use SmoothRestaurant\Domains\Menu\MenuAdminScreen;
use SmoothRestaurant\Domains\Menu\MenuService;

/**
 * Unit tests for the admin screen validation, listing, and cascades.
 */
final class MenuAdminScreenTest extends TestCase
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $menus = array();

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $items = array();

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $modifiers = array();

    private int $nextId = 1;

    protected function setUp(): void
    {
        parent::setUp();
        sr_test_reset_stubs();
        $this->menus     = array();
        $this->items     = array();
        $this->modifiers = array();
        $this->nextId    = 1;
    }

    protected function tearDown(): void
    {
        sr_test_reset_stubs();
        parent::tearDown();
    }

    private function screen(): MenuAdminScreen
    {
        $test = $this;

        $menus = new class ($test) implements MenuRepositoryInterface {
            /**
             * @param MenuAdminScreenTest $test Test harness.
             */
            public function __construct(private MenuAdminScreenTest $test)
            {
            }

            public function getTable(): string
            {
                return 'smooth_menus';
            }

            public function schema(): string
            {
                return '';
            }

            public function mapRows(array $rows): array
            {
                return $rows;
            }

            public function createTable(): void
            {
            }

            public function insert(array $data): int
            {
                return $this->test->insertMenu($data);
            }

            public function findById(int $id): ?array
            {
                return $this->test->findMenu($id);
            }

            public function findBySlug(string $slug): ?array
            {
                return $this->test->findMenuBySlug($slug);
            }

            public function paginate(int $page, int $perPage, string $search = '', string $status = 'publish'): array
            {
                return $this->test->paginateMenus($page, $perPage, $search, $status);
            }

            public function update(int $id, array $data): bool
            {
                return $this->test->updateMenu($id, $data);
            }

            public function delete(int $id): bool
            {
                return $this->test->deleteMenu($id);
            }

            public static function generateSlug(string $name): string
            {
                $slug = (string) \preg_replace('/[^a-z0-9]+/', '-', \strtolower($name));

                return '' !== \trim($slug, '-') ? \trim($slug, '-') : 'menu';
            }
        };

        $items = new class ($test) implements MenuItemRepositoryInterface {
            /**
             * @param MenuAdminScreenTest $test Test harness.
             */
            public function __construct(private MenuAdminScreenTest $test)
            {
            }

            public function getTable(): string
            {
                return 'smooth_menu_items';
            }

            public function schema(): string
            {
                return '';
            }

            public function mapRows(array $rows): array
            {
                return $rows;
            }

            public function createTable(): void
            {
            }

            public function insert(array $data): int
            {
                return $this->test->insertItem($data);
            }

            public function findById(int $id): ?array
            {
                return $this->test->findItem($id);
            }

            public function listByMenu(int $menuId, string $status = 'publish'): array
            {
                return $this->test->listItems($menuId, $status);
            }

            public function maxSortOrderForMenu(int $menuId): ?int
            {
                return $this->test->maxItemOrder($menuId);
            }

            public function update(int $id, array $data): bool
            {
                return $this->test->updateItem($id, $data);
            }

            public function delete(int $id): bool
            {
                return $this->test->deleteItem($id);
            }
        };

        $modifiers = new class ($test) implements ModifierRepositoryInterface {
            /**
             * @param MenuAdminScreenTest $test Test harness.
             */
            public function __construct(private MenuAdminScreenTest $test)
            {
            }

            public function getTable(): string
            {
                return 'smooth_modifiers';
            }

            public function schema(): string
            {
                return '';
            }

            public function mapRows(array $rows): array
            {
                return $rows;
            }

            public function createTable(): void
            {
            }

            public function insert(array $data): int
            {
                return $this->test->insertModifier($data);
            }

            public function findById(int $id): ?array
            {
                return $this->test->findModifier($id);
            }

            public function listByItem(int $itemId, string $status = 'publish'): array
            {
                return $this->test->listModifiers($itemId, $status);
            }

            public function maxSortOrderForItem(int $itemId): ?int
            {
                return $this->test->maxModifierOrder($itemId);
            }

            public function update(int $id, array $data): bool
            {
                return $this->test->updateModifier($id, $data);
            }

            public function delete(int $id): bool
            {
                return $this->test->deleteModifier($id);
            }
        };

        return new MenuAdminScreen($menus, $items, $modifiers);
    }

    /**
     * @param array<string, mixed> $data Column values.
     */
    public function insertMenu(array $data): int
    {
        $id               = $this->nextId++;
        $defaults         = array( 'id' => $id, 'status' => 'publish', 'sort_order' => 0 );
        $this->menus[$id] = \array_merge($defaults, $data, array( 'id' => $id ));

        return $id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findMenu(int $id): ?array
    {
        return $this->menus[$id] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findMenuBySlug(string $slug): ?array
    {
        foreach ($this->menus as $row) {
            if ((string) ($row['slug'] ?? '') === $slug) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function paginateMenus(int $page, int $perPage, string $search, string $status): array
    {
        $rows = array();
        foreach ($this->menus as $row) {
            if ((string) ($row['status'] ?? '') !== $status) {
                continue;
            }
            $haystack = (string) ($row['name'] ?? '') . (string) ($row['description'] ?? '');
            if ('' !== $search && false === \strpos($haystack, $search)) {
                continue;
            }
            $rows[] = $row;
        }
        $offset = ($page - 1) * $perPage;

        return \array_slice($rows, $offset, $perPage);
    }

    /**
     * @param array<string, mixed> $data Column values.
     */
    public function updateMenu(int $id, array $data): bool
    {
        if (! isset($this->menus[$id])) {
            return false;
        }
        $this->menus[$id] = \array_merge($this->menus[$id], $data);

        return true;
    }

    public function deleteMenu(int $id): bool
    {
        if (! isset($this->menus[$id])) {
            return false;
        }
        unset($this->menus[$id]);

        return true;
    }

    /**
     * @param array<string, mixed> $data Column values.
     */
    public function insertItem(array $data): int
    {
        $id               = $this->nextId++;
        $defaults         = array( 'id' => $id, 'status' => 'publish', 'sort_order' => 0 );
        $this->items[$id] = \array_merge($defaults, $data, array( 'id' => $id ));

        return $id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findItem(int $id): ?array
    {
        return $this->items[$id] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listItems(int $menuId, string $status): array
    {
        $rows = array();
        foreach ($this->items as $row) {
            if ((int) ($row['menu_id'] ?? 0) !== $menuId || (string) ($row['status'] ?? '') !== $status) {
                continue;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    public function maxItemOrder(int $menuId): ?int
    {
        $max = null;
        foreach ($this->items as $row) {
            if ((int) ($row['menu_id'] ?? 0) !== $menuId) {
                continue;
            }
            $order = (int) ($row['sort_order'] ?? 0);
            $max   = null === $max ? $order : \max($max, $order);
        }

        return $max;
    }

    /**
     * @param array<string, mixed> $data Column values.
     */
    public function updateItem(int $id, array $data): bool
    {
        if (! isset($this->items[$id])) {
            return false;
        }
        $this->items[$id] = \array_merge($this->items[$id], $data);

        return true;
    }

    public function deleteItem(int $id): bool
    {
        if (! isset($this->items[$id])) {
            return false;
        }
        unset($this->items[$id]);

        return true;
    }

    /**
     * @param array<string, mixed> $data Column values.
     */
    public function insertModifier(array $data): int
    {
        $id                   = $this->nextId++;
        $defaults             = array( 'id' => $id, 'status' => 'publish', 'sort_order' => 0 );
        $this->modifiers[$id] = \array_merge($defaults, $data, array( 'id' => $id ));

        return $id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findModifier(int $id): ?array
    {
        return $this->modifiers[$id] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listModifiers(int $itemId, string $status): array
    {
        $rows = array();
        foreach ($this->modifiers as $row) {
            $owner   = (int) ($row['item_id'] ?? 0) === $itemId;
            $matches = (string) ($row['status'] ?? '') === $status;
            if (! $owner || ! $matches) {
                continue;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    public function maxModifierOrder(int $itemId): ?int
    {
        $max = null;
        foreach ($this->modifiers as $row) {
            if ((int) ($row['item_id'] ?? 0) !== $itemId) {
                continue;
            }
            $order = (int) ($row['sort_order'] ?? 0);
            $max   = null === $max ? $order : \max($max, $order);
        }

        return $max;
    }

    /**
     * @param array<string, mixed> $data Column values.
     */
    public function updateModifier(int $id, array $data): bool
    {
        if (! isset($this->modifiers[$id])) {
            return false;
        }
        $this->modifiers[$id] = \array_merge($this->modifiers[$id], $data);

        return true;
    }

    public function deleteModifier(int $id): bool
    {
        if (! isset($this->modifiers[$id])) {
            return false;
        }
        unset($this->modifiers[$id]);

        return true;
    }

    public function test_clamps_page_and_per_page(): void
    {
        $this->assertSame(1, MenuAdminScreen::clampPage(0));
        $this->assertSame(1, MenuAdminScreen::clampPage('abc'));
        $this->assertSame(100, MenuAdminScreen::clampPerPage(9999));
        $this->assertSame(1, MenuAdminScreen::clampPerPage(-5));
        $this->assertSame(20, MenuAdminScreen::clampPerPage(null));
    }

    public function test_sanitizes_search_and_escapes_output(): void
    {
        $this->assertSame('lunch', MenuAdminScreen::sanitizeSearch('lunch'));
        $this->assertSame('100%', MenuAdminScreen::sanitizeSearch('100%'));
        $this->assertSame('&lt;script&gt;', MenuAdminScreen::esc('<script>'));
    }

    public function test_menu_validation_requires_name_and_allowlisted_status(): void
    {
        $screen = $this->screen();

        $missing = $screen->validateMenu(array( 'name' => '' ), true);
        $this->assertFalse($missing['valid']);
        $this->assertSame('smooth_menu_missing_name', $missing['error']);

        $badStatus = $screen->validateMenu(array( 'name' => 'Brunch', 'status' => 'archived' ), true);
        $this->assertFalse($badStatus['valid']);

        $created = $screen->validateMenu(array( 'name' => 'Brunch', 'description' => 'Late' ), true);
        $this->assertTrue($created['valid']);
        $this->assertSame('publish', $created['data']['status']);
        $this->assertSame('brunch', $created['data']['slug']);
    }

    public function test_menu_slug_collision_suffixes_and_self_ignore(): void
    {
        $screen = $this->screen();

        $first = $screen->validateMenu(array( 'name' => 'Brunch' ), true);
        $this->assertTrue($first['valid']);
        $id1 = $this->insertMenu($first['data']);

        $second = $screen->validateMenu(array( 'name' => 'Brunch' ), true);
        $this->assertTrue($second['valid']);
        $this->assertSame('brunch-2', $second['data']['slug']);

        $same = $screen->validateMenu(array( 'name' => 'Brunch', 'slug' => 'brunch' ), false, $id1);
        $this->assertTrue($same['valid']);
        $this->assertSame('brunch', $same['data']['slug']);
    }

    public function test_shared_slug_helper_suffixes_and_time_fallback(): void
    {
        $slug = MenuService::uniqueSlug(
            static function (string $candidate): ?array {
                return 'brunch' === $candidate ? array( 'id' => 5, 'slug' => 'brunch' ) : null;
            },
            'brunch',
            0
        );
        $this->assertSame('brunch-2', $slug);

        $same = MenuService::uniqueSlug(
            static function (string $candidate): ?array {
                if ('brunch' !== $candidate) {
                    return null;
                }

                return array( 'id' => 7, 'slug' => $candidate );
            },
            'brunch',
            7
        );
        $this->assertSame('brunch', $same);
    }

    public function test_unslash_before_sanitize(): void
    {
        $screen  = $this->screen();
        $checked = $screen->validateMenu(array( 'name' => 'O\\\'Brien' ), true);
        $this->assertTrue($checked['valid']);
        $this->assertSame("O'Brien", $checked['data']['name']);
    }

    public function test_item_validation_and_image_check(): void
    {
        $screen = $this->screen();

        $badPrice = $screen->validateItem(array( 'name' => 'Soup', 'price_cents' => '-5' ), true);
        $this->assertFalse($badPrice['valid']);
        $this->assertSame('smooth_menu_item_invalid_price', $badPrice['error']);

        $badImage = $screen->validateItem(array( 'name' => 'Soup', 'image_id' => 999 ), true);
        $this->assertFalse($badImage['valid']);
        $this->assertSame('smooth_menu_item_invalid_image', $badImage['error']);

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- test stub globals defined in WpStubs.
        $GLOBALS['__sr_test_flags']['test_posts'] = array(
            3 => array( 'post_type' => 'attachment', 'mime' => 'image/jpeg' ),
        );
        $good = $screen->validateItem(
            array( 'name' => 'Soup', 'price_cents' => 950, 'image_id' => 3 ),
            true
        );
        $this->assertTrue($good['valid']);
        $this->assertSame(950, $good['data']['price_cents']);
    }

    public function test_modifier_validation_rejects_bad_price_and_status(): void
    {
        $screen = $this->screen();

        $bad = $screen->validateModifier(array( 'name' => 'Extra', 'price_cents' => 'abc' ), true);
        $this->assertFalse($bad['valid']);

        $badStatus = $screen->validateModifier(array( 'name' => 'Extra', 'status' => 'nope' ), true);
        $this->assertFalse($badStatus['valid']);

        $good = $screen->validateModifier(array( 'name' => 'Extra', 'price_cents' => 150 ), true);
        $this->assertTrue($good['valid']);
        $this->assertSame('publish', $good['data']['status']);
    }

    public function test_list_merges_publish_and_draft(): void
    {
        $this->insertMenu(array( 'name' => 'Lunch', 'slug' => 'lunch', 'status' => 'publish' ));
        $this->insertMenu(array( 'name' => 'Secret', 'slug' => 'secret', 'status' => 'draft' ));

        $result = $this->screen()->listMenus(1, 20, '');
        $names  = \array_column($result['data'], 'name');

        $this->assertContains('Lunch', $names);
        $this->assertContains('Secret', $names);
        $this->assertSame(1, $result['meta']['page']);
        $this->assertSame(20, $result['meta']['per_page']);
    }

    public function test_ownership_and_forged_parent_checks(): void
    {
        $menuId  = $this->insertMenu(array( 'name' => 'A', 'slug' => 'a', 'status' => 'publish' ));
        $otherId = $this->insertMenu(array( 'name' => 'B', 'slug' => 'b', 'status' => 'publish' ));
        $itemId  = $this->insertItem(array( 'menu_id' => $menuId, 'name' => 'Soup', 'status' => 'publish' ));

        $screen = $this->screen();
        $this->assertTrue($screen->ownsItem($itemId, $menuId));
        $this->assertFalse($screen->ownsItem($itemId, $otherId));
    }

    public function test_delete_cascades_leave_no_orphans(): void
    {
        $menuId     = $this->insertMenu(array( 'name' => 'A', 'slug' => 'a', 'status' => 'publish' ));
        $itemId     = $this->insertItem(array( 'menu_id' => $menuId, 'name' => 'Soup', 'status' => 'draft' ));
        $modifierId = $this->insertModifier(array( 'item_id' => $itemId, 'name' => 'Large', 'status' => 'draft' ));

        $screen = $this->screen();
        $this->assertTrue($screen->deleteMenuCascade($menuId));
        $this->assertNull($this->findMenu($menuId));
        $this->assertNull($this->findItem($itemId));
        $this->assertNull($this->findModifier($modifierId));
    }

    public function test_can_manage_and_verify_nonce_defaults_allow_in_unit_context(): void
    {
        sr_test_grant_caps(array( 'smooth_manage_menus' ));
        $screen = $this->screen();
        $this->assertTrue($screen->canManage('smooth_manage_menus'));
        $this->assertTrue($screen->verifyNonce(MenuAdminScreen::NONCE_MENU_SAVE));
    }

    public function test_cap_denial_blocks_management(): void
    {
        $cap = 'smooth_manage_menus';
        sr_test_grant_caps(array());
        $this->assertFalse(\current_user_can($cap));

        sr_test_grant_caps(array( $cap ));
        $this->assertTrue(\current_user_can($cap));
    }

    public function test_nonce_reject_path(): void
    {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- test stub globals.
        $GLOBALS['__sr_test_flags']['nonce_pass'] = false;
        $this->assertFalse($this->screen()->verifyNonce(MenuAdminScreen::NONCE_MENU_SAVE));
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- test stub globals.
        $GLOBALS['__sr_test_flags']['nonce_pass'] = true;
    }
}
