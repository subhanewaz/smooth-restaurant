<?php

/**
 * Minimal WordPress function stubs for the Unit suite.
 *
 * These are defined ONLY when the real WordPress test environment is not
 * loaded, so wp-env runs keep using the real functions. The stubs model a
 * bare frontend request: no admin, no cron, no enqueued assets. Tests switch
 * contexts through `$GLOBALS['__sr_test_flags']`.
 *
 * Supported hooks subset: add_action / has_action / remove_action /
 * add_filter / has_filter / remove_filter / apply_filters / do_action.
 *
 * Supported REST subset: register_rest_route / rest_ensure_response /
 * home_url plus minimal WP_REST_Request, WP_REST_Response, and WP_Error
 * shims so controllers are exercisable without WordPress. Registered routes
 * are recorded in `$GLOBALS['__sr_test_routes']`.
 *
 * @package SmoothRestaurant
 */

declare(strict_types=1);

if (! isset($GLOBALS['__sr_test_flags']) || ! is_array($GLOBALS['__sr_test_flags'])) {
    $GLOBALS['__sr_test_flags'] = array(
        'is_admin'   => false,
        'doing_cron' => false,
    );
}

if (! isset($GLOBALS['__sr_test_hooks']) || ! is_array($GLOBALS['__sr_test_hooks'])) {
    $GLOBALS['__sr_test_hooks'] = array(
        'actions' => array(),
        'filters' => array(),
    );
}

if (! isset($GLOBALS['__sr_test_enqueues']) || ! is_array($GLOBALS['__sr_test_enqueues'])) {
    $GLOBALS['__sr_test_enqueues'] = array();
}

if (! isset($GLOBALS['__sr_test_routes']) || ! is_array($GLOBALS['__sr_test_routes'])) {
    $GLOBALS['__sr_test_routes'] = array();
}

if (! function_exists('sr_test_reset_stubs')) {
    /**
     * Reset hook storage, enqueue log, and context flags between tests.
     *
     * @return void
     */
    function sr_test_reset_stubs(): void
    {
        $GLOBALS['__sr_test_hooks']    = array(
            'actions' => array(),
            'filters' => array(),
        );
        $GLOBALS['__sr_test_enqueues'] = array();
        $GLOBALS['__sr_test_routes']   = array();
        $GLOBALS['__sr_test_flags']    = array(
            'is_admin'   => false,
            'doing_cron' => false,
        );
    }
}

if (! function_exists('sr_test_set_flag')) {
    /**
     * Set a context flag consumed by the conditional stubs.
     *
     * @param string $flag  Flag name ('is_admin' or 'doing_cron').
     * @param bool   $value Flag value.
     * @return void
     */
    function sr_test_set_flag(string $flag, bool $value): void
    {
        $GLOBALS['__sr_test_flags'][ $flag ] = $value;
    }
}

if (! function_exists('sr_test_add_hook')) {
    /**
     * Store a hook callback in the stub registry.
     *
     * @param array<string, mixed> $hooks    Registry slice (actions or filters).
     * @param string               $hook     Hook name.
     * @param callable             $callback Callback.
     * @param int                  $priority Priority.
     * @return void
     */
    function sr_test_add_hook(array &$hooks, string $hook, callable $callback, int $priority): void
    {
        $hooks[ $hook ][] = array(
            'callback' => $callback,
            'priority' => $priority,
        );
        usort(
            $hooks[ $hook ],
            static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']
        );
    }
}

if (! function_exists('add_action')) {
    /**
     * Stub for add_action().
     *
     * @param string   $hook     Hook name.
     * @param callable $callback Callback.
     * @param int      $priority Priority.
     * @return void
     */
    function add_action(string $hook, callable $callback, int $priority = 10): void
    {
        sr_test_add_hook($GLOBALS['__sr_test_hooks']['actions'], $hook, $callback, $priority);
    }
}

if (! function_exists('has_action')) {
    /**
     * Stub for has_action().
     *
     * @param string $hook Hook name.
     * @return int|false Priority of the first callback, or false.
     */
    function has_action(string $hook): int|false
    {
        $actions = $GLOBALS['__sr_test_hooks']['actions'][ $hook ] ?? null;
        if (! is_array($actions) || [] === $actions) {
            return false;
        }

        return $GLOBALS['__sr_test_hooks']['actions'][ $hook ][0]['priority'];
    }
}

if (! function_exists('remove_action')) {
    /**
     * Stub for remove_action().
     *
     * @param string $hook Hook name.
     * @return bool
     */
    function remove_action(string $hook): bool
    {
        unset($GLOBALS['__sr_test_hooks']['actions'][ $hook ]);

        return true;
    }
}

if (! function_exists('add_filter')) {
    /**
     * Stub for add_filter().
     *
     * @param string   $hook     Hook name.
     * @param callable $callback Callback.
     * @param int      $priority Priority.
     * @return void
     */
    function add_filter(string $hook, callable $callback, int $priority = 10): void
    {
        sr_test_add_hook($GLOBALS['__sr_test_hooks']['filters'], $hook, $callback, $priority);
    }
}

if (! function_exists('has_filter')) {
    /**
     * Stub for has_filter().
     *
     * @param string $hook Hook name.
     * @return int|false Priority of the first callback, or false.
     */
    function has_filter(string $hook): int|false
    {
        $filters = $GLOBALS['__sr_test_hooks']['filters'][ $hook ] ?? null;
        if (! is_array($filters) || [] === $filters) {
            return false;
        }

        return $GLOBALS['__sr_test_hooks']['filters'][ $hook ][0]['priority'];
    }
}

if (! function_exists('remove_filter')) {
    /**
     * Stub for remove_filter().
     *
     * @param string $hook Hook name.
     * @return bool
     */
    function remove_filter(string $hook): bool
    {
        unset($GLOBALS['__sr_test_hooks']['filters'][ $hook ]);

        return true;
    }
}

if (! function_exists('apply_filters')) {
    /**
     * Stub for apply_filters(): threads the value through registered callbacks.
     *
     * @param string $hook  Hook name.
     * @param mixed  $value Filtered value.
     * @param mixed  ...$args Additional arguments.
     * @return mixed
     */
    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
    {
        foreach ($GLOBALS['__sr_test_hooks']['filters'][ $hook ] ?? array() as $entry) {
            $value = ( $entry['callback'] )($value, ...$args);
        }

        return $value;
    }
}

if (! function_exists('do_action')) {
    /**
     * Stub for do_action(): invokes registered callbacks.
     *
     * @param string $hook Hook name.
     * @param mixed  ...$args Action arguments.
     * @return void
     */
    function do_action(string $hook, mixed ...$args): void
    {
        foreach ($GLOBALS['__sr_test_hooks']['actions'][ $hook ] ?? array() as $entry) {
            ( $entry['callback'] )(...$args);
        }
    }
}

if (! function_exists('is_admin')) {
    /**
     * Stub for is_admin(), driven by the 'is_admin' test flag.
     *
     * @return bool
     */
    function is_admin(): bool
    {
        return (bool) ( $GLOBALS['__sr_test_flags']['is_admin'] ?? false );
    }
}

if (! function_exists('wp_doing_cron')) {
    /**
     * Stub for wp_doing_cron(), driven by the 'doing_cron' test flag.
     *
     * @return bool
     */
    function wp_doing_cron(): bool
    {
        return (bool) ( $GLOBALS['__sr_test_flags']['doing_cron'] ?? false );
    }
}

if (! function_exists('wp_enqueue_script')) {
    /**
     * Stub for wp_enqueue_script(): records the handle instead of printing.
     *
     * @param string $handle Script handle.
     * @return void
     */
    function wp_enqueue_script(string $handle): void
    {
        $GLOBALS['__sr_test_enqueues']['scripts'][] = $handle;
    }
}

if (! function_exists('wp_enqueue_style')) {
    /**
     * Stub for wp_enqueue_style(): records the handle instead of printing.
     *
     * @param string $handle Style handle.
     * @return void
     */
    function wp_enqueue_style(string $handle): void
    {
        $GLOBALS['__sr_test_enqueues']['styles'][] = $handle;
    }
}

if (! function_exists('register_rest_route')) {
    /**
     * Stub for register_rest_route(): records the route instead of registering.
     *
     * @param string               $namespace Route namespace.
     * @param string               $route     Route path.
     * @param array<int|string, mixed> $args  Route definitions.
     * @param bool                 $override  Whether to override an existing route.
     * @return bool
     */
    function register_rest_route(string $namespace, string $route, array $args = array(), bool $override = false): bool
    {
        $GLOBALS['__sr_test_routes'][] = array(
            'namespace' => $namespace,
            'route'     => $route,
            'args'      => $args,
        );

        return true;
    }
}

if (! function_exists('rest_ensure_response')) {
    /**
     * Stub for rest_ensure_response().
     *
     * @param mixed $response Response data.
     * @return mixed
     */
    function rest_ensure_response(mixed $response): mixed
    {
        if ($response instanceof WP_REST_Response) {
            return $response;
        }

        return new WP_REST_Response($response);
    }
}

if (! function_exists('home_url')) {
    /**
     * Stub for home_url(), driven by `$GLOBALS['__sr_test_home_url']`.
     *
     * @param string $path Optional path to append.
     * @return string
     */
    function home_url(string $path = ''): string
    {
        $home = $GLOBALS['__sr_test_home_url'] ?? 'http://example.test';

        return \rtrim((string) $home, '/') . '/' . \ltrim($path, '/');
    }
}

if (! class_exists('WP_REST_Request')) {
    /**
     * Minimal WP_REST_Request shim for controller unit tests.
     */
    class WP_REST_Request
    {
        /**
         * @param array<string, mixed> $params Request parameters.
         */
        public function __construct(private array $params = array())
        {
        }

        /**
         * @param string $key Parameter name.
         * @return mixed
         */
        public function get_param(string $key): mixed
        {
            return $this->params[ $key ] ?? null;
        }

        /**
         * @return array<string, mixed>
         */
        public function get_params(): array
        {
            return $this->params;
        }

        /**
         * @param string $key   Parameter name.
         * @param mixed  $value Parameter value.
         * @return void
         */
        public function set_param(string $key, mixed $value): void
        {
            $this->params[ $key ] = $value;
        }
    }
}

if (! class_exists('WP_REST_Response')) {
    /**
     * Minimal WP_REST_Response shim for controller unit tests.
     */
    class WP_REST_Response
    {
        /**
         * @param mixed $data   Response body.
         * @param int   $status HTTP status code.
         */
        public function __construct(private mixed $data = null, private int $status = 200)
        {
        }

        /**
         * @return mixed
         */
        public function get_data(): mixed
        {
            return $this->data;
        }

        /**
         * @param mixed $data Response body.
         * @return void
         */
        public function set_data(mixed $data): void
        {
            $this->data = $data;
        }

        public function get_status(): int
        {
            return $this->status;
        }

        /**
         * @param int $status HTTP status code.
         * @return void
         */
        public function set_status(int $status): void
        {
            $this->status = $status;
        }
    }
}

if (! class_exists('WP_Error')) {
    /**
     * Minimal WP_Error shim for controller unit tests.
     */
    class WP_Error
    {
        /**
         * @param string $code    Error code.
         * @param string $message Human-readable message.
         * @param mixed  $data    Optional error data (e.g. ['status' => 409]).
         */
        public function __construct(
            private string $code = '',
            private string $message = '',
            private mixed $data = null
        ) {
        }

        public function get_error_code(): string
        {
            return $this->code;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }

        /**
         * @return mixed
         */
        public function get_error_data(): mixed
        {
            return $this->data;
        }
    }
}
