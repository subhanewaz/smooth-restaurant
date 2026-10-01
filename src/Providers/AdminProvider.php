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
 * Smooth dashboard page and renders the mount point for the admin bundle.
 */
final class AdminProvider extends ServiceProvider
{
    /**
     * Admin menu slug for the Smooth dashboard.
     *
     * Contains "smooth" so AssetsProvider's admin screen gate
     * (`str_contains($screen->id, 'smooth')`) loads the admin bundle here.
     */
    public const SLUG = 'smooth-tables';

    /**
     * Capability required to view and use the Smooth dashboard.
     */
    public const CAPABILITY = 'manage_options';

    /**
     * DOM id of the admin bundle mount point.
     */
    public const ROOT_ID = 'smooth-admin-root';

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
        $this->markBooted();
    }

    /**
     * Register the Smooth dashboard admin page.
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
            self::SLUG,
            array( $this, 'renderScreen' ),
            'dashicons-store',
            56
        );
    }

    /**
     * Render the admin bundle mount point.
     *
     * The React table screen mounts on `data-screen="tables"`; asset
     * enqueueing is owned by AssetsProvider's smooth admin screen gate.
     *
     * @return void
     */
    public function renderScreen(): void
    {
        if (function_exists('current_user_can') && ! current_user_can(self::CAPABILITY)) {
            return;
        }

        printf(
            '<div id="%s" data-screen="tables"></div>',
            function_exists('esc_attr') ? esc_attr(self::ROOT_ID) : self::ROOT_ID
        );
    }
}
