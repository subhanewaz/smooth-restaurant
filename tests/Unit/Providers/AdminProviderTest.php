<?php

/**
 * Unit tests for the admin provider wiring.
 *
 * @package SmoothRestaurant
 */

declare(strict_types=1);

namespace SmoothRestaurant\Tests\Unit\Providers;

use PHPUnit\Framework\TestCase;
use SmoothRestaurant\Core\Container;
use SmoothRestaurant\Core\Context;
use SmoothRestaurant\Core\Plugin;
use SmoothRestaurant\Domains\Menu\MenuAdminScreen;
use SmoothRestaurant\Providers\AdminProvider;
use SmoothRestaurant\Providers\MenuProvider;

/**
 * Class AdminProviderTest
 */
final class AdminProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Plugin::reset();
        Context::reset();
        sr_test_reset_stubs();
    }

    protected function tearDown(): void
    {
        sr_test_reset_stubs();
        Context::reset();
        Plugin::reset();
        parent::tearDown();
    }

    public function test_register_binds_screen_singleton_only(): void
    {
        $container = new Container();
        $provider  = new AdminProvider($container);
        $provider->register($container);

        $this->assertTrue($container->has(MenuAdminScreen::class));
        $this->assertFalse(has_action('admin_menu'));
    }

    public function test_boot_bails_outside_admin(): void
    {
        $container = new Container();
        $container->register(MenuProvider::class);
        $container->register(AdminProvider::class);
        $container->boot();

        $this->assertFalse(has_action('admin_menu'));
    }

    public function test_boot_hooks_menu_and_post_actions_in_admin(): void
    {
        sr_test_set_flag('is_admin', true);
        $container = new Container();
        $container->register(MenuProvider::class);
        $container->register(AdminProvider::class);
        $container->boot();

        $this->assertNotFalse(has_action('admin_menu'));
        $this->assertNotFalse(has_action('admin_post_smooth_restaurant_save_menu'));
        $this->assertNotFalse(has_action('admin_post_smooth_restaurant_delete_menu'));
        $this->assertNotFalse(has_action('admin_post_smooth_restaurant_save_item'));
        $this->assertNotFalse(has_action('admin_post_smooth_restaurant_delete_item'));
        $this->assertNotFalse(has_action('admin_post_smooth_restaurant_save_modifier'));
        $this->assertNotFalse(has_action('admin_post_smooth_restaurant_delete_modifier'));

        $providers = $container->providers();
        $admin     = null;
        foreach ($providers as $provider) {
            if ($provider instanceof AdminProvider) {
                $admin = $provider;
            }
        }
        $this->assertNotNull($admin);
        $this->assertTrue($admin->booted());
        $this->assertSame(array( 'admin' ), AdminProvider::contexts());
    }

    public function test_register_menu_uses_manage_cap_and_smooth_slugs(): void
    {
        sr_test_set_flag('is_admin', true);
        sr_test_grant_caps(array( MenuProvider::MANAGE_CAP ));
        $container = new Container();
        $container->register(MenuProvider::class);
        $container->register(AdminProvider::class);
        $container->boot();

        $admin = null;
        foreach ($container->providers() as $provider) {
            if ($provider instanceof AdminProvider) {
                $admin = $provider;
            }
        }
        $this->assertNotNull($admin);
        $admin->registerMenu();

        $slugs = \array_column($GLOBALS['__sr_test_menu_pages'], 'slug');
        $this->assertContains(MenuAdminScreen::MENU_SLUG, $slugs);
        $this->assertContains(MenuAdminScreen::PAGE_SLUG, $slugs);
        $this->assertStringContainsString('smooth', MenuAdminScreen::MENU_SLUG);
        $this->assertStringContainsString('smooth', MenuAdminScreen::PAGE_SLUG);

        foreach ($GLOBALS['__sr_test_menu_pages'] as $page) {
            $this->assertSame(MenuProvider::MANAGE_CAP, $page['cap']);
        }
    }

    public function test_register_menu_skips_without_cap(): void
    {
        sr_test_set_flag('is_admin', true);
        sr_test_grant_caps(array());
        $container = new Container();
        $container->register(MenuProvider::class);
        $container->register(AdminProvider::class);
        $container->boot();

        $admin = null;
        foreach ($container->providers() as $provider) {
            if ($provider instanceof AdminProvider) {
                $admin = $provider;
            }
        }
        $this->assertNotNull($admin);
        $admin->registerMenu();

        $this->assertSame(array(), $GLOBALS['__sr_test_menu_pages']);
    }
}
