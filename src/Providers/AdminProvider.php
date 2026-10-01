<?php

/**
 * Admin service provider.
 *
 * @package SmoothRestaurant
 */

declare(strict_types=1);

namespace SmoothRestaurant\Providers;

use SmoothRestaurant\Core\Container;
use SmoothRestaurant\Core\ServiceProvider;

/**
 * Class AdminProvider
 *
 * Hooks admin screens in boot() on wp-admin requests only. Registers the
 * Smooth dashboard page with a Tables & QR submenu and renders the mount
 * point for the admin bundle.
 */
final class AdminProvider extends ServiceProvider
{
    /**
     * Admin menu slug shared by the top-level page and its submenu.
     *
     * Contains "smooth" so AssetsProvider's admin screen gate
     * (`str_contains($screen->id, 'smooth')`) loads the admin bundle here.
     */
    public const MENU_SLUG = 'smooth-tables';

    /**
     * Capability required to view and use the Smooth dashboard.
     */
    public const CAPABILITY = 'manage_options';

    /**
     * DOM id of the admin bundle mount point.
     */
    public const ROOT_ID = 'smooth-admin-root';

    /**
     * `data-screen` value the admin bundle dispatches on.
     */
    public const SCREEN_TABLES = 'tables';

    /**
     * Style handle for the WordPress component library.
     *
     * Enqueued on the tables screen only, so the bundle's own stylesheet is
     * never paid for elsewhere in wp-admin.
     */
    public const COMPONENTS_STYLE = 'wp-components';

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
     * Shell: no admin bindings yet.
     *
     * @param Container $container The DI container.
     * @return void
     */
    public function register(Container $container): void
    {
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
        add_action('admin_enqueue_scripts', array( $this, 'enqueueScreenStyles' ));
        $this->markBooted();
    }

    /**
     * Register the Smooth dashboard page and its Tables & QR submenu.
     *
     * @return void
     */
    public function registerMenu(): void
    {
        if (! function_exists('add_menu_page')) {
            return;
        }

        add_menu_page(
            __('Smooth Restaurant', 'smooth-restaurant'),
            __('Smooth', 'smooth-restaurant'),
            self::CAPABILITY,
            self::MENU_SLUG,
            array( $this, 'renderScreen' ),
            'dashicons-store',
            56
        );

        $this->registerSubmenu();
    }

    /**
     * Register the "Tables & QR" submenu under the Smooth top-level page.
     *
     * @return void
     */
    public function registerSubmenu(): void
    {
        if (! function_exists('add_submenu_page')) {
            return;
        }

        add_submenu_page(
            self::MENU_SLUG,
            __('Tables & QR', 'smooth-restaurant'),
            __('Tables & QR', 'smooth-restaurant'),
            self::CAPABILITY,
            self::MENU_SLUG,
            array( $this, 'renderScreen' )
        );
    }

    /**
     * Enqueue the component stylesheet on the tables screen only.
     *
     * @return void
     */
    public function enqueueScreenStyles(): void
    {
        if (! $this->isTablesScreen() || ! function_exists('wp_enqueue_style')) {
            return;
        }

        wp_enqueue_style(self::COMPONENTS_STYLE);
    }

    /**
     * Render the admin bundle mount point.
     *
     * The React table screen mounts on `data-screen="tables"`; the bundle
     * itself is enqueued by AssetsProvider's smooth admin screen gate.
     *
     * @return void
     */
    public function renderScreen(): void
    {
        if (function_exists('current_user_can') && ! current_user_can(self::CAPABILITY)) {
            return;
        }

        printf(
            '<div id="%s" data-screen="%s"></div>',
            function_exists('esc_attr') ? esc_attr(self::ROOT_ID) : self::ROOT_ID,
            function_exists('esc_attr') ? esc_attr(self::SCREEN_TABLES) : self::SCREEN_TABLES
        );
    }

    /**
     * Whether the current admin screen is the tables screen.
     *
     * @return bool
     */
    protected function isTablesScreen(): bool
    {
        if (! function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();
        if (! is_object($screen) || ! isset($screen->id)) {
            return false;
        }

        return is_string($screen->id) && str_contains($screen->id, self::MENU_SLUG);
    }
}
