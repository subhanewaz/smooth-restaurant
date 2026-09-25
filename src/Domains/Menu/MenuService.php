<?php

/**
 * Menu domain service.
 *
 * Pure menu domain logic: tree assembly, price formatting, and per-request
 * memoization keys. Stays dependency-free (no raw DB access, no WP globals) so
 * providers can bind it in register() and DomainPurityTest stays green —
 * callers pass rows in and take trees out.
 *
 * @package SmoothRestaurant
 */

declare(strict_types=1);

namespace SmoothRestaurant\Domains\Menu;

/**
 * Class MenuService
 */
final class MenuService
{
    /**
     * Per-request memoized values, keyed by cacheKey().
     *
     * The instance is a container singleton, so the memo lives exactly one
     * request. Persistent object cache (with version keys and a stampede
     * guard) is an explicit follow-up, not this change.
     *
     * @var array<string, mixed>
     */
    private array $memo = [];

    /**
     * Build a pure cache key for a derived menu value.
     *
     * Pure string template: the caller supplies the blog id so multisite
     * namespacing never depends on a global inside the domain.
     *
     * @param int    $blogId Blog id (multisite namespace).
     * @param string $scope  Value scope (e.g. 'binding', 'tree').
     * @param int    $id     Owner row id.
     * @param string $key    Field key.
     * @return string Namespaced cache key.
     */
    public static function cacheKey(int $blogId, string $scope, int $id, string $key): string
    {
        return sprintf('smooth:%d:%s:%d:%s', $blogId, $scope, $id, $key);
    }

    /**
     * Return the memoized value for a key, computing it once per request.
     *
     * @param callable(): mixed $compute Value factory (runs at most once per key).
     * @return mixed The memoized value.
     */
    public function remember(string $key, callable $compute): mixed
    {
        if (! array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $compute();
        }

        return $this->memo[$key];
    }

    /**
     * Clear the per-request memo (tests and long-running contexts).
     *
     * @return void
     */
    public function flush(): void
    {
        $this->memo = [];
    }

    /**
     * Find a free slug, suffixing (-2, -3, …) past collisions.
     *
     * Pure helper shared by REST and wp-admin: the caller supplies a
     * find-by-slug lookup so uniqueness lives in one place. Mirrors the
     * historic MenuRoutes::uniqueSlug() contract, including the 100-suffix
     * cap with a time() fallback.
     *
     * @param callable(string): (array<string, mixed>|null) $findBySlug Slug lookup returning a row or null.
     * @param string $base Base slug.
     * @param int $ignoreId Row id allowed to keep its own slug (updates).
     * @return string Free slug.
     */
    public static function uniqueSlug(callable $findBySlug, string $base, int $ignoreId = 0): string
    {
        $slug   = $base;
        $suffix = 2;
        while (true) {
            $existing = $findBySlug($slug);
            if (null === $existing || (int) ($existing['id'] ?? 0) === $ignoreId) {
                return $slug;
            }
            $slug = $base . '-' . $suffix;
            $suffix++;
            if ($suffix > 100) {
                return $base . '-' . time();
            }
        }
    }
    /**
     * Format a cents price for display.
     *
     * Display-only: checkout totals always read live price_cents at
     * calculation time, never a formatted string.
     *
     * @param int $cents Price in minor units.
     * @return string Decimal string with two places (e.g. "9.50").
     */
    public static function formatPrice(int $cents): string
    {
        return number_format($cents / 100, 2, '.', ',');
    }

    /**
     * Assemble a menu tree from flat repository rows.
     *
     * @param array<string, mixed>       $menu      Menu row.
     * @param list<array<string, mixed>> $items     Item rows for the menu.
     * @param list<array<string, mixed>> $modifiers Modifier rows (flat, with item_id).
     * @return array{menu: array<string, mixed>, items: list<array{item: array<string, mixed>,
     *     modifiers: list<array<string, mixed>>}>} Menu tree.
     */
    public static function assemble(array $menu, array $items, array $modifiers): array
    {
        $grouped = [];
        foreach ($modifiers as $modifier) {
            $itemId = (int) ($modifier['item_id'] ?? 0);
            $grouped[$itemId][] = $modifier;
        }

        $tree = [];
        foreach ($items as $item) {
            $itemId = (int) ($item['id'] ?? 0);
            $tree[] = [
                'item' => $item,
                'modifiers' => $grouped[$itemId] ?? [],
            ];
        }

        return [
            'menu' => $menu,
            'items' => $tree,
        ];
    }
}
