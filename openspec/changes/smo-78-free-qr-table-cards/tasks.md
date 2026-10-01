## 1. Domain

- [x] 1.1 Add `TableState` string-backed enum (`free`, `seated`, `ordered`, `needs_bill`) with `nextStates()`, `canTransitionTo()`, and `all()`
- [x] 1.2 Implement `TableService`: normalize/validate a state string and expose the transition graph (free→seated→ordered→needs_bill→free; any→free; needs_bill→ordered); also normalize labels (strip tags, trim, collapse internal whitespace, 1–64 chars, reject empty) and throw a `TableException` for an invalid label or an illegal move
- [x] 1.3 Add a pure menu-URL builder that appends the display-only `?table=` label to a base URL
- [x] 1.4 Unit tests: every allowed transition, every rejected transition, `nextStates()` per state, URL label encoding

## 2. Data

- [x] 2.1 Add `state varchar(32) NOT NULL DEFAULT 'free'` to `RestaurantTableRepository::columnDefinitions()`; keep `status` as active/archived lifecycle
- [x] 2.2 Extend `RestaurantTableRepositoryInterface` + repository: list active, find by id, create `{label, seats}`, update state, archive, duplicate-label lookup
- [x] 2.3 Register the `smooth_tables` creation migration in `MigrationRunner::defaults()` and bump `TARGET_VERSION` `0.1.0` → `0.2.0` (create `smooth_tables` only)
- [x] 2.4 Add a recording wpdb test double under `tests/Unit/Database/Support` exposing `get_results`/`insert`/`update`, since `FakeWpdb` only implements `prepare()`; tests: schema includes the `state` column; repository CRUD against the recording double; migration creates the table and a rerun is a no-op; assert `smooth_table_sessions` is never created. Note `ActivatorTest` runs migrations without WordPress, where `dbDelta` is unavailable

## 3. REST

- [ ] 3.1 Add `src/Rest/TablesController.php`: `GET /tables`, `POST /tables`, `POST /tables/<id>/state`, `DELETE /tables/<id>`; include `next_states` and `qr_url` on each table
- [ ] 3.2 Bind `TablesController` in `RestProvider::register()` via a closure using `new TableService()` and `new RestaurantTableRepository()` (TablesProvider stays frontend-only)
- [ ] 3.3 Register routes in `RestProvider::registerRoutes()` with `requireCapability('manage_options')`; map errors to 400 invalid, 404 not found, 409 duplicate label
- [ ] 3.4 Resolve `qr_url` from `home_url('/menu/')` and the `smooth_qr_menu_url` filter (documented `@since`/`@param`/`@return`/`@example`); append the label for display only
- [ ] 3.5 Unit tests: route registration, capability, each status code, `next_states` per state, `qr_url` default + filter override

## 4. Admin UI

- [ ] 4.1 Add `AdminProvider` constants for slug, `manage_options` capability, and root id; register the page and render `<div id="smooth-admin-root" data-screen="tables">`
- [ ] 4.2 Build the React tables screen in `assets/src/admin` mounting on `data-screen="tables"`; use `api-fetch` for list/create/state/archive (REST root + nonce come from `api-fetch`, no localized data)
- [ ] 4.3 Render QR cards with `qrcode.react` (`QRCodeSVG`, ~55 mm) and add a `react-to-print` print action with table selection
- [ ] 4.4 Add the print stylesheet: 2 cards per A4 row, large label, readable in grayscale
- [ ] 4.5 Component test: renders tables, exposes only `next_states` actions, and triggers print

## 5. Scope guards

- [ ] 5.1 Confirm `TableSessionRepository`, sessions, and session-tagged orders are untouched and Free creates no session (record the plain-token concern on SMO-94 in Linear)
- [ ] 5.2 Confirm no rewrite rule, shortcode, or block is added, and no tables are seeded

## 6. Verification

- [ ] 6.1 `composer quality` green (PHPCS, PHPStan, unit suite)
- [ ] 6.2 `npm run test:js` and `npm run build` green (admin bundle + print CSS compile)
- [ ] 6.3 Manual pass: create tables, walk the state graph via the UI, print cards and confirm 2-up A4 with legible labels; since `/menu` does not exist until SMO-105, confirm the printed QR encodes `<menu url>?table=<label>` and that no `smooth_table_sessions` row appears
- [ ] 6.4 On `wp-env`, call every `/smooth/v1/tables` route as an admin (and once logged out) and confirm they are registered and return `200`/`201`/`400`/`401`/`404`/`409` as expected; unit tests cannot prove routes register on real WordPress
