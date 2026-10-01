<?php

/**
 * Request context helper.
 *
 * @package SmoothRestaurant
 */

declare(strict_types=1);

namespace SmoothRestaurant\Core;

/**
 * Class Context
 *
 * Resolves the current request context so `Plugin::registerProviders()` can
 * skip providers whose `boot()` would bail before instantiating them.
 * Every check is `function_exists`-guarded (or constant-guarded), so unit
 * tests without WordPress read as the frontend default.
 *
 * Tests may force a context via `override()`; always pair with `reset()`.
 */
final class Context
{
    /**
     * Admin dashboard context.
     */
    public const ADMIN = 'admin';

    /**
     * REST API context.
     */
    public const REST = 'rest';

    /**
     * Cron context.
     */
    public const CRON = 'cron';

    /**
     * Diner frontend context (default when WordPress is not loaded).
     */
    public const FRONTEND = 'frontend';

    /**
     * WP-CLI context.
     */
    public const CLI = 'cli';

    /**
     * Forced context for tests, null in production.
     *
     * @var string|null
     */
    private static ?string $override = null;

    /**
     * Resolve the current request context.
     *
     * Precedence: cron, REST, admin, WP-CLI, frontend.
     *
     * @return string One of admin|rest|cron|frontend|cli.
     */
    public static function current(): string
    {
        if (null !== self::$override) {
            return self::$override;
        }

        if (function_exists('wp_doing_cron') && wp_doing_cron()) {
            return self::CRON;
        }

        if (self::isRestRequest()) {
            return self::REST;
        }

        if (function_exists('is_admin') && is_admin()) {
            return self::ADMIN;
        }

        if (defined('WP_CLI') && (bool) WP_CLI) {
            return self::CLI;
        }

        return self::FRONTEND;
    }

    /**
     * Whether the current request targets the REST API.
     *
     * `REST_REQUEST` alone is not enough: WordPress defines that constant
     * inside `rest_api_loaded()`, which runs on `parse_request` -- long after
     * `plugins_loaded`. Providers gated on it would never be registered on a
     * real REST request, so this also recognises the two shapes WordPress
     * itself resolves the REST route from:
     * - `?rest_route=/…` (the fallback when pretty permalinks are off), and
     * - a permalink under `/{rest_get_url_prefix()}/…`.
     *
     * Every WordPress call is `function_exists`-guarded so the class keeps
     * working in unit tests that run without WordPress.
     *
     * @return bool True when the request should be treated as REST.
     */
    public static function isRestRequest(): bool
    {
        if (defined('REST_REQUEST') && (bool) REST_REQUEST) {
            return true;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- REST route detection needs no nonce; self::clean() applies wp_unslash() then sanitize_text_field() when WordPress is loaded, and the value is only inspected, never stored.
        $restRoute = isset($_GET['rest_route']) ? self::clean($_GET['rest_route']) : '';

        if ('' !== $restRoute) {
            return true;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- as above, read-only inspection.
        $requestUri = isset($_SERVER['REQUEST_URI']) ? self::clean($_SERVER['REQUEST_URI']) : '';

        if ('' === $requestUri || ! function_exists('rest_get_url_prefix')) {
            return false;
        }

        $prefix = self::clean(rest_get_url_prefix());

        return '' !== $prefix && str_contains($requestUri, '/' . $prefix . '/');
    }

    /**
     * Normalize a superglobal fragment to a trimmed string.
     *
     * Unslashes and sanitizes when WordPress is loaded, and never throws on a
     * non-string value (a hostile `?rest_route[]=x` must not fatal the boot).
     *
     * @param mixed $value Raw superglobal value.
     * @return string Cleaned string, empty when the value is not usable.
     */
    private static function clean(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        if (function_exists('wp_unslash')) {
            $value = wp_unslash($value);
        }

        if (function_exists('sanitize_text_field')) {
            $value = sanitize_text_field($value);
        }

        return is_string($value) ? trim($value) : '';
    }

    /**
     * Force a context.
     *
     * @internal For unit tests only. Production code MUST NOT call this.
     *
     * @param string|null $context Forced context, null to clear.
     * @return void
     */
    public static function override(?string $context): void
    {
        self::$override = $context;
    }

    /**
     * Clear any forced context.
     *
     * @internal For unit tests only.
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$override = null;
    }
}
