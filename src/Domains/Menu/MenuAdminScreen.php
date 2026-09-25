<?php

/**
 * Menu admin screen (stepping-stone QA surface).
 *
 * Server-rendered wp-admin UI for menus, items, and modifiers built on the
 * repository contracts. No new REST endpoints, no raw SQL, no postmeta.
 * Security: capability + per-action/id-bound nonces, sanitize early and
 * escape late. Scope guard: no ordering editor, no background saves, no
 * block registration, no media selection UI, no mass actions, no previews.
 *
 * @package SmoothRestaurant
 */

declare(strict_types=1);

namespace SmoothRestaurant\Domains\Menu;

use SmoothRestaurant\Contracts\MenuItemRepositoryInterface;
use SmoothRestaurant\Contracts\MenuRepositoryInterface;
use SmoothRestaurant\Contracts\ModifierRepositoryInterface;
use SmoothRestaurant\Database\Repositories\MenuRepository;

/**
 * Class MenuAdminScreen
 */
final class MenuAdminScreen
{
    /**
     * Top-level menu slug (contains smooth so the asset gate passes).
     */
    public const MENU_SLUG = 'smooth';

    /**
     * Menus submenu slug.
     */
    public const PAGE_SLUG = 'smooth-menus';

    /**
     * Hook suffix for the top-level page (filled at registration).
     */
    public const TOP_HOOK = 'toplevel_page_smooth';

    /**
     * Hook suffix for the menus page (filled at registration).
     */
    public const MENUS_HOOK = 'smooth_page_smooth-menus';

    /**
     * Nonce action for menu saves.
     */
    public const NONCE_MENU_SAVE = 'smooth_menu_save';

    /**
     * Nonce action for item saves.
     */
    public const NONCE_ITEM_SAVE = 'smooth_item_save';

    /**
     * Nonce action for modifier saves.
     */
    public const NONCE_MODIFIER_SAVE = 'smooth_modifier_save';

    /**
     * Nonce action prefix for menu deletes (append the id).
     */
    public const NONCE_MENU_DELETE = 'smooth_menu_delete_';

    /**
     * Nonce action prefix for item deletes (append the id).
     */
    public const NONCE_ITEM_DELETE = 'smooth_item_delete_';

    /**
     * Nonce action prefix for modifier deletes (append the id).
     */
    public const NONCE_MODIFIER_DELETE = 'smooth_modifier_delete_';

    /**
     * Default rows per page stated on-screen.
     */
    public const PER_PAGE_DEFAULT = 20;

    /**
     * Constructor.
     *
     * @param MenuRepositoryInterface     $menus     Menus table repository.
     * @param MenuItemRepositoryInterface $items     Menu items table repository.
     * @param ModifierRepositoryInterface $modifiers Modifiers table repository.
     */
    public function __construct(
        private MenuRepositoryInterface $menus,
        private MenuItemRepositoryInterface $items,
        private ModifierRepositoryInterface $modifiers
    ) {
    }

    /**
     * Whether the current user may manage menus.
     *
     * Unit context without WordPress allows (mirrors RestProvider) so pure
     * validation stays testable; production enforces the capability.
     * Capability is a parameter (not a literal) so Domains stays decoupled
     * from the provider that owns the cap string.
     *
     * @param string $cap Required capability.
     * @return bool
     */
    public function canManage(string $cap): bool
    {
        if (! \function_exists('current_user_can')) {
            return true;
        }

        return \current_user_can($cap);
    }

    /**
     * Verify a nonce action without dying in unit context.
     *
     * Production calls check_admin_referer() with its default die behavior
     * (failure halts); the unit stub returns a controllable flag instead.
     *
     * @param string $action Nonce action.
     * @return bool
     */
    public function verifyNonce(string $action): bool
    {
        if (! \function_exists('check_admin_referer')) {
            return true;
        }

        $result = \check_admin_referer($action);

        return false !== $result;
    }

    /**
     * Clamp a page number to >= 1.
     *
     * @param mixed $page Raw page input.
     * @return int
     */
    public static function clampPage(mixed $page): int
    {
        $value = \is_numeric($page) ? (int) $page : 1;

        return max(1, $value);
    }

    /**
     * Clamp per-page to 1–100.
     *
     * @param mixed $perPage Raw per-page input.
     * @return int
     */
    public static function clampPerPage(mixed $perPage): int
    {
        $value = \is_numeric($perPage) ? (int) $perPage : self::PER_PAGE_DEFAULT;

        return min(100, max(1, $value));
    }

    /**
     * Sanitize a search string (unslash then strip).
     *
     * @param mixed $search Raw search input.
     * @return string
     */
    public static function sanitizeSearch(mixed $search): string
    {
        $value = \is_string($search) ? $search : '';
        if (\function_exists('wp_unslash')) {
            $unslashed = \wp_unslash($value);
            $value     = \is_string($unslashed) ? $unslashed : $value;
        }
        if (\function_exists('sanitize_text_field')) {
            return \sanitize_text_field($value);
        }

        return trim((string) \preg_replace('/[\x00-\x1F\x7F]/', '', $value));
    }

    /**
     * Sanitize a plain text field (unslash then sanitize).
     *
     * @param mixed $value Raw input.
     * @return string
     */
    public static function sanitizeText(mixed $value): string
    {
        $text = \is_string($value) ? $value : '';
        if (\function_exists('wp_unslash')) {
            $unslashed = \wp_unslash($text);
            $text      = \is_string($unslashed) ? $unslashed : $text;
        }
        if (\function_exists('sanitize_text_field')) {
            return \sanitize_text_field($text);
        }

        return trim($text);
    }

    /**
     * Sanitize a slug (unslash then title-sanitize).
     *
     * @param mixed $value Raw input.
     * @return string
     */
    public static function sanitizeSlug(mixed $value): string
    {
        $text = \is_string($value) ? $value : '';
        if (\function_exists('wp_unslash')) {
            $unslashed = \wp_unslash($text);
            $text      = \is_string($unslashed) ? $unslashed : $text;
        }
        if (\function_exists('sanitize_title')) {
            return \sanitize_title($text);
        }

        $slug = strtolower((string) \preg_replace('/[^a-z0-9]+/i', '-', $text));

        return trim($slug, '-');
    }

    /**
     * Normalize a status input.
     *
     * Missing status defaults to publish on create only; invalid values are
     * rejected (null) so callers perform zero writes.
     *
     * @param mixed $status    Raw status input.
     * @param bool  $isCreate Whether this is a create (default applies).
     * @return string publish|draft, or empty string when invalid/missing-on-update.
     */
    public static function normalizeStatus(mixed $status, bool $isCreate): string
    {
        if (null === $status || '' === $status) {
            return $isCreate ? 'publish' : '';
        }
        if ('publish' === $status || 'draft' === $status) {
            return $status;
        }

        return '';
    }

    /**
     * Strict non-negative-int check (ints and integer strings only).
     *
     * @param mixed $raw Raw input.
     * @return array{present: bool, value: int, valid: bool}
     */
    public static function nonNegativeInt(mixed $raw, bool $present): array
    {
        if (! $present) {
            return array( 'present' => false, 'value' => 0, 'valid' => true );
        }
        if (\is_int($raw)) {
            $value = $raw;
        } elseif (\is_string($raw) && '' !== $raw && false !== \filter_var($raw, FILTER_VALIDATE_INT)) {
            $value = (int) $raw;
        } elseif (\is_float($raw) && (float) (int) $raw === $raw) {
            $value = (int) $raw;
        } else {
            return array( 'present' => true, 'value' => 0, 'valid' => false );
        }
        if ($value < 0) {
            return array( 'present' => true, 'value' => 0, 'valid' => false );
        }

        return array( 'present' => true, 'value' => $value, 'valid' => true );
    }

    /**
     * List menus for the admin screen, merging publish + draft.
     *
     * No contract break: two single-status paginate() calls merged in
     * display order. Page links carry the clamped values.
     *
     * @param mixed $page    Raw page input.
     * @param mixed $perPage Raw per-page input.
     * @param mixed $search  Raw search input.
     * @return array{data: list<array<string, mixed>>, meta: array{page: int, per_page: int, search: string}}
     */
    public function listMenus(mixed $page, mixed $perPage, mixed $search): array
    {
        $pageNum    = self::clampPage($page);
        $perPageNum = self::clampPerPage($perPage);
        $term       = self::sanitizeSearch($search);

        $half       = (int) ceil($perPageNum / 2);
        $published  = $this->menus->paginate($pageNum, $half, $term, 'publish');
        $drafts     = $this->menus->paginate($pageNum, $half, $term, 'draft');
        $merged     = \array_merge($published, $drafts);
        $merged     = \array_slice($merged, 0, $perPageNum);

        return array(
            'data' => $merged,
            'meta' => array(
                'page'     => $pageNum,
                'per_page' => $perPageNum,
                'search'   => $term,
            ),
        );
    }

    /**
     * Load a menu edit tree, merging publish + draft children.
     *
     * @param int $menuId Menu row id.
     * @return array{menu: array<string, mixed>, items: list<array{item: array<string, mixed>,
     *     modifiers: list<array<string, mixed>>}>}|null
     */
    public function editTree(int $menuId): ?array
    {
        $menu = $menuId > 0 ? $this->menus->findById($menuId) : null;
        if (null === $menu) {
            return null;
        }

        $items     = \array_merge(
            $this->items->listByMenu($menuId, 'publish'),
            $this->items->listByMenu($menuId, 'draft')
        );
        $modifiers = array();
        foreach ($items as $item) {
            $itemId = (int) ($item['id'] ?? 0);
            foreach ($this->modifiers->listByItem($itemId, 'publish') as $modifier) {
                $modifiers[] = $modifier;
            }
            foreach ($this->modifiers->listByItem($itemId, 'draft') as $modifier) {
                $modifiers[] = $modifier;
            }
        }

        return MenuService::assemble($menu, $items, $modifiers);
    }

    /**
     * Validate menu input.
     *
     * Missing status defaults to publish on create; on update a missing
     * status means "no change" and stays valid. Present-but-invalid status
     * is rejected with zero writes.
     *
     * @param array<string, mixed> $input    Raw input (name, slug, description, status).
     * @param bool                 $isCreate Whether this is a create.
     * @param int                  $ignoreId Row id allowed to keep its slug.
     * @return array{valid: bool, data: array<string, mixed>, error: string}
     */
    public function validateMenu(array $input, bool $isCreate, int $ignoreId = 0): array
    {
        $name = self::sanitizeText($input['name'] ?? '');
        if ('' === $name) {
            return array( 'valid' => false, 'data' => array(), 'error' => 'smooth_menu_missing_name' );
        }

        $hasStatus = \array_key_exists('status', $input);
        $status    = self::normalizeStatus($input['status'] ?? null, $isCreate);
        if ($hasStatus && '' === $status) {
            return array( 'valid' => false, 'data' => array(), 'error' => 'smooth_menu_invalid_status' );
        }

        $slugRaw = self::sanitizeSlug((string) ($input['slug'] ?? ''));
        $base    = '' !== $slugRaw ? $slugRaw : MenuRepository::generateSlug($name);
        $slug    = MenuService::uniqueSlug(
            function (string $candidate): ?array {
                $row = $this->menus->findBySlug($candidate);

                return \is_array($row) ? $row : null;
            },
            $base,
            $ignoreId
        );

        $description = self::sanitizeText($input['description'] ?? '');

        $data = array(
            'name'        => $name,
            'slug'        => $slug,
            'description' => $description,
        );
        if ('' !== $status) {
            $data['status'] = $status;
        }
        if ($isCreate) {
            if (! isset($data['status'])) {
                $data['status'] = 'publish';
            }
            $data['sort_order'] = 0;
        }

        return array( 'valid' => true, 'data' => $data, 'error' => '' );
    }

    /**
     * Validate item input.
     *
     * @param array<string, mixed> $input    Raw input.
     * @param bool                 $isCreate Whether this is a create.
     * @return array{valid: bool, data: array<string, mixed>, error: string}
     */
    public function validateItem(array $input, bool $isCreate): array
    {
        $name = self::sanitizeText($input['name'] ?? '');
        if ('' === $name) {
            return array( 'valid' => false, 'data' => array(), 'error' => 'smooth_menu_item_missing_name' );
        }

        $hasStatus = \array_key_exists('status', $input);
        $status    = self::normalizeStatus($input['status'] ?? null, $isCreate);
        if ($hasStatus && '' === $status) {
            return array( 'valid' => false, 'data' => array(), 'error' => 'smooth_menu_item_invalid_status' );
        }

        $price = self::nonNegativeInt($input['price_cents'] ?? null, isset($input['price_cents']));
        if (! $price['valid']) {
            return array( 'valid' => false, 'data' => array(), 'error' => 'smooth_menu_item_invalid_price' );
        }

        $image = self::nonNegativeInt($input['image_id'] ?? null, isset($input['image_id']));
        if (! $image['valid']) {
            return array( 'valid' => false, 'data' => array(), 'error' => 'smooth_menu_item_invalid_image' );
        }
        if ($image['value'] > 0 && ! $this->isValidImage($image['value'])) {
            return array( 'valid' => false, 'data' => array(), 'error' => 'smooth_menu_item_invalid_image' );
        }

        $data = array( 'name' => $name );
        if ('' !== $status) {
            $data['status'] = $status;
        } elseif ($isCreate) {
            $data['status'] = 'publish';
        }
        if (isset($input['description'])) {
            $data['description'] = self::sanitizeText($input['description']);
        }
        if ($price['present']) {
            $data['price_cents'] = $price['value'];
        } elseif ($isCreate) {
            $data['price_cents'] = 0;
        }
        if ($image['present']) {
            $data['image_id'] = $image['value'];
        } elseif ($isCreate) {
            $data['image_id'] = 0;
        }

        return array( 'valid' => true, 'data' => $data, 'error' => '' );
    }

    /**
     * Validate modifier input.
     *
     * @param array<string, mixed> $input    Raw input.
     * @param bool                 $isCreate Whether this is a create.
     * @return array{valid: bool, data: array<string, mixed>, error: string}
     */
    public function validateModifier(array $input, bool $isCreate): array
    {
        $name = self::sanitizeText($input['name'] ?? '');
        if ('' === $name) {
            return array( 'valid' => false, 'data' => array(), 'error' => 'smooth_modifier_missing_name' );
        }

        $hasStatus = \array_key_exists('status', $input);
        $status    = self::normalizeStatus($input['status'] ?? null, $isCreate);
        if ($hasStatus && '' === $status) {
            return array( 'valid' => false, 'data' => array(), 'error' => 'smooth_modifier_invalid_status' );
        }

        $price = self::nonNegativeInt($input['price_cents'] ?? null, isset($input['price_cents']));
        if (! $price['valid']) {
            return array( 'valid' => false, 'data' => array(), 'error' => 'smooth_modifier_invalid_price' );
        }

        $data = array( 'name' => $name );
        if ('' !== $status) {
            $data['status'] = $status;
        } elseif ($isCreate) {
            $data['status'] = 'publish';
        }
        if ($price['present']) {
            $data['price_cents'] = $price['value'];
        } elseif ($isCreate) {
            $data['price_cents'] = 0;
        }

        return array( 'valid' => true, 'data' => $data, 'error' => '' );
    }

    /**
     * Whether an attachment id is a usable image.
     *
     * @param int $attachmentId Attachment id.
     * @return bool
     */
    private function isValidImage(int $attachmentId): bool
    {
        if (! \function_exists('get_post')) {
            return true;
        }
        $post = \get_post($attachmentId);
        if (null === $post || false === $post) {
            return false;
        }
        if (\function_exists('wp_attachment_is_image')) {
            return \wp_attachment_is_image($attachmentId);
        }
        $mime = '';
        if (\is_object($post)) {
            $mime = (string) $post->post_mime_type;
        } elseif (\is_array($post) && isset($post['post_mime_type'])) {
            $mime = (string) $post['post_mime_type'];
        }

        return '' !== $mime && 0 === \strpos($mime, 'image/');
    }

    /**
     * Whether an item belongs to a menu.
     *
     * @param int $itemId Item id.
     * @param int $menuId Menu id.
     * @return bool
     */
    public function ownsItem(int $itemId, int $menuId): bool
    {
        $item = $itemId > 0 ? $this->items->findById($itemId) : null;
        if (null === $item) {
            return false;
        }

        return (int) ($item['menu_id'] ?? 0) === $menuId;
    }

    /**
     * Whether a modifier belongs to an item.
     *
     * @param int $modifierId Modifier id.
     * @param int $itemId     Item id.
     * @return bool
     */
    public function ownsModifier(int $modifierId, int $itemId): bool
    {
        $modifier = $modifierId > 0 ? $this->modifiers->findById($modifierId) : null;
        if (null === $modifier) {
            return false;
        }

        return (int) ($modifier['item_id'] ?? 0) === $itemId;
    }

    /**
     * Delete a menu tree with verification.
     *
     * @param int $menuId Menu id.
     * @return bool True when the tree is gone.
     */
    public function deleteMenuCascade(int $menuId): bool
    {
        $menu = $menuId > 0 ? $this->menus->findById($menuId) : null;
        if (null === $menu) {
            return false;
        }

        foreach (array( 'publish', 'draft' ) as $status) {
            foreach ($this->items->listByMenu($menuId, $status) as $item) {
                $itemId = (int) ($item['id'] ?? 0);
                $this->deleteItemCascade($itemId);
            }
        }
        $this->menus->delete($menuId);

        foreach (array( 'publish', 'draft' ) as $status) {
            if ([] !== $this->items->listByMenu($menuId, $status)) {
                return false;
            }
        }

        return null === $this->menus->findById($menuId);
    }

    /**
     * Delete an item with its modifiers and verification.
     *
     * @param int $itemId Item id.
     * @return bool True when the item and modifiers are gone.
     */
    public function deleteItemCascade(int $itemId): bool
    {
        $item = $itemId > 0 ? $this->items->findById($itemId) : null;
        if (null === $item) {
            return false;
        }

        foreach (array( 'publish', 'draft' ) as $status) {
            foreach ($this->modifiers->listByItem($itemId, $status) as $modifier) {
                $this->modifiers->delete((int) ($modifier['id'] ?? 0));
            }
        }
        $this->items->delete($itemId);

        foreach (array( 'publish', 'draft' ) as $status) {
            if ([] !== $this->modifiers->listByItem($itemId, $status)) {
                return false;
            }
        }

        return null === $this->items->findById($itemId);
    }

    /**
     * Escape for HTML output (guarded for unit context).
     *
     * @param string $text Raw text.
     * @return string
     */
    public static function esc(string $text): string
    {
        if (\function_exists('esc_html')) {
            return \esc_html($text);
        }

        return \htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
