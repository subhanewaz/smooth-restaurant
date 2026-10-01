<?php

/**
 * Admin provider unit tests.
 *
 * Covers the Smooth dashboard menu registration (top-level page plus the
 * Tables & QR submenu), the screen-scoped style enqueue, and the admin bundle
 * mount point rendered by the screen callback.
 *
 * @package SmoothRestaurant
 */

declare(strict_types=1);

namespace SmoothRestaurant\Tests\Unit\Providers;

use PHPUnit\Framework\TestCase;
use SmoothRestaurant\Core\Container;
use SmoothRestaurant\Providers\AdminProvider;

/**
 * Class AdminProviderTest
 */
final class AdminProviderTest extends TestCase
{
    /**
     * Reset the stub harness before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        sr_test_reset_stubs();
    }

    /**
     * The constants describe the dashboard and its screen.
     *
     * @return void
     */
    public function test_constants_describe_the_dashboard(): void
    {
        $this->assertSame('smooth-tables', AdminProvider::MENU_SLUG);
        $this->assertSame('manage_options', AdminProvider::CAPABILITY);
        $this->assertSame('smooth-admin-root', AdminProvider::ROOT_ID);
        $this->assertSame('tables', AdminProvider::SCREEN_TABLES);
    }

    /**
     * The slug must contain "smooth" so the admin asset gate matches.
     *
     * @return void
     */
    public function test_menu_slug_satisfies_the_asset_gate(): void
    {
        $this->assertStringContainsString('smooth', AdminProvider::MENU_SLUG);
    }

    /**
     * registerMenu() registers the top-level "Smooth" page.
     *
     * @return void
     */
    public function test_register_menu_registers_the_smooth_page(): void
    {
        $provider = new AdminProvider(new Container());
        $provider->registerMenu();

        $page = $GLOBALS['__sr_test_admin_pages'][0];
        $this->assertSame('Smooth', $page['menu_title']);
        $this->assertSame(AdminProvider::MENU_SLUG, $page['slug']);
        $this->assertSame(AdminProvider::CAPABILITY, $page['capability']);
        $this->assertIsCallable($page['callback']);
    }

    /**
     * The page and its submenu share one slug so the screen id is stable.
     *
     * @return void
     */
    public function test_register_menu_adds_a_tables_and_qr_submenu_on_the_same_slug(): void
    {
        $provider = new AdminProvider(new Container());
        $provider->registerMenu();

        $submenu = $GLOBALS['__sr_test_admin_pages'][1];
        $this->assertSame('Tables & QR', $submenu['menu_title']);
        $this->assertSame(AdminProvider::MENU_SLUG, $submenu['parent_slug']);
        $this->assertSame(AdminProvider::MENU_SLUG, $submenu['slug']);
        $this->assertSame(AdminProvider::CAPABILITY, $submenu['capability']);
        $this->assertIsCallable($submenu['callback']);
    }

    /**
     * The screen renders the React mount point for the tables screen.
     *
     * @return void
     */
    public function test_render_screen_outputs_the_tables_mount_point(): void
    {
        $provider = new AdminProvider(new Container());

        \ob_start();
        $provider->renderScreen();
        $markup = (string) \ob_get_clean();

        $this->assertStringContainsString('id="smooth-admin-root"', $markup);
        $this->assertStringContainsString('data-screen="tables"', $markup);
    }

    /**
     * The component stylesheet loads on the tables screen.
     *
     * @return void
     */
    public function test_component_styles_enqueue_on_the_tables_screen(): void
    {
        sr_test_set_screen((object) array( 'id' => 'toplevel_page_smooth-tables' ));

        $provider = new AdminProvider(new Container());
        $provider->enqueueScreenStyles();

        $this->assertContains(AdminProvider::COMPONENTS_STYLE, $GLOBALS['__sr_test_enqueues']['styles']);
    }

    /**
     * No styles leak onto other wp-admin screens.
     *
     * @return void
     */
    public function test_component_styles_do_not_enqueue_elsewhere(): void
    {
        sr_test_set_screen((object) array( 'id' => 'dashboard' ));

        $provider = new AdminProvider(new Container());
        $provider->enqueueScreenStyles();

        $this->assertSame(array(), $GLOBALS['__sr_test_enqueues']['styles'] ?? array());
    }

    /**
     * The style callback stays safe when no screen is available.
     *
     * @return void
     */
    public function test_component_styles_are_skipped_without_a_screen(): void
    {
        sr_test_set_screen(null);

        $provider = new AdminProvider(new Container());
        $provider->enqueueScreenStyles();

        $this->assertSame(array(), $GLOBALS['__sr_test_enqueues']['styles'] ?? array());
    }
}
