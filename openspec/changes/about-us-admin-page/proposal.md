## Why

SMO-143 shipped the `Smooth` admin menu and the `Smooth → Menus` screen, but the plugin still has no owner-facing page that states what it is. An About Us page gives owners and QA a first landing spot inside wp-admin and demonstrates the admin shell's submenu + capability pattern outside of the menu CRUD surface.

The original attempt (PR #2, commit `a4ff018`) was built against a pre-SMO-143 tree: it created a `src/Admin/` layer that does not exist on `rebuild`, hard-coded a second `'smooth'` menu slug and a standalone top-level menu, duplicated the escape helper, and shipped a React route for a page that has no admin SPA to live in. This change re-implements the page against the real admin shell.

## What Changes

- Add an `About Us` submenu under the existing `Smooth` top-level menu via `AdminProvider::registerMenu()`, gated by `MenuProvider::MANAGE_CAP` and rendered by a new `renderAboutPage()` callback that re-checks the capability.
- Add `src/Core/AboutUs.php`: owns the submenu slug, the hook suffix, the admin URL, and a namespaced settings option. Extends `Settings` per the team decision that feature surfaces inherit the shared settings surface.
- Make `src/Core/Settings.php` extensible: remove `final` and resolve `DEFAULTS`/`OPTION` through `static::`. No behaviour change for existing callers.
- Derive every identifier instead of repeating literals: `AboutUs::MENU_SLUG` aliases `MenuAdminScreen::MENU_SLUG`, `PAGE_SLUG` is derived from it, and `PAGE_HOOK` follows WordPress' `{parent}_page_{page}` rule. No second `'smooth'` literal and no second `esc()` helper.
- Server-rendered only: no JavaScript, no CSS, no localized config, no route registration. The existing `AssetsProvider` slug gate already covers the screen.

## Capabilities

### New Capabilities

- `admin-about-us`: Smooth → About Us submenu, server-rendered screen, capability gating on both registration and direct access, single-source identifiers.

### Modified Capabilities

- None. Uses `provider-architecture` (register/boot split) and `admin-menus-crud` (the Smooth menu shell, `MenuProvider::MANAGE_CAP`, `MenuAdminScreen` helpers) without changing their requirements. `Core/Settings` changes shape (unfinalized, `static::`) but not behaviour.

## Impact

- Touched: `src/Core/Settings.php` (extensibility), `src/Core/AboutUs.php` (new), `src/Providers/AdminProvider.php` (submenu + callback), `tests/Unit/Core/AboutUsTest.php` (new), `tests/Unit/Providers/AdminProviderTest.php` (+3 cases).
- Not touched: `src/Providers/AssetsProvider.php`, `src/Providers/MenuProvider.php`, `src/Domains/Menu/MenuAdminScreen.php`, `HOOKS.md` (no new `smooth_restaurant_` hook), `tests/Support/WpStubs.php` (all needed stubs already exist), anything under `assets/`.
- Not in scope: i18n on Smooth admin labels, and `MenuAdminScreen::TOP_HOOK` re-hard-coding the menu slug. Both are logged as follow-ups rather than folded into this change.
- Tests: `AboutUsTest` covers the inherited Settings surface, the namespaced option, and identifier derivation; `AdminProviderTest` covers submenu registration and capability gating on direct access.
