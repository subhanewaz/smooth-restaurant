<?php

/**
 * Admin service provider.
 *
 * @package SmoothRestaurant
 */

declare(strict_types=1);

namespace SmoothRestaurant\Providers;

use SmoothRestaurant\Contracts\MenuItemRepositoryInterface;
use SmoothRestaurant\Contracts\MenuRepositoryInterface;
use SmoothRestaurant\Contracts\ModifierRepositoryInterface;
use SmoothRestaurant\Core\Container;
use SmoothRestaurant\Core\ServiceProvider;
use SmoothRestaurant\Domains\Menu\MenuAdminScreen;

/**
 * Class AdminProvider
 *
 * Thin admin shell: register() binds the screen class only; boot() hooks
 * admin_menu plus all admin_post handlers directly after the admin bail.
 */
final class AdminProvider extends ServiceProvider
{
    /**
     * Request contexts this provider participates in.
     *
     * Mirrors boot(): admin screens only.
     *
     * @return list<string>
     */
    public static function contexts(): array
    {
        return array( 'admin' );
    }

    /**
     * Register services with the container.
     *
     * Bind-only: no hooks, no database access, no translation calls.
     *
     * @param Container $container The DI container.
     * @return void
     */
    public function register(Container $container): void
    {
        $container->singleton(MenuAdminScreen::class);
    }

    /**
     * Boot the provider after all providers are registered.
     *
     * @param Container $container The DI container.
     * @return void
     */
    public function boot(Container $container): void
    {
        if (! $this->isAdmin()) {
            return;
        }

        add_action('admin_menu', array( $this, 'registerMenu' ));
        add_action('admin_post_smooth_restaurant_save_menu', array( $this, 'handleSaveMenu' ));
        add_action('admin_post_smooth_restaurant_delete_menu', array( $this, 'handleDeleteMenu' ));
        add_action('admin_post_smooth_restaurant_save_item', array( $this, 'handleSaveItem' ));
        add_action('admin_post_smooth_restaurant_delete_item', array( $this, 'handleDeleteItem' ));
        add_action('admin_post_smooth_restaurant_save_modifier', array( $this, 'handleSaveModifier' ));
        add_action('admin_post_smooth_restaurant_delete_modifier', array( $this, 'handleDeleteModifier' ));
        $this->markBooted();
    }

    /**
     * Register admin menu screens.
     *
     * @return void
     */
    public function registerMenu(): void
    {
        if (! \function_exists('add_menu_page') || ! \function_exists('add_submenu_page')) {
            return;
        }
        if (! \function_exists('current_user_can') || ! \current_user_can(MenuProvider::MANAGE_CAP)) {
            return;
        }

        \add_menu_page(
            'Smooth',
            'Smooth',
            MenuProvider::MANAGE_CAP,
            MenuAdminScreen::MENU_SLUG,
            array( $this, 'renderMenusPage' )
        );
        \add_submenu_page(
            MenuAdminScreen::MENU_SLUG,
            'Menus',
            'Menus',
            MenuProvider::MANAGE_CAP,
            MenuAdminScreen::PAGE_SLUG,
            array( $this, 'renderMenusPage' )
        );
    }

    /**
     * Render the Menus screen.
     *
     * @return void
     */
    public function renderMenusPage(): void
    {
        $screen = $this->screen();
        if (null === $screen) {
            return;
        }
        if (! $screen->canManage(MenuProvider::MANAGE_CAP)) {
            return;
        }

        $page   = MenuAdminScreen::clampPage($this->queryRaw('paged', 1));
        $search = MenuAdminScreen::sanitizeSearch($this->queryRaw('s', ''));
        $result = $screen->listMenus($page, MenuAdminScreen::PER_PAGE_DEFAULT, $search);

        echo '<div class="wrap"><h1>' . MenuAdminScreen::esc('Smooth Menus') . '</h1>';
        echo '<p>' . MenuAdminScreen::esc('Drafts are hidden from public reads; publish to verify via REST.') . '</p>';
        echo '<form method="get"><input type="hidden" name="page" value="'
            . MenuAdminScreen::esc(MenuAdminScreen::PAGE_SLUG) . '" />';
        echo '<input type="search" name="s" value="'
            . MenuAdminScreen::esc((string) ($result['meta']['search'] ?? '')) . '" />';
        echo '</form><table class="widefat"><thead><tr><th>Name</th><th>Slug</th><th>Status</th></tr></thead><tbody>';
        foreach ($result['data'] as $row) {
            echo '<tr><td>' . MenuAdminScreen::esc((string) ($row['name'] ?? '')) . '</td>';
            echo '<td>' . MenuAdminScreen::esc((string) ($row['slug'] ?? '')) . '</td>';
            echo '<td>' . MenuAdminScreen::esc((string) ($row['status'] ?? '')) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    /**
     * Handle menu create/update.
     *
     * @return void
     */
    public function handleSaveMenu(): void
    {
        $screen = $this->screen();
        if (null === $screen || ! $screen->canManage(MenuProvider::MANAGE_CAP)) {
            return;
        }
        if (! $screen->verifyNonce(MenuAdminScreen::NONCE_MENU_SAVE)) {
            return;
        }

        $input    = $this->postFields(array( 'name', 'slug', 'description', 'status' ));
        $menuId   = $this->postId('menu_id');
        $isCreate = 0 === $menuId;
        $checked  = $screen->validateMenu($input, $isCreate, $menuId);
        if (! $checked['valid']) {
            return;
        }

        $menus = $this->menus();
        if (null === $menus) {
            return;
        }
        if ($isCreate) {
            $menus->insert($checked['data']);
        } else {
            $existing = $menus->findById($menuId);
            if (null === $existing) {
                return;
            }
            $menus->update($menuId, $checked['data']);
        }

        $this->redirect(MenuAdminScreen::PAGE_SLUG);
    }

    /**
     * Handle menu delete with id-bound nonce and cascade.
     *
     * @return void
     */
    public function handleDeleteMenu(): void
    {
        $screen = $this->screen();
        if (null === $screen || ! $screen->canManage(MenuProvider::MANAGE_CAP)) {
            return;
        }
        $menuId = $this->postId('menu_id');
        if ($menuId <= 0) {
            return;
        }
        if (! $screen->verifyNonce(MenuAdminScreen::NONCE_MENU_DELETE . $menuId)) {
            return;
        }

        $screen->deleteMenuCascade($menuId);
        $this->redirect(MenuAdminScreen::PAGE_SLUG);
    }

    /**
     * Handle item create/update under a verified parent menu.
     *
     * @return void
     */
    public function handleSaveItem(): void
    {
        $screen = $this->screen();
        if (null === $screen || ! $screen->canManage(MenuProvider::MANAGE_CAP)) {
            return;
        }
        if (! $screen->verifyNonce(MenuAdminScreen::NONCE_ITEM_SAVE)) {
            return;
        }

        $menuId = $this->postId('menu_id');
        $menus  = $this->menus();
        $items  = $this->items();
        if (null === $menus || null === $items) {
            return;
        }
        if (null === $menus->findById($menuId)) {
            return;
        }

        $itemId   = $this->postId('item_id');
        $isCreate = 0 === $itemId;
        if (! $isCreate && ! $screen->ownsItem($itemId, $menuId)) {
            return;
        }

        $input   = $this->postFields(array( 'name', 'description', 'price_cents', 'image_id', 'status' ));
        $checked = $screen->validateItem($input, $isCreate);
        if (! $checked['valid']) {
            return;
        }

        if ($isCreate) {
            $max                    = $items->maxSortOrderForMenu($menuId);
            $checked['data']['menu_id']    = $menuId;
            $checked['data']['sort_order'] = null === $max ? 0 : $max + 1;
            $items->insert($checked['data']);
        } else {
            unset($checked['data']['menu_id']);
            $items->update($itemId, $checked['data']);
        }

        $this->redirect(MenuAdminScreen::PAGE_SLUG);
    }

    /**
     * Handle item delete with cascade.
     *
     * @return void
     */
    public function handleDeleteItem(): void
    {
        $screen = $this->screen();
        if (null === $screen || ! $screen->canManage(MenuProvider::MANAGE_CAP)) {
            return;
        }
        $itemId = $this->postId('item_id');
        $menuId = $this->postId('menu_id');
        if ($itemId <= 0 || ! $screen->ownsItem($itemId, $menuId)) {
            return;
        }
        if (! $screen->verifyNonce(MenuAdminScreen::NONCE_ITEM_DELETE . $itemId)) {
            return;
        }

        $screen->deleteItemCascade($itemId);
        $this->redirect(MenuAdminScreen::PAGE_SLUG);
    }

    /**
     * Handle modifier create/update under a verified parent item.
     *
     * @return void
     */
    public function handleSaveModifier(): void
    {
        $screen = $this->screen();
        if (null === $screen || ! $screen->canManage(MenuProvider::MANAGE_CAP)) {
            return;
        }
        if (! $screen->verifyNonce(MenuAdminScreen::NONCE_MODIFIER_SAVE)) {
            return;
        }

        $itemId    = $this->postId('item_id');
        $items     = $this->items();
        $modifiers = $this->modifiers();
        if (null === $items || null === $modifiers) {
            return;
        }
        if (null === $items->findById($itemId)) {
            return;
        }

        $modifierId = $this->postId('modifier_id');
        $isCreate   = 0 === $modifierId;
        if (! $isCreate && ! $screen->ownsModifier($modifierId, $itemId)) {
            return;
        }

        $input   = $this->postFields(array( 'name', 'price_cents', 'status' ));
        $checked = $screen->validateModifier($input, $isCreate);
        if (! $checked['valid']) {
            return;
        }

        if ($isCreate) {
            $max                    = $modifiers->maxSortOrderForItem($itemId);
            $checked['data']['item_id']    = $itemId;
            $checked['data']['sort_order'] = null === $max ? 0 : $max + 1;
            $modifiers->insert($checked['data']);
        } else {
            unset($checked['data']['item_id']);
            $modifiers->update($modifierId, $checked['data']);
        }

        $this->redirect(MenuAdminScreen::PAGE_SLUG);
    }

    /**
     * Handle modifier delete.
     *
     * @return void
     */
    public function handleDeleteModifier(): void
    {
        $screen = $this->screen();
        if (null === $screen || ! $screen->canManage(MenuProvider::MANAGE_CAP)) {
            return;
        }
        $modifierId = $this->postId('modifier_id');
        $itemId     = $this->postId('item_id');
        if ($modifierId <= 0 || ! $screen->ownsModifier($modifierId, $itemId)) {
            return;
        }
        if (! $screen->verifyNonce(MenuAdminScreen::NONCE_MODIFIER_DELETE . $modifierId)) {
            return;
        }

        $modifiers = $this->modifiers();
        if (null === $modifiers) {
            return;
        }
        $modifiers->delete($modifierId);
        $this->redirect(MenuAdminScreen::PAGE_SLUG);
    }

    /**
     * Resolve the screen from the container.
     *
     * @return MenuAdminScreen|null
     */
    private function screen(): ?MenuAdminScreen
    {
        try {
            $screen = $this->container->make(MenuAdminScreen::class);
        } catch (\Throwable) {
            return null;
        }

        return $screen instanceof MenuAdminScreen ? $screen : null;
    }

    /**
     * Resolve menus repository.
     *
     * @return MenuRepositoryInterface|null
     */
    private function menus(): ?MenuRepositoryInterface
    {
        try {
            $repos = $this->container->make(MenuRepositoryInterface::class);
        } catch (\Throwable) {
            return null;
        }

        return $repos instanceof MenuRepositoryInterface ? $repos : null;
    }

    /**
     * Resolve items repository.
     *
     * @return MenuItemRepositoryInterface|null
     */
    private function items(): ?MenuItemRepositoryInterface
    {
        try {
            $repos = $this->container->make(MenuItemRepositoryInterface::class);
        } catch (\Throwable) {
            return null;
        }

        return $repos instanceof MenuItemRepositoryInterface ? $repos : null;
    }

    /**
     * Resolve modifiers repository.
     *
     * @return ModifierRepositoryInterface|null
     */
    private function modifiers(): ?ModifierRepositoryInterface
    {
        try {
            $repos = $this->container->make(ModifierRepositoryInterface::class);
        } catch (\Throwable) {
            return null;
        }

        return $repos instanceof ModifierRepositoryInterface ? $repos : null;
    }

    /**
     * Read a raw query value (unslashed, sanitized downstream).
     *
     * Read-only list display only; no state change, no nonce required.
     *
     * @param string $key Field key.
     * @param mixed  $default Default when missing.
     * @return mixed
     */
    private function queryRaw(string $key, mixed $default): mixed
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display.
        if (! isset($_GET[ $key ])) {
            return $default;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read-only display, sanitized in clamp/sanitize helpers.
        return \wp_unslash($_GET[ $key ]);
    }

    /**
     * Read an integer id from POST (unslashed, cast to int).
     *
     * Callers verify capability + nonce via verifyNonce() before reading;
     * validation and ownership checks happen in MenuAdminScreen.
     *
     * @param string $key Field key.
     * @return int
     */
    private function postId(string $key): int
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers verify via verifyNonce() wrapping check_admin_referer.
        if (! isset($_POST[ $key ])) {
            return 0;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- unslashed then cast to int; callers verify via verifyNonce().
        $raw = \wp_unslash($_POST[ $key ]);

        return (int) $raw;
    }

    /**
     * Collect unslashed POST fields.
     *
     * @param list<string> $keys Field keys.
     * @return array<string, mixed>
     */
    private function postFields(array $keys): array
    {
        $input = array();
        foreach ($keys as $key) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers verify via verifyNonce() wrapping check_admin_referer.
            if (! isset($_POST[ $key ])) {
                continue;
            }
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- unslashed here, sanitized in MenuAdminScreen validators.
            $value = \wp_unslash($_POST[ $key ]);
            $input[ $key ] = $value;
        }

        return $input;
    }

    /**
     * Hard-coded safe redirect (no user-supplied target).
     *
     * @param string $slug Admin page slug.
     * @return void
     */
    private function redirect(string $slug): void
    {
        if (! \function_exists('wp_safe_redirect') || ! \function_exists('admin_url')) {
            return;
        }

        \wp_safe_redirect(\admin_url('admin.php?page=' . $slug));
    }
}
