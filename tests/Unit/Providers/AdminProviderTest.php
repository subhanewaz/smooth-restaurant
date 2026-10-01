<?php

/**
 * Admin provider unit tests.
 *
 * Covers the Smooth dashboard menu registration (slug, capability) and the
 * admin bundle mount point rendered by the screen callback.
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
     * The slug must contain "smooth" so the admin asset gate matches.
     *
     * @return void
     */
    public function test_constants_describe_the_dashboard(): void
    {
        $this->assertStringContainsString('smooth', AdminProvider::SLUG);
        $this->assertSame('manage_options', AdminProvider::CAPABILITY);
        $this->assertSame('smooth-admin-root', AdminProvider::ROOT_ID);
    }

    /**
     * registerMenu() registers the dashboard with the right capability.
     *
     * @return void
     */
    public function test_register_menu_registers_the_smooth_page(): void
    {
        $provider = new AdminProvider(new Container());
        $provider->registerMenu();

        $this->assertCount(1, $GLOBALS['__sr_test_admin_pages']);
        $page = $GLOBALS['__sr_test_admin_pages'][0];
        $this->assertSame(AdminProvider::SLUG, $page['slug']);
        $this->assertSame(AdminProvider::CAPABILITY, $page['capability']);
        $this->assertIsCallable($page['callback']);
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
}
