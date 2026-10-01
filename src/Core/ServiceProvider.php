<?php

/**
 * Abstract service provider.
 *
 * @package SmoothRestaurant
 */

declare(strict_types=1);

namespace SmoothRestaurant\Core;

/**
 * Class ServiceProvider
 *
 * Base class for all service providers.
 *
 * Lifecycle discipline (review-enforced):
 *
 * - `register()` binds services into the container ONLY. No `add_action` /
 *   `add_filter` calls, no database access, no translation calls.
 * - `boot()` registers hooks ONLY, and its first line bails early when the
 *   current request is outside the provider's context (admin, REST, cron,
 *   or diner frontend). Use the `isAdmin()`, `isDoingCron()`,
 *   `isDoingRest()`, and `isBackendRequest()` helpers below.
 * - In-context `boot()` implementations call `markBooted()` so the boot
 *   matrix stays observable via `booted()`.
 */
abstract class ServiceProvider
{
    /**
     * Provider version, checked against the floor in `Plugin::registerProviders()`.
     *
     * Pro providers MUST declare a version at or above the floor; entries
     * below it are skipped and logged. Free providers inherit this default.
     *
     * @var string
     */
    public const VERSION = '0.1.0';

    /**
     * The container instance.
     *
     * @var Container
     */
    protected Container $container;

    /**
     * Whether boot() ran in context (as opposed to bailing early).
     *
     * @var bool
     */
    private bool $booted = false;

    /**
     * Request contexts this provider participates in.
     *
     * `Plugin::registerProviders()` filters the final provider list by
     * `Context::current()` before instantiation, so providers whose `boot()`
     * would bail are never constructed. The `'all'` wildcard (the default)
     * keeps providers like `CoreProvider` and `DatabaseProvider` on every
     * request; Pro appends without an override inherit it. Values are one
     * of `admin|rest|cron|frontend|cli`.
     *
     * MUST mirror the provider's `boot()` guard: a context listed here must
     * reach past the early bail, and every context that reaches past the
     * bail must be listed.
     *
     * @return list<string>
     */
    public static function contexts(): array
    {
        return array( 'all' );
    }

    /**
     * Constructor.
     *
     * @param Container $container The DI container.
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Register services with the container.
     *
     * Bind-only: no hooks, no database access, no translation calls.
     *
     * @param Container $container The DI container.
     * @return void
     */
    abstract public function register(Container $container): void;

    /**
     * Boot the provider after all providers are registered.
     *
     * Hook-only: the first line MUST bail early outside the provider's
     * context. Call `markBooted()` once the provider does its work.
     *
     * @param Container $container The DI container.
     * @return void
     */
    public function boot(Container $container): void
    {
        // Override in providers that need boot-time logic.
    }

    /**
     * Whether boot() executed in context instead of bailing early.
     *
     * @return bool
     */
    final public function booted(): bool
    {
        return $this->booted;
    }

    /**
     * Mark boot() as executed in context.
     *
     * @return void
     */
    final protected function markBooted(): void
    {
        $this->booted = true;
    }

    /**
     * Whether the current request is a wp-admin request.
     *
     * Returns false when WordPress is not loaded (unit-test frontend default).
     *
     * @return bool
     */
    protected function isAdmin(): bool
    {
        return function_exists('is_admin') && is_admin();
    }

    /**
     * Whether the current request is a cron run.
     *
     * Returns false when WordPress is not loaded (unit-test frontend default).
     *
     * @return bool
     */
    protected function isDoingCron(): bool
    {
        return function_exists('wp_doing_cron') && wp_doing_cron();
    }

    /**
     * Whether the current request is a REST request.
     *
     * Delegates to {@see Context::isRestRequest()}, which also recognises the
     * permalink and `?rest_route=` shapes because WordPress only defines the
     * REST_REQUEST constant on `parse_request`, after `plugins_loaded`.
     *
     * @return bool
     */
    protected function isDoingRest(): bool
    {
        return Context::isRestRequest();
    }

    /**
     * Whether the current request is outside the diner frontend.
     *
     * Diner-facing providers bail early when this returns true.
     *
     * @return bool
     */
    protected function isBackendRequest(): bool
    {
        return $this->isAdmin() || $this->isDoingCron() || $this->isDoingRest();
    }
}
