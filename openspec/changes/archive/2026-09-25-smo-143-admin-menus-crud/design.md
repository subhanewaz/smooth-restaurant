## Context

`AdminProvider::registerMenu()` (`src/Providers/AdminProvider.php:72`) is an empty shell hooked via `admin_menu` in `boot()` with admin-context bail + `markBooted()`. Menu storage is done: `MenuProvider` binds `MenuRepositoryInterface`, `MenuItemRepositoryInterface`, `ModifierRepositoryInterface` as singletons and maps `smooth_manage_menus` → `manage_options` via `map_meta_cap`. REST (`MenuRoutes`, `MenuItemRoutes`, `ModifierRoutes` in `src/Domains/Menu/`) is the field-shape and validation reference (`api-docs/smooth-v1/menus/*`, `api-docs/openapi.json`). `AssetsProvider::isSmoothAdminScreen()` gates admin assets on `str_contains($screen->id, 'smooth')`. SMO-121 is Done; SMO-120 (Gutenberg editor, assignee Subha) is Todo and owns reorder/autosave/keyboard/RTL. Explore decisions locked: server-rendered PHP, simple custom table, full CRUD lite (no reorder), editable slug + server dedup. Adversarial review applied: per-action + id-bound nonces, IDOR parent checks, status allowlist, shared slug helper, all-statuses merge in screen, Domains placement.

## Goals / Non-Goals

**Goals:**
- Visible Smooth → Menus wp-admin surface for QA/dev verification without code, SQL, or curl.
- Menus list (paginate + search, publish + draft merged), create/edit (name, slug, description, status), delete with id-bound confirmation.
- Item lite CRUD under a menu and modifier lite CRUD under an item, via repository interfaces directly, with parent-ownership enforcement.
- Capability + per-action/id-bound nonce gating on every write, sanitization/escaping, unit-tested unauthorized rejection.
- REST read parity: wp-admin writes visible via `GET /smooth/v1/menus` and `GET /smooth/v1/menus/<id>` once all levels published.

**Non-Goals:**
- No Gutenberg features: no drag-reorder, bulk reorder UI, sort_order editor, autosave, revisions UI, keyboard/RTL editor work (SMO-120).
- No new REST endpoints; no admin-side REST consumption.
- No new tables, no postmeta, no CSV/AI import, no role seeding, no media-library picker, no bulk ops, no item search, no tree preview.
- No admin JS bundle unless gate-verified minimal CSS is needed; no editor tokens/webfonts; no `register_block_type`/Interactivity imports.

## Decisions

### 1. Server-rendered PHP via admin-post, hooks in boot() only
`add_menu_page` + `add_submenu_page` rendering plain PHP forms posting to `admin-post.php` actions (`smooth_restaurant_` prefixed), handlers resolving repository interfaces from the container. `boot()` adds `add_action('admin_menu', …)` **and** all `add_action('admin_post_…', …)` directly after the `isAdmin()` bail, then `markBooted()` — never nested inside `registerMenu()`, since `admin-post.php` never fires `admin_menu` reliably and nested registration escapes purity tests. Rejected JS-over-existing-REST: contradicts the "repositories directly" constraint and adds auth + bundle coupling. Post-redirect uses hard-coded `wp_safe_redirect()` + `exit`, never user-supplied `redirect_to`.

### 2. Simple custom table with merged statuses over WP_List_Table
List merges publish + draft (two `paginate()`/`listByMenu`/`listByItem` calls; no contract break — single-status `paginate($status)` stays as-is) into a hand-rendered `<table>` with search box + page links. Rejected `WP_List_Table`: heavier boilerplate and harder to unit-test. Rejected new `paginateAll` repo method: unnecessary signature change per `src/Contracts/AGENTS.md`. Page/per_page clamped 1–100 (default 20 stated on-screen); search via `sanitize_text_field` + `esc_like` semantics matching REST `LIKE name/description`; reflected search and page links escaped (`esc_html`, `esc_url(add_query_arg())`). Admin shows all statuses; REST stays publish-only — documented on-screen with a drafts-hidden hint.

### 3. Shared slug helper, sanitized editable slug
Form exposes slug; `wp_unslash` → `sanitize_title`; empty-after-sanitize falls back to `MenuRepository::generateSlug($name)`. Dedup extracted to a shared pure helper in `src/Domains/Menu/` (e.g. `MenuService::uniqueSlug(callable $findBySlug, string $base, int $ignoreId)`), rewiring `MenuRoutes::uniqueSlug()` and the admin screen to it — single source of truth including the `-2…-100` + `time()` fallback and self-ignore. Rejected copy-paste duplication: drifts on a UNIQUE-key invariant. Rename via admin re-derives slug with the same rule (custom slug overwritten on rename, matching `updateMenu`); duplicate-key race retried with next suffix.

### 4. Full CRUD lite with strict guards, explicitly no reorder
Items: name (required), description, price_cents (strict non-negative-int), image_id (`0` or verified `attachment` image via `get_post` + `wp_attachment_is_image`), status allowlist `publish|draft` only (invalid rejected, zero writes; missing defaults to `publish` on create only). No `sort_order` input rendered anywhere; create always appends at `maxSortOrder+1`, update never touches it — the fork guard against SMO-120 reorder. Modifiers: same minus image. `menu_id`/`item_id` immutable: handlers `findById` the parent, 404 on miss, enforce `item.menu_id == URL menu_id` and `modifier.item_id == URL item_id`, silently ignore forged parent fields (mirroring `updateItem`/`updateModifier`). Bulk reorder endpoints never surfaced.

### 5. Screen code placement: thin provider + Domains screen class
`AdminProvider::register()` binds the screen class as `singleton()` (bind-only); `registerMenu()` wires menu/submenu slugs containing `smooth` (captured hook suffixes as class consts for gate tests) and delegates to `src/Domains/Menu/MenuAdminScreen.php` resolved via `$container->make()`. Rejected `src/Admin/` dir: violates `folder-structure.md` and escapes purity/no-postmeta scans. `contexts()` stays `['admin']` mirroring the boot guard. New `admin_menu`/`admin_post_*` subscriptions get unconditional `HOOKS.md` rows with exact `smooth_restaurant_`-prefixed action names.

### 6. Delete cascade mirrors REST with verification
Menu delete and item delete loop both `publish` and `draft` children before deleting the parent, matching `deleteMenu()`/`deleteItem()`. Wrapped to fail closed: on partial failure verify `listByMenu`/`listByItem` empty after, with an orphan-assert unit test. Deletes are confirmed POST only with id-bound nonces (`smooth_menu_delete_{id}` etc.); GET-to-delete performs zero `delete()` calls.

### 7. Security: cap first, per-action + id-bound nonces, sanitize early / escape late
Every handler: `current_user_can(MenuProvider::MANAGE_CAP)` first, then `check_admin_referer($action)` (dies on failure — no re-render path). Distinct actions per op (`smooth_menu_save`, `smooth_item_save`, `smooth_modifier_save`) and id-bound delete nonces with id/nonce mismatch rejected. Inputs: `wp_unslash` first, then `sanitize_text_field`/`sanitize_title`/`absint`/status allowlist; outputs: `esc_html`/`esc_attr`/`esc_textarea`/`esc_url`. REST error-code parity mapped to admin notices (`smooth_menu_missing_name`, `invalid_price`, `invalid_image`, `not_found`).

## Risks / Trade-offs

- [Risk] Slug UNIQUE race under concurrency → Mitigation: unique DB key + catch duplicate-entry and retry next suffix; unit test collision/self-ignore/suffix + time fallback.
- [Risk] Menu slug misses `smooth` substring → assets silently missing → Mitigation: hook-suffix consts + stubbed `get_current_screen` test asserting `smooth_should_load()` SHALL be true.
- [Risk] Drafts invisible via public GET confuses QA → Mitigation: on-screen hint + parity tasks assert publish-all-levels tree and draft-excluded negative.
- [Risk] Nested forms grow into second editor → Mitigation: explicit forbidden list (media picker, sort editor, bulk ops, item search, tree preview, block imports) + grep gate in quality task; reviewer rejects additions.
- [Risk] No local Docker → live click-through unverifiable here → Mitigation: unit + wp-env/Playground verification on reviewer/CI; Bruno `01-list-menus,02-get-menu` after `Cache-Control: public, max-age=60` expiry/re-bust.
- [Risk] `price_cents`/`image_id`/search/page type-juggling → Mitigation: strict non-negative-int, attachment-image check, absint/clamp + esc_like/esc_html/esc_url; invalid → form error, zero writes.

## Migration Plan

No migrations, no options, no capability seeding. Deploy: plugin update adds menu wiring. Rollback: revert change removes Smooth menu; tables and REST untouched.
