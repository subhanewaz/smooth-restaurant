<?php

/**
 * Minimal WordPress function stubs for the Unit suite.
 *
 * These are defined ONLY when the real WordPress test environment is not
 * loaded, so wp-env runs keep using the real functions. The stubs model a
 * bare frontend request: no admin, no cron, no REST, no enqueued assets.
 * Tests switch contexts through `$GLOBALS['__sr_test_flags']`.
 *
 * Supported hooks subset: add_action / has_action / remove_action /
 * add_filter / has_filter / remove_filter / apply_filters / do_action.
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

if (! isset($GLOBALS['__sr_test_caps']) || ! is_array($GLOBALS['__sr_test_caps'])) {
    $GLOBALS['__sr_test_caps'] = array();
}

if (! isset($GLOBALS['__sr_test_routes']) || ! is_array($GLOBALS['__sr_test_routes'])) {
    $GLOBALS['__sr_test_routes'] = array();
}

if (! isset($GLOBALS['__sr_test_bindings']) || ! is_array($GLOBALS['__sr_test_bindings'])) {
    $GLOBALS['__sr_test_bindings'] = array();
}

if (! isset($GLOBALS['__sr_test_cache']) || ! is_array($GLOBALS['__sr_test_cache'])) {
    $GLOBALS['__sr_test_cache'] = array();
}

if (! isset($GLOBALS['__sr_test_dbdelta']) || ! is_array($GLOBALS['__sr_test_dbdelta'])) {
    $GLOBALS['__sr_test_dbdelta'] = array();
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
        $GLOBALS['__sr_test_caps']     = array();
        $GLOBALS['__sr_test_routes']   = array();
        $GLOBALS['__sr_test_bindings'] = array();
        $GLOBALS['__sr_test_cache']    = array();
        $GLOBALS['__sr_test_dbdelta']  = array();
        $GLOBALS['__sr_test_menu_pages'] = array();
        $GLOBALS['__sr_test_redirects']  = array();
        $GLOBALS['__sr_test_flags']    = array(
            'is_admin'   => false,
            'doing_cron' => false,
            'nonce_pass' => true,
            'screen_id'  => '',
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

if (! function_exists('sr_test_grant_caps')) {
    /**
     * Grant capabilities to the stubbed current user.
     *
     * The current_user_can() stub denies everything by default (bare
     * frontend request, no authenticated user); tests opt in here.
     *
     * @param list<string> $caps Capabilities the stub user holds.
     * @return void
     */
    function sr_test_grant_caps(array $caps): void
    {
        $GLOBALS['__sr_test_caps'] = array_values($caps);
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
     * @param string   $hook          Hook name.
     * @param callable $callback      Callback.
     * @param int      $priority      Priority.
     * @param int      $acceptedArgs  Accepted argument count (recorded, not enforced).
     * @return void
     */
    function add_action(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
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
     * @param string   $hook          Hook name.
     * @param callable $callback      Callback.
     * @param int      $priority      Priority.
     * @param int      $acceptedArgs  Accepted argument count (recorded, not enforced).
     * @return void
     */
    function add_filter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
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

if (! function_exists('current_user_can')) {
    /**
     * Stub for current_user_can(): denies everything unless the capability
     * was granted via sr_test_grant_caps().
     *
     * @param string $cap Capability being checked.
     * @return bool
     */
    function current_user_can(string $cap): bool
    {
        return \in_array($cap, $GLOBALS['__sr_test_caps'] ?? array(), true);
    }
}

if (! function_exists('register_rest_route')) {
    /**
     * Stub for register_rest_route(): records the registration for assertions.
     *
     * @param string               $namespace Route namespace.
     * @param string               $route     Route path.
     * @param array<string, mixed> $args      Route arguments.
     * @param bool                 $override  Whether to override existing routes.
     * @return bool
     */
    function register_rest_route(string $namespace, string $route, array $args = array(), bool $override = false): bool
    {
        $GLOBALS['__sr_test_routes'][] = array(
            'namespace' => $namespace,
            'route'     => $route,
            'args'      => $args,
            'override'  => $override,
        );

        return true;
    }
}

if (! function_exists('register_block_bindings_source')) {
    /**
     * Stub for register_block_bindings_source(): records the source for assertions.
     *
     * @param string               $name Source name.
     * @param array<string, mixed> $args Source arguments.
     * @return null Always null in the stub (no source object without WordPress).
     */
    function register_block_bindings_source(string $name, array $args): mixed
    {
        $GLOBALS['__sr_test_bindings'][ $name ] = $args;

        return null;
    }
}

if (! function_exists('wp_cache_get')) {
    /**
     * Stub for wp_cache_get(): reads the in-memory test cache (misses as false).
     *
     * @param string $key   Cache key.
     * @param string $group Cache group.
     * @return mixed
     */
    function wp_cache_get(string $key, string $group = ''): mixed
    {
        return $GLOBALS['__sr_test_cache'][ $group ][ $key ] ?? false;
    }
}

if (! function_exists('wp_cache_set')) {
    /**
     * Stub for wp_cache_set(): writes the in-memory test cache.
     *
     * @param string $key    Cache key.
     * @param mixed  $data   Cached value.
     * @param string $group  Cache group.
     * @param int    $expire Expiration in seconds (ignored by the stub).
     * @return bool
     */
    function wp_cache_set(string $key, mixed $data, string $group = '', int $expire = 0): bool
    {
        $GLOBALS['__sr_test_cache'][ $group ][ $key ] = $data;

        return true;
    }
}

if (! function_exists('wp_cache_delete')) {
    /**
     * Stub for wp_cache_delete(): removes a key from the in-memory test cache.
     *
     * @param string $key   Cache key.
     * @param string $group Cache group.
     * @return bool Whether the key existed.
     */
    function wp_cache_delete(string $key, string $group = ''): bool
    {
        if (! isset($GLOBALS['__sr_test_cache'][ $group ][ $key ])) {
            return false;
        }
        unset($GLOBALS['__sr_test_cache'][ $group ][ $key ]);

        return true;
    }
}

if (! function_exists('get_current_blog_id')) {
    /**
     * Stub for get_current_blog_id(): single-site default without WordPress.
     *
     * @return int
     */
    function get_current_blog_id(): int
    {
        return (int) ( $GLOBALS['__sr_test_flags']['blog_id'] ?? 1 );
    }
}

if (! function_exists('dbDelta')) {
    /**
     * Stub for dbDelta(): captures schema statements for migration assertions.
     *
     * @param string $queries CREATE TABLE statement.
     * @return list<string> Empty (no deltas computed without WordPress).
     */
    function dbDelta(string $queries): array
    {
        $GLOBALS['__sr_test_dbdelta'][] = $queries;

        return array();
    }
}

if (! isset($GLOBALS['__sr_test_menu_pages']) || ! is_array($GLOBALS['__sr_test_menu_pages'])) {
    $GLOBALS['__sr_test_menu_pages'] = array();
}

if (! isset($GLOBALS['__sr_test_redirects']) || ! is_array($GLOBALS['__sr_test_redirects'])) {
    $GLOBALS['__sr_test_redirects'] = array();
}

if (! function_exists('add_menu_page')) {
    /**
     * Stub for add_menu_page(): records the slug and returns a hook suffix.
     *
     * @param string   $pageTitle Page title.
     * @param string   $menuTitle Menu title.
     * @param string   $capability Capability.
     * @param string   $menuSlug Menu slug.
     * @param callable $callback Callback.
     * @return string Hook suffix.
     */
    function add_menu_page(
        string $pageTitle,
        string $menuTitle,
        string $capability,
        string $menuSlug,
        callable $callback
    ): string {
        $GLOBALS['__sr_test_menu_pages'][] = array(
            'type' => 'menu',
            'slug' => $menuSlug,
            'cap'  => $capability,
        );

        return 'toplevel_page_' . $menuSlug;
    }
}

if (! function_exists('add_submenu_page')) {
    /**
     * Stub for add_submenu_page(): records the slug and returns a hook suffix.
     *
     * @param string   $parent Parent slug.
     * @param string   $pageTitle Page title.
     * @param string   $menuTitle Menu title.
     * @param string   $capability Capability.
     * @param string   $menuSlug Menu slug.
     * @param callable $callback Callback.
     * @return string Hook suffix.
     */
    function add_submenu_page(
        string $parent,
        string $pageTitle,
        string $menuTitle,
        string $capability,
        string $menuSlug,
        callable $callback
    ): string {
        $GLOBALS['__sr_test_menu_pages'][] = array(
            'type'   => 'submenu',
            'parent' => $parent,
            'slug'   => $menuSlug,
            'cap'    => $capability,
        );

        return 'smooth_page_' . $menuSlug;
    }
}

if (! function_exists('get_current_screen')) {
    /**
     * Stub for get_current_screen(): id driven by the screen_id test flag.
     *
     * @return object|null
     */
    function get_current_screen(): mixed
    {
        $id = (string) ( $GLOBALS['__sr_test_flags']['screen_id'] ?? '' );
        if ('' === $id) {
            return null;
        }

        $screen     = new stdClass();
        $screen->id = $id;

        return $screen;
    }
}

if (! function_exists('check_admin_referer')) {
    /**
     * Stub for check_admin_referer(): pass/fail driven by the nonce_pass flag.
     *
     * @param string $action Nonce action.
     * @return bool
     */
    function check_admin_referer(string $action): bool
    {
        return (bool) ( $GLOBALS['__sr_test_flags']['nonce_pass'] ?? true );
    }
}

if (! function_exists('wp_unslash')) {
    /**
     * Stub for wp_unslash(): strips slashes.
     *
     * @param mixed $value Raw value.
     * @return mixed
     */
    function wp_unslash(mixed $value): mixed
    {
        return \is_string($value) ? \stripslashes($value) : $value;
    }
}

if (! function_exists('sanitize_text_field')) {
    /**
     * Stub for sanitize_text_field().
     *
     * @param string $value Raw value.
     * @return string
     */
    function sanitize_text_field(string $value): string
    {
        return trim((string) \preg_replace('/[\x00-\x1F\x7F]/', '', \strip_tags($value)));
    }
}

if (! function_exists('sanitize_title')) {
    /**
     * Stub for sanitize_title().
     *
     * @param string $value Raw value.
     * @return string
     */
    function sanitize_title(string $value): string
    {
        $slug = strtolower((string) \preg_replace('/[^a-z0-9]+/i', '-', $value));

        return trim($slug, '-');
    }
}

if (! function_exists('esc_html')) {
    /**
     * Stub for esc_html().
     *
     * @param string $text Raw text.
     * @return string
     */
    function esc_html(string $text): string
    {
        return \htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('esc_attr')) {
    /**
     * Stub for esc_attr().
     *
     * @param string $text Raw text.
     * @return string
     */
    function esc_attr(string $text): string
    {
        return \htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('esc_textarea')) {
    /**
     * Stub for esc_textarea().
     *
     * @param string $text Raw text.
     * @return string
     */
    function esc_textarea(string $text): string
    {
        return \htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('esc_url')) {
    /**
     * Stub for esc_url().
     *
     * @param string $url Raw URL.
     * @return string
     */
    function esc_url(string $url): string
    {
        return trim($url);
    }
}

if (! function_exists('admin_url')) {
    /**
     * Stub for admin_url().
     *
     * @param string $path Path.
     * @return string
     */
    function admin_url(string $path = ''): string
    {
        return 'http://example.test/wp-admin/' . \ltrim($path, '/');
    }
}

if (! function_exists('wp_safe_redirect')) {
    /**
     * Stub for wp_safe_redirect(): records the target instead of exiting.
     *
     * @param string $url Target URL.
     * @return void
     */
    function wp_safe_redirect(string $url): void
    {
        $GLOBALS['__sr_test_redirects'][] = $url;
    }
}

if (! function_exists('get_post')) {
    /**
     * Stub for get_post(): test posts driven by the test_posts flag.
     *
     * @param int|null $id Post id (null returns null in unit context).
     * @return object|null
     */
    function get_post(?int $id = null): mixed
    {
        if (null === $id) {
            return null;
        }
        $posts = $GLOBALS['__sr_test_flags']['test_posts'] ?? array();
        if (isset($posts[ $id ])) {
            $post                   = new stdClass();
            $post->ID               = $id;
            $post->post_type        = (string) ( $posts[ $id ]['post_type'] ?? 'post' );
            $post->post_mime_type   = (string) ( $posts[ $id ]['mime'] ?? '' );
            $post->post_author      = (int) ( $posts[ $id ]['author'] ?? 1 );
            return $post;
        }

        return null;
    }
}

if (! function_exists('wp_attachment_is_image')) {
    /**
     * Stub for wp_attachment_is_image().
     *
     * @param int $id Attachment id.
     * @return bool
     */
    function wp_attachment_is_image(int $id): bool
    {
        $posts = $GLOBALS['__sr_test_flags']['test_posts'] ?? array();

        return isset($posts[ $id ]) && 0 === \strpos((string) ( $posts[ $id ]['mime'] ?? '' ), 'image/');
    }
}
