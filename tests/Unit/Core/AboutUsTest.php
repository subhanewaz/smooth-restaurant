<?php

/**
 * Unit tests for the About Us screen surface.
 *
 * @package SmoothRestaurant
 */

declare(strict_types=1);

namespace SmoothRestaurant\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use SmoothRestaurant\Core\AboutUs;
use SmoothRestaurant\Core\Settings;
use SmoothRestaurant\Domains\Menu\MenuAdminScreen;

/**
 * Class AboutUsTest
 *
 * WordPress is not loaded in unit context, so these tests cover the inherited
 * Settings surface plus the static identifiers used by AdminProvider.
 */
final class AboutUsTest extends TestCase
{
    public function test_about_us_extends_settings(): void
    {
        $this->assertInstanceOf(Settings::class, new AboutUs());
    }

    public function test_about_us_uses_a_namespaced_option(): void
    {
        $this->assertSame('smooth_about_us_settings', AboutUs::OPTION);
        $this->assertNotSame(Settings::OPTION, AboutUs::OPTION);
    }

    public function test_inherited_defaults_resolve_via_static_dispatch(): void
    {
        $aboutUs = new AboutUs();

        $this->assertSame('USD', $aboutUs->get('smooth_currency'));
        $this->assertSame('site', $aboutUs->get('smooth_timezone_mode'));
    }

    public function test_menu_slug_aliases_the_existing_smooth_menu(): void
    {
        $this->assertSame(MenuAdminScreen::MENU_SLUG, AboutUs::MENU_SLUG);
    }

    public function test_page_slug_is_derived_from_the_menu_slug(): void
    {
        $this->assertSame(MenuAdminScreen::MENU_SLUG . '-about-us', AboutUs::PAGE_SLUG);
        $this->assertSame('smooth-about-us', AboutUs::PAGE_SLUG);
        $this->assertStringContainsString(MenuAdminScreen::MENU_SLUG, AboutUs::PAGE_SLUG);
    }

    public function test_page_hook_is_derived_not_literal(): void
    {
        $this->assertSame(
            MenuAdminScreen::MENU_SLUG . '_page_' . AboutUs::PAGE_SLUG,
            AboutUs::PAGE_HOOK
        );
        $this->assertSame('smooth_page_smooth-about-us', AboutUs::PAGE_HOOK);
    }

    public function test_hook_derivation_matches_the_menus_page(): void
    {
        $this->assertSame(
            MenuAdminScreen::MENU_SLUG . '_page_' . MenuAdminScreen::PAGE_SLUG,
            MenuAdminScreen::MENUS_HOOK
        );
    }

    public function test_page_url_points_at_the_submenu(): void
    {
        $this->assertSame(
            'http://example.test/wp-admin/admin.php?page=smooth-about-us',
            AboutUs::pageUrl()
        );
    }
}
