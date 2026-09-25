## Why

SMO-121 shipped custom tables, repositories, and public REST reads, but there is no visible wp-admin surface to verify them without code, SQL, or curl. SMO-143 gives owners and QA a minimal Smooth → Menus screen now, before the Gutenberg authoring UI (SMO-120) lands.

## What Changes

- Add top-level "Smooth" admin menu (`add_menu_page`) gated by `smooth_manage_menus`, plus a Menus submenu page in `AdminProvider::registerMenu()`.
- Menus screen: paginated + searchable list via `MenuRepositoryInterface::paginate()`, create/edit form (name, slug, description, status), delete with two-step confirmation.
- Item entry under a menu (add/edit/remove): name, description, price_cents, image_id, status; append via `maxSortOrderForMenu()` when sort_order omitted.
- Modifier entry under an item (add/edit/remove): name, price_cents, status; append via `maxSortOrderForItem()` when omitted. No reorder UI, no autosave, no drag-drop.
- Server-rendered PHP via `admin-post.php` handlers using repository interfaces directly; no new REST endpoints, no raw SQL outside `src/Database/`.
- Every state-changing action requires `current_user_can(smooth_manage_menus)` + nonce (`check_admin_referer`); unauthorized requests rejected with no writes.
- Field shapes follow `api-docs/smooth-v1/menus/*`; data created in wp-admin is visible via `GET /smooth/v1/menus` and `GET /smooth/v1/menus/<id>`.
- Admin assets load only on Smooth screens via the existing `AssetsProvider` gate (menu slug contains `smooth`).

## Capabilities

### New Capabilities

- `admin-menus-crud`: Smooth admin shell, Menus list/create/edit/delete, nested item and modifier lite CRUD via repositories, capability + nonce gating, sanitization/escaping, REST read parity, stepping-stone scope guard (no editor features).

### Modified Capabilities

- None. Uses existing `menu-bindings-rest` (repositories, capability map, REST schemas) and `provider-architecture` (register/boot split, asset gate) without changing their requirements.

## Impact

- Touched: `src/Providers/AdminProvider.php` (menu wiring + screen handlers or delegated screen class), `src/Providers/AssetsProvider.php` gate already covers new screens (no change unless slug misses `smooth`), `HOOKS.md` if new `smooth_restaurant_` hooks added.
- Uses: `MenuRepositoryInterface`, `MenuItemRepositoryInterface`, `ModifierRepositoryInterface`, `MenuProvider::MANAGE_CAP`, `api-docs/smooth-v1/menus/*` as field reference.
- Not touched: REST routes (reference only), custom tables/migrations (no new tables), postmeta (none), Gutenberg blocks (SMO-120 owns), CSV/AI import (explicitly out).
- Tests: new unit coverage for capability, nonce, sanitization, slug-dedup, delete cascade; live parity verified via `GET /smooth/v1/menus`.
