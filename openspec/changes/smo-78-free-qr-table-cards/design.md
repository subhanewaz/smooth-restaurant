## Context

The provider architecture and `BaseRepository` data pattern are locked and green (73 tests). Tables are a domain shell: `TableService` and both repository contracts exist, `smooth_tables` has a dbDelta-first schema but no query methods, and `MigrationRunner::defaults()` is empty so activation creates nothing. `AdminProvider` and `RestProvider` boot in their contexts with empty surface methods. The Free/Pro split is fixed: Free owns table state and QR menu links; Pro (SMO-94) owns sessions and per-device carts. SMO-78 is blocked by SMO-105 (the `/menu` page) and blocks SMO-94. `sm_docs` locks D6 (sessions are rows with TTL) and 2-cards-on-A4 QR print output.

## Goals / Non-Goals

**Goals:**
- Free owners can create tables, change state across the locked transition graph, and print QR cards linking to `/menu?table=`.
- The state graph is pure domain logic; the REST API is the single source of `next_states` so the UI never re-implements rules.
- Free QR never creates a session or session-tagged order.
- `smooth_tables` is created by a version-guarded, idempotent migration; `smooth_table_sessions` is untouched.

**Non-Goals:**
- Table sessions, session-tagged orders, or per-device carts (SMO-94).
- Floor plans (M3 Pro).
- Reading/handling `?table=` on the menu page (SMO-105 owns the menu).
- Table/session demo seeding (SMO-122).
- Any rewrite rule, shortcode, or block for the menu or cards.

## Decisions

- **`RestProvider` binds the controller; `TablesProvider` stays frontend-only.** In `RestProvider::register()`, bind `TablesController` with a closure that builds it from `new TableService()` and `new RestaurantTableRepository()`. This keeps `TablesProvider::contexts()` and the locked boot matrix / `BootMatrixTest` / `RestBootTest` / `provider-architecture` spec untouched. Alternative (extend `TablesProvider` to admin+REST) rejected: it would change a locked spec and two test expectations for no functional gain.
- **`AdminProvider` needs no bindings.** The screen renders markup and talks to REST via `api-fetch`; it resolves nothing from the container.
- **`status` and `state` are separate axes.** `status` is row lifecycle (`active`/`archived` soft delete); `state` is operational (free/seated/ordered/needs_bill). Deleting a table archives it; lists return active rows.
- **State machine is a pure string-backed `TableState` enum** with `nextStates()`, `canTransitionTo()`, and `tryFrom()` helpers. `TableService` normalizes input and exposes the same transitions. No WordPress or `$wpdb` calls in `Domains/` (DomainPurity guard).
- **`next_states` and `qr_url` are computed server-side.** Every table object in a REST response carries `next_states` (from the domain) and `qr_url` (base + display-only `?table=` label), so the React screen never duplicates rules or URL logic.
- **REST contract.** `GET /tables`, `POST /tables`, `POST /tables/<id>/state`, `DELETE /tables/<id>`; all require `manage_options`. Errors: `400` invalid payload/transition/state, `404` unknown id, `409` duplicate label. Built with `RestProvider::route()` and `RestProvider::requireCapability()`.
- **`smooth_qr_menu_url` filter, no rewrite.** Default `home_url('/menu/')`, overridable via `smooth_qr_menu_url`, documented with `@since`/`@param`/`@return`/`@example` per coding-standards. `?table=` is appended for display only; the menu owner (SMO-105) decides whether to consume it.
- **No localized data.** `api-fetch` supplies the REST root and nonce; the screen reads `data-screen="tables"` off the root element and nothing else is injected.
- **Print is admin-only.** `qrcode.react` (`QRCodeSVG`) at ~55 mm plus `react-to-print`; a print stylesheet renders 2 cards per A4 row with a large label. No shortcode/block.
- **Plain-token note goes to Linear, not the code.** `TableSessionRepository` is not modified; the plain-token/hash/audit concern is recorded on SMO-94.

## Risks / Trade-offs

- [Risk] `RestProvider` constructing `TableService`/`RestaurantTableRepository` directly couples the platform provider to a domain → Mitigation: the closure is one line, uses interfaces, and keeps the boot matrix stable; Pro can still override the binding via `Container::instance()`.
- [Risk] Duplicate-label uniqueness is enforced in PHP, not a DB unique key, so concurrent creates could race → Mitigation: pre-check plus `409`; a `UNIQUE KEY label` is deferred until a backfill-safe migration is needed (existing rows may legitimately share labels).
- [Risk] Print output varies by browser → Mitigation: fixed `mm` dimensions, explicit `@media print` page rules, and a manual print check in the verification step.
- [Risk] React screen adds the first real admin bundle → Mitigation: AssetsProvider already gates on a Smooth admin screen and enqueues the admin surface; the screen ships with the existing build.
