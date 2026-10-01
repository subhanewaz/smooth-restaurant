<?php

/**
 * REST request detection tests.
 *
 * WordPress defines REST_REQUEST inside rest_api_loaded(), which runs on
 * parse_request -- after plugins_loaded. A provider that only reads that
 * constant therefore never registers on a real REST request, so detection
 * must also recognise the plain permalink (`/wp-json/...`) and the
 * `?rest_route=` fallback used when pretty permalinks are off.
 *
 * @package SmoothRestaurant
 */

declare(strict_types=1);

namespace SmoothRestaurant\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use SmoothRestaurant\Core\Context;
use SmoothRestaurant\Core\Plugin;
use SmoothRestaurant\Providers\RestProvider;

/**
 * Class ContextRestDetectionTest
 */
final class ContextRestDetectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        sr_test_reset_stubs();
        Plugin::reset();
        Context::reset();
        unset($_GET['rest_route'], $_SERVER['REQUEST_URI']);
    }

    protected function tearDown(): void
    {
        unset($_GET['rest_route'], $_SERVER['REQUEST_URI'], $GLOBALS['__sr_test_rest_prefix']);
        Plugin::reset();
        Context::reset();
        parent::tearDown();
    }

    public function test_the_rest_route_query_parameter_is_detected(): void
    {
        $_GET['rest_route'] = '/smooth/v1/tables';

        $this->assertTrue(Context::isRestRequest());
        $this->assertSame(Context::REST, Context::current());
    }

    public function test_the_rest_route_query_parameter_detects_the_namespace_root(): void
    {
        $_GET['rest_route'] = '/';

        $this->assertTrue(Context::isRestRequest());
    }

    public function test_a_permalink_under_the_rest_prefix_is_detected(): void
    {
        $_SERVER['REQUEST_URI'] = '/wp-json/smooth/v1/tables';

        $this->assertTrue(Context::isRestRequest());
        $this->assertSame(Context::REST, Context::current());
    }

    public function test_a_custom_rest_prefix_is_honoured(): void
    {
        $GLOBALS['__sr_test_rest_prefix'] = 'api';

        $_SERVER['REQUEST_URI'] = '/api/smooth/v1/tables';
        $this->assertTrue(Context::isRestRequest());

        $_SERVER['REQUEST_URI'] = '/wp-json/smooth/v1/tables';
        $this->assertFalse(Context::isRestRequest());
    }

    public function test_a_diner_frontend_request_is_not_a_rest_request(): void
    {
        $_SERVER['REQUEST_URI'] = '/menu/?table=Patio';

        $this->assertFalse(Context::isRestRequest());
        $this->assertSame(Context::FRONTEND, Context::current());
    }

    public function test_a_path_that_merely_mentions_the_prefix_is_not_detected(): void
    {
        $_SERVER['REQUEST_URI'] = '/blog/how-wp-json-works/';

        $this->assertFalse(Context::isRestRequest());
    }

    public function test_an_empty_request_uri_is_not_detected(): void
    {
        $_SERVER['REQUEST_URI'] = '';

        $this->assertFalse(Context::isRestRequest());
    }

    public function test_no_superglobals_at_all_is_not_detected(): void
    {
        $this->assertFalse(Context::isRestRequest());
        $this->assertSame(Context::FRONTEND, Context::current());
    }

    public function test_the_rest_provider_boots_and_hooks_routes_without_the_constant(): void
    {
        $_SERVER['REQUEST_URI'] = '/wp-json/smooth/v1/tables';

        Plugin::instance()->boot();

        $indexed = array();
        foreach (Plugin::instance()->container()->providers() as $provider) {
            $indexed[$provider::class] = $provider;
        }

        $this->assertArrayHasKey(RestProvider::class, $indexed);
        $this->assertTrue($indexed[RestProvider::class]->booted());

        $hooks = $GLOBALS['__sr_test_hooks']['actions']['rest_api_init'] ?? array();
        $this->assertNotEmpty($hooks, 'rest_api_init must be hooked for routes to register.');
    }

    public function test_an_admin_request_does_not_register_the_rest_provider(): void
    {
        $GLOBALS['__sr_test_flags']['is_admin'] = true;
        $_SERVER['REQUEST_URI']                  = '/wp-admin/admin.php?page=smooth-tables';

        Plugin::instance()->boot();

        $classes = array();
        foreach (Plugin::instance()->container()->providers() as $provider) {
            $classes[] = $provider::class;
        }

        $this->assertNotContains(RestProvider::class, $classes);
    }
}
