<?php

declare(strict_types=1);

namespace SmoothRestaurant\Tests\Unit\Providers;

use PHPUnit\Framework\TestCase;
use ReflectionFunction;
use SmoothRestaurant\Core\Container;
use SmoothRestaurant\Providers\RestProvider;
use SmoothRestaurant\Rest\TablesController;

/**
 * Unit tests for tables REST route registration.
 *
 * WordPress route registration is stubbed (see tests/Support/WpStubs.php):
 * register_rest_route() records every route in $GLOBALS['__sr_test_routes'].
 * That the routes work on real WordPress is verified separately on wp-env.
 */
final class RestRoutesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        sr_test_reset_stubs();
    }

    public function test_register_binds_the_tables_controller(): void
    {
        $container = new Container();
        $provider  = new RestProvider($container);
        $provider->register($container);

        $this->assertTrue($container->has(TablesController::class));
        $this->assertInstanceOf(TablesController::class, $container->make(TablesController::class));
    }

    public function test_register_routes_registers_every_tables_route(): void
    {
        $methods = $this->registeredMethods();

        $this->assertSame(array( 'GET', 'POST' ), $methods['tables']);
        $this->assertSame(array( 'DELETE' ), $methods['tables/(?P<id>\d+)']);
        $this->assertSame(array( 'POST' ), $methods['tables/(?P<id>\d+)/state']);
    }

    public function test_register_routes_uses_the_versioned_namespace(): void
    {
        $this->registerRoutes();

        $routes = $GLOBALS['__sr_test_routes'];

        $this->assertNotEmpty($routes);
        foreach ($routes as $entry) {
            $this->assertSame(RestProvider::NAMESPACE, $entry['namespace']);
        }
    }

    public function test_every_route_requires_manage_options(): void
    {
        $this->registerRoutes();

        foreach ($GLOBALS['__sr_test_routes'] as $entry) {
            foreach ($entry['args'] as $endpoint) {
                $this->assertArrayHasKey('permission_callback', $endpoint);
                $callback = $endpoint['permission_callback'];
                $this->assertIsCallable($callback);

                $reflection = new ReflectionFunction(\Closure::fromCallable($callback));
                $this->assertSame('manage_options', $reflection->getStaticVariables()['cap'] ?? null);
            }
        }
    }

    public function test_permission_callback_allows_without_wordpress(): void
    {
        $this->registerRoutes();

        foreach ($GLOBALS['__sr_test_routes'] as $entry) {
            foreach ($entry['args'] as $endpoint) {
                $this->assertTrue(( $endpoint['permission_callback'] )());
            }
        }
    }

    /**
     * Route path => list of HTTP methods, from the recorded registrations.
     *
     * @return array<string, list<string>>
     */
    private function registeredMethods(): array
    {
        $this->registerRoutes();

        $methods = array();
        foreach ($GLOBALS['__sr_test_routes'] as $entry) {
            $path = \trim($entry['route'], '/');
            foreach ($entry['args'] as $endpoint) {
                $methods[ $path ][] = $endpoint['methods'];
            }
        }

        return $methods;
    }

    private function registerRoutes(): void
    {
        $container = new Container();
        $provider  = new RestProvider($container);
        $provider->register($container);
        $provider->registerRoutes();
    }
}
