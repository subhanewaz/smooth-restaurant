## Why

SMO-78 requires Free to give every table a printable QR entry point. The Tables domain is entirely shells today: `TableService` is empty, `RestaurantTableRepository` exposes a schema but no queries, `AdminProvider::registerMenu()` and `RestProvider::registerRoutes()` are empty, and `MigrationRunner::defaults()` creates no tables. Owners cannot create tables, manage their state, or print QR cards. Free gains table-state management and printable `/menu?table=` cards **without creating a session** — sessions are Pro (SMO-94).

## What Changes

- Add a pure table **state machine**: free → seated → ordered → needs_bill → free; any state → free; needs_bill → ordered. The domain exposes each state's valid next states so the UI never duplicates the rules.
- Extend the `smooth_tables` schema: keep `status` as the row lifecycle (`active`/`archived` soft delete), add `state` (`free`/`seated`/`ordered`/`needs_bill`). Register creation in `MigrationRunner::defaults()` and bump `TARGET_VERSION` `0.1.0` → `0.2.0`. **Only `smooth_tables`** is created; `TableSessionRepository` is untouched.
- Add repository queries through `BaseRepository` (list, find, create, update state, archive, duplicate-label check) and extend `RestaurantTableRepositoryInterface`.
- Add a REST surface: `GET /tables`, `POST /tables` (create `{label, seats}`), `POST /tables/<id>/state`, `DELETE /tables/<id>` (archive), all gated on `manage_options`. Each table in responses includes its `next_states` and `qr_url`.
- Add an **admin React** screen mounted from a PHP-rendered `<div id="smooth-admin-root" data-screen="tables">`; create/list tables, change state, select and print QR cards. Slug, capability, and root id are `AdminProvider` class constants (no hardcoded strings).
- Add printable cards via `qrcode.react` + `react-to-print` with a print stylesheet: 2 cards per A4 row, large label, ~55 mm QR. Admin screen only; no shortcode or block.
- Add the `smooth_qr_menu_url` filter, default `home_url('/menu/')`. The `?table=` label is display-only; **no rewrite rule** is added.
- No table seeding.

## Capabilities

### New Capabilities

- `free-qr-table-cards`: Free table state model and transitions, table management REST API with `next_states`/`qr_url`, admin management screen, printable QR cards, and the `smooth_qr_menu_url` filter — with an explicit guarantee that Free QR opens the menu only and never creates a table session.

### Modified Capabilities

- None. `provider-architecture` is unchanged: `TablesProvider` stays frontend-only, `RestProvider` binds the controller locally, and the boot matrix / `BootMatrixTest` are not touched.

## Impact

- Affected code: `src/Domains/Tables/{TableState.php (new),TableService.php}`, `src/Contracts/RestaurantTableRepositoryInterface.php`, `src/Database/Repositories/RestaurantTableRepository.php`, `src/Database/MigrationRunner.php`, `src/Providers/{Admin,Rest}Provider.php`, `src/Rest/TablesController.php` (new), `assets/src/admin/**`, print stylesheet under `assets/css/`, `tests/`.
- APIs: new `smooth_qr_menu_url` filter; new `/smooth/v1/tables` routes; new `AdminProvider` slug/capability/root-id constants.
- Dependencies/systems: uses `qrcode.react`, `react-to-print`, and `@wordpress/components` (already in `package.json`); no runtime dependency added. No changes to `smooth_table_sessions` or Pro.
