<?php

/**
 * About Us admin screen.
 *
 * @package SmoothRestaurant
 */

declare(strict_types=1);

namespace SmoothRestaurant\Core;

use SmoothRestaurant\Domains\Menu\MenuAdminScreen;

/**
 * Class AboutUs
 *
 * Owns the About Us screen's identifiers and storage option so the slug, hook
 * suffix, and page URL are defined exactly once.
 *
 * Extends Settings per the team decision that feature surfaces inherit the
 * shared settings surface. It overrides only the option name, so About Us can
 * never read or write the plugin-wide smooth_settings option. No
 * about_us_* keys exist yet: DEFAULTS is inherited and unused until the page
 * grows a real field.
 *
 * The screen is server-rendered. About Us owns no JavaScript, CSS, or
 * localized config; the existing AssetsProvider gate already enqueues the
 * admin surface on any screen whose id contains "smooth".
 */
final class AboutUs extends Settings
{
    /**
     * Option holding About Us settings.
     *
     * Namespaced away from Settings::OPTION so this surface never collides
     * with the plugin-wide settings.
     */
    public const OPTION = 'smooth_about_us_settings';

    /**
     * Parent admin menu slug. Aliased from the Menu screen so the Smooth menu
     * slug is defined exactly once across the plugin.
     */
    public const MENU_SLUG = MenuAdminScreen::MENU_SLUG;

    /**
     * About Us submenu page slug. Derived from the parent menu slug, which
     * also guarantees the slug contains "smooth" so the asset gate passes.
     */
    public const PAGE_SLUG = self::MENU_SLUG . '-about-us';

    /**
     * Admin page hook suffix for this page, derived the way WordPress does:
     * "{parent-slug}_page_{page-slug}".
     */
    public const PAGE_HOOK = self::MENU_SLUG . '_page_' . self::PAGE_SLUG;

    /**
     * URL of the About Us admin screen.
     *
     * @return string Admin URL, or an empty string when admin_url() is unavailable (unit context).
     */
    public static function pageUrl(): string
    {
        if (! function_exists('admin_url')) {
            return '';
        }

        return admin_url('admin.php?page=' . self::PAGE_SLUG);
    }
}
