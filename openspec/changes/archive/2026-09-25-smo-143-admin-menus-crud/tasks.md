## 1. Admin shell wiring

- [x] 1.1 Bind screen class as `singleton()` in `AdminProvider::register()` (bind-only); in `boot()` after `isAdmin()` bail add `admin_menu` plus all `admin_post_smooth_restaurant_*` actions directly, then `markBooted()`; menu/submenu gated by `MenuProvider::MANAGE_CAP` with slugs containing `smooth`; LF endings, PHP 8.2, `declare(strict_types=1)`
- [x] 1.2 Create `src/Domains/Menu/MenuAdminScreen.php` (WP calls `function_exists`-guarded) resolved via `$container->make()`; capture `add_menu_page`/`add_submenu_page` hook suffixes as class consts
- [x] 1.3 Add unconditional `HOOKS.md` subscription rows for `admin_menu → AdminProvider::registerMenu` and each `admin_post_smooth_restaurant_*` with exact `smooth_restaurant_`-prefixed action names

## 2. Menus list + create/edit/delete

- [x] 2.1a Build Menus list by merging publish + draft `paginate()` calls (no contract change), default per_page 20 clamped 1–100, `sanitize_text_field` + `esc_like` search, escaped output (`esc_html`, `esc_url(add_query_arg())`); unit test page 0/per_page 9999/search `<script>`/`100%`
- [x] 2.1b Merge publish + draft `listByMenu`/`listByItem` for the edit-screen trees; unit test mixed-status visibility
- [x] 2.2 Build menu create/edit form (name required, slug editable, description, status allowlist) posting to `admin-post.php` with per-action nonce; `wp_unslash` then sanitize, escape late; hard-coded `wp_safe_redirect()` + `exit`
- [x] 2.3 Extract shared pure slug helper in `src/Domains/Menu/` (e.g. `MenuService::uniqueSlug`), rewire `MenuRoutes::uniqueSlug()` + admin to it (covers `-2…-100` + `time()` fallback, self-ignore); unit test collision/self-ignore/suffix/race-retry
- [x] 2.4 Implement menu delete via id-bound nonce `smooth_menu_delete_{id}` confirmed POST + full cascade (publish+draft items → publish+draft modifiers → menu) with post-delete empty verification, mirroring `MenuRoutes::deleteMenu()`; GET performs zero deletes

## 3. Item lite CRUD

- [x] 3.1 Add item add/edit/remove under a verified parent menu (`findById`, 404 on miss; enforce `item.menu_id == URL menu_id`; ignore forged `menu_id`); never render `sort_order`, create always `maxSortOrderForMenu()+1`
- [x] 3.2 Validate item inputs (required name → `smooth_menu_missing_name`; strict non-negative-int price/image → `invalid_price`/`invalid_image`; status allowlist reject, default `publish` on create only; image_id `0` or verified attachment image); invalid → error notice, zero writes
- [x] 3.3 Implement item delete via id-bound nonce POST with modifier cascade across both statuses + empty verification, mirroring `MenuItemRoutes::deleteItem()`; GET performs zero deletes

## 4. Modifier lite CRUD

- [x] 4.1 Add modifier add/edit/remove under a verified parent item (`findById`, 404 on miss; enforce `modifier.item_id == URL item_id`; ignore forged `item_id`); never render `sort_order`, create always `maxSortOrderForItem()+1`; no reorder/autosave UI
- [x] 4.2 Validate modifier inputs (required name, strict non-negative price, status allowlist reject, default `publish` on create only); invalid → error notice, zero writes; GET-delete performs zero deletes

## 5. Security, parity, and gates

- [x] 5.1 Gate every write with `current_user_can(MANAGE_CAP)` first then `check_admin_referer($per_action_or_id_bound)` (dies on failure); distinct `smooth_menu_save`/`smooth_item_save`/`smooth_modifier_save` + id-bound deletes; id/nonce mismatch → zero writes
- [x] 5.2a Unit tests: cap-denial and nonce-reject per op (zero writes)
- [x] 5.2b Unit tests: sanitization (`wp_unslash` quote, `sanitize_title` slug, search escaping), slug trio (collision/self-ignore/suffix + time fallback), `smooth` slug gate + `smooth_should_load()` SHALL-true with stubbed screen
- [x] 5.2c Unit tests: delete cascades (menu tree, item modifiers) with orphan asserts; forged-parent ignored for items and modifiers
- [x] 5.2d Architecture tests: `RegisterPurityTest` (no hooks in `register()`), boot-discipline (bail first), `BootMatrix`/`ContextFilter` admin matrix unchanged, `DomainPurity`/`NoPostmeta` for new screen path; extend `tests/Support/WpStubs.php` (`add_menu_page`, `admin_post`, `get_current_screen`, `check_admin_referer`) with `function_exists` guards
- [ ] 5.3 Verify REST parity via wp-env/Playground: publish menu + item + modifier in wp-admin, run Bruno `01-list-menus,02-get-menu` (app-password, after `Cache-Control: public, max-age=60` expiry/re-bust); assert draft menu and draft child excluded; on-screen drafts-hidden hint
  - Static parity audit (2026-09-25, interim): admin validators cover all contract fields — menus (name/slug/description/status), items (+price_cents/image_id), modifiers (+price_cents); defaults match (status publish, price/image 0, append via max+1); error codes match REST (`smooth_menu_missing_name`, `smooth_menu_item_missing_name/invalid_price/invalid_image`, `smooth_modifier_missing_name/invalid_price`, `not_found` family); slug dedup shares `MenuService::uniqueSlug()` with REST. Intentional divergences only: (a) admin exposes editable slug, rename re-derives identically to `updateMenu`; (b) admin never sends `sort_order`, matching REST's omitted-means-append; (c) admin list merges publish+draft default 20 vs REST publish-only default 10 (hint shown on-screen). Live Bruno check deferred (no Docker locally).
- [x] 5.4 Run `composer quality` green (PHPCS, PHPStan L8, units, arch incl. no-postmeta/`$wpdb`-in-Database-only) plus scope grep gate (zero refs to `reorderItems`/`reorderModifiers`, `autosave`, `register_block_type`, media-picker, bulk ops in screen path); confirm no new tables, no postmeta
- [x] 5.5 Validate change: `openspec validate smo-143-admin-menus-crud`
