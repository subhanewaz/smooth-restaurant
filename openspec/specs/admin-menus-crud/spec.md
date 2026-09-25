# admin-menus-crud Specification

## Purpose
Provide a minimal Smooth → Menus wp-admin surface so owners and QA can verify custom tables, repositories, and public REST reads without code, SQL, or curl, ahead of the Gutenberg authoring UI (SMO-120). Covers admin shell wiring, menu/item/modifier lite CRUD via repository interfaces, capability + nonce gating, sanitization/escaping, and REST read parity — explicitly excluding editor features (block authoring, reorder, autosave, bulk ops).

## Requirements

### Requirement: Smooth admin shell gated by menu capability
The system SHALL register a top-level "Smooth" admin menu plus a Menus submenu via `AdminProvider::registerMenu()`, both gated by `smooth_manage_menus` (mapped to `manage_options`), with menu slugs containing `smooth` and captured hook suffixes as class consts. `AdminProvider::register()` SHALL bind the screen class as singleton (bind-only); `boot()` SHALL add `admin_menu` plus all `admin_post_smooth_restaurant_*` actions directly after the `isAdmin()` bail, then `markBooted()`; `contexts()` stays `['admin']`.

#### Scenario: Admin sees Smooth Menus
- **WHEN** an admin with `smooth_manage_menus` opens wp-admin
- **THEN** a "Smooth → Menus" entry is visible with HTTP 200 and no PHP fatal or notice

#### Scenario: Unauthorized user sees nothing
- **WHEN** a user without `smooth_manage_menus` opens wp-admin
- **THEN** no Smooth menu is registered for them

#### Scenario: Asset gate passes on Smooth screens
- **WHEN** the Menus screen loads with a stubbed Smooth screen id
- **THEN** `smooth_should_load()` SHALL return true

#### Scenario: Hooks registered in boot only
- **WHEN** the provider boots in admin context
- **THEN** `has_action('admin_menu')` and each `has_action('admin_post_smooth_restaurant_*')` are true, and false outside admin context

### Requirement: Menus list with pagination and search merged across statuses
The system SHALL list menus by merging publish + draft via two `MenuRepositoryInterface::paginate()` calls (no contract break), with page/per_page clamped 1–100 (default 20 stated on-screen), search sanitized via `sanitize_text_field` with `esc_like` semantics matching REST `LIKE name/description`, and reflected search + page links escaped.

#### Scenario: Paginated QA scan
- **WHEN** an admin opens Menus with 25 menus at 20 per page
- **THEN** page 1 shows 20 rows with page links and page 2 shows the remainder

#### Scenario: Search narrows rows literally
- **WHEN** an admin searches "lunch" or literal "100%"
- **THEN** only menus whose name or description contains that literal are listed

#### Scenario: Out-of-range paging is safe
- **WHEN** an admin requests page 0, per_page 9999, or search `<script>`
- **THEN** values clamp to range, no SQL error occurs, and reflected output is escaped

#### Scenario: Mixed statuses visible in admin
- **WHEN** menus exist in both publish and draft
- **THEN** the admin list shows both while public REST reads stay publish-only (hint shown on-screen)

### Requirement: Menu create and edit with shared slug helper
The system SHALL provide create/edit forms (name required; slug editable; description; status allowlist `publish|draft` only) using `wp_unslash` then `sanitize_text_field`/`sanitize_title`, falling back to `MenuRepository::generateSlug(name)` when empty-after-sanitize, and deduplicating via a shared pure helper (e.g. `MenuService::uniqueSlug`) with `-2…-100` + `time()` fallback ignoring self on update. Missing status defaults to `publish` on create only; invalid status is rejected with zero writes. No `sort_order` input is rendered; menu create sets `sort_order=0`. Rename re-derives slug with the same rule, overwriting custom slugs identically to REST `updateMenu`.

#### Scenario: Create menu with items-ready fields
- **WHEN** an admin submits name "Brunch", description "Late morning", status draft with empty slug
- **THEN** a row is created with slug "brunch" and the list shows it

#### Scenario: Empty name rejected with REST-mapped notice
- **WHEN** an admin submits an empty name
- **THEN** no row is written and the form shows `smooth_menu_missing_name`

#### Scenario: Slug collision suffixed via shared helper
- **WHEN** an admin creates "Brunch" while slug "brunch" exists
- **THEN** the new row gets slug "brunch-2" through the shared helper

#### Scenario: Update keeps own slug
- **WHEN** an admin saves a menu without changing its name
- **THEN** its slug is unchanged (self ignored in collision check)

#### Scenario: Invalid status rejected
- **WHEN** an admin submits status "archived" or tampers the field
- **THEN** no row is written and the form shows an error

#### Scenario: Slashed input stored clean
- **WHEN** an admin submits name "O'Brien" with slashes
- **THEN** the stored value is "O'Brien" (unslashed before sanitize)

### Requirement: Menu delete with id-bound confirmation and verified cascade
The system SHALL delete only via confirmed POST with id-bound nonce `smooth_menu_delete_{id}`, verifying id/nonce match, cascading through both publish and draft items and their publish and draft modifiers before deleting the menu row, then verifying no orphans remain. Hard-coded `wp_safe_redirect()` + `exit` follows; no user-supplied redirect.

#### Scenario: Delete removes tree
- **WHEN** an admin confirms deletion of a menu with items and modifiers (mixed statuses)
- **THEN** the menu, its items, and their modifiers are all removed and follow-up `listByMenu`/`listByItem` are empty

#### Scenario: Unconfirmed GET deletes nothing
- **WHEN** a menu delete URL is opened via GET without nonce confirmation
- **THEN** zero `delete()` calls occur

#### Scenario: Id nonce mismatch deletes nothing
- **WHEN** a delete POST carries a valid nonce for a different id
- **THEN** no row is deleted

### Requirement: Item lite CRUD under a menu with ownership checks
The system SHALL allow add/edit/remove of items under a menu via `MenuItemRepositoryInterface` with fields name (required), description, price_cents (strict non-negative-int), image_id (`0` or verified attachment image), status allowlist; `findById` the parent menu (404 on miss), enforce `item.menu_id == URL menu_id`, silently ignore forged `menu_id` on update, never render or accept `sort_order` (create always `maxSortOrderForMenu()+1`). Invalid input maps to REST codes (`smooth_menu_item_missing_name`, `invalid_price`, `invalid_image`) with zero writes.

#### Scenario: Add item to menu
- **WHEN** an admin adds item "Margherita" price 950 to an existing menu
- **THEN** the item row exists under that menu and appears on its edit screen

#### Scenario: Invalid price rejected
- **WHEN** an admin submits price_cents "-5" or "abc"
- **THEN** no row is written and the form shows `smooth_menu_item_invalid_price`

#### Scenario: Invalid image rejected
- **WHEN** an admin submits image_id pointing to a non-attachment or another user's private file
- **THEN** no row is written and the form shows `smooth_menu_item_invalid_image`

#### Scenario: Forged parent ignored
- **WHEN** an admin POSTs an item update with forged `menu_id` for another menu
- **THEN** the row stays under its original parent

#### Scenario: Remove item cascades modifiers
- **WHEN** an admin confirms deletion of an item with modifiers (any status) via id-bound nonce POST
- **THEN** the item and all its modifiers are removed

#### Scenario: Item GET-delete does nothing
- **WHEN** an item delete URL is opened via GET
- **THEN** zero `delete()` calls occur

### Requirement: Modifier lite CRUD under an item with ownership checks
The system SHALL allow add/edit/remove of modifiers under an item via `ModifierRepositoryInterface` with fields name (required), price_cents (strict non-negative-int), status allowlist; `findById` the parent item (404 on miss), enforce `modifier.item_id == URL item_id`, silently ignore forged `item_id`, never render `sort_order` (create always `maxSortOrderForItem()+1`). No reorder, autosave, or bulk UI is provided.

#### Scenario: Add modifier to item
- **WHEN** an admin adds modifier "Extra cheese" price 150 to an existing item
- **THEN** the modifier row exists under that item

#### Scenario: Forged item parent ignored
- **WHEN** an admin POSTs a modifier update with forged `item_id`
- **THEN** the row stays under its original item

#### Scenario: Reorder explicitly absent
- **WHEN** the modifier form HTML is inspected
- **THEN** selectors for drag-reorder, bulk-reorder, and autosave are DOM-absent

#### Scenario: Modifier GET-delete does nothing
- **WHEN** a modifier delete URL is opened via GET
- **THEN** zero `delete()` calls occur

### Requirement: Capability and per-action nonce gating on every write
The system SHALL require `current_user_can(smooth_manage_menus)` first, then `check_admin_referer($action)` (dies on failure) on every state-changing admin action, with distinct actions per op (`smooth_menu_save`, `smooth_item_save`, `smooth_modifier_save`) and id-bound delete nonces. Failures perform zero writes. Post-success redirects are hard-coded via `wp_safe_redirect()` + `exit`.

#### Scenario: Unauthorized write rejected
- **WHEN** a user without the cap POSTs a menu create with a valid nonce
- **THEN** no row is written

#### Scenario: Bad nonce rejected
- **WHEN** a POST arrives with a missing or invalid nonce
- **THEN** no row is written and execution halts via die

### Requirement: REST read parity for admin-created data
Data created in wp-admin SHALL be visible via `GET /smooth/v1/menus` and `GET /smooth/v1/menus/<id>` once menu + item + modifier are all `publish`, with field shapes matching `api-docs/smooth-v1/menus/*`.

#### Scenario: Published tree readable via REST
- **WHEN** an admin creates and publishes menu "Brunch" with a published item and published modifier
- **THEN** `GET /smooth/v1/menus` lists it and `GET /smooth/v1/menus/<id>` returns its tree

#### Scenario: Draft child excluded from public tree
- **WHEN** a menu is published but its item or modifier remains draft
- **THEN** public GETs exclude the draft child (documented on-screen)

### Requirement: Stepping-stone scope guard with explicit forbids
The screen SHALL remain a minimal QA/dev surface and SHALL NOT introduce block-editor authoring, drag-reorder or `sort_order` editing, autosave, revisions UI, media-library picker, bulk ops, item search, tree preview, CSV/AI import, new tables, postmeta usage, or any `register_block_type`/Interactivity import.

#### Scenario: Forbidden paths absent by grep
- **WHEN** the quality gate greps the screen implementation
- **THEN** zero references exist to `reorderItems`, `reorderModifiers`, `autosave`, `register_block_type`, media-picker, or bulk operations
