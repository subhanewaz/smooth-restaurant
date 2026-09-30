## Context

`upstream/rebuild` (`96a17d6`) already contains the SMO-143 admin shell:

- `src/Providers/AdminProvider.php::registerMenu()` registers the top-level `Smooth` menu and the `Smooth → Menus` submenu, both gated by `MenuProvider::MANAGE_CAP` (`smooth_manage_menus`, mapped to `manage_options`).
- `src/Domains/Menu/MenuAdminScreen.php` owns the shared identifiers (`MENU_SLUG = 'smooth'`, `PAGE_SLUG`, `TOP_HOOK`, `MENUS_HOOK`) and the shared helpers (`esc()`, `canManage()`, `verifyNonce()`, sanitizers).
- `src/Providers/AssetsProvider.php::isSmoothAdminScreen()` gates admin assets on `str_contains($screen->id, 'smooth')`.
- `src/Core/Settings.php` is a `final` class owning the `smooth_settings` option, with all WordPress access `function_exists`-guarded.

There is no `src/Admin/` layer and no admin SPA: `assets/src/admin/index.tsx` is `export {};`. The prior attempt (PR #2, `a4ff018`) was written against a pre-SMO-143 tree and assumed both existed.

## Goals / Non-Goals

**Goals:**

- An owner-facing `Smooth → About Us` page reachable from wp-admin, gated by the existing menu capability.
- One definition of the menu slug, page slug, and hook suffix, reused from `MenuAdminScreen` rather than re-typed.
- `AboutUs extends Settings` over a namespaced option, with no invented `about_us_*` keys.
- Server-rendered, zero new assets, zero new hooks, zero new stubs.

**Non-Goals:**

- No admin SPA: no router entry, no sidebar entry, no React page, no `aboutUsConfig` localization, no asset handles, no CSS or colours. There is no SPA on `rebuild` to extend.
- No dedicated capability. `smooth_manage_menus` is reused rather than adding `smooth_view_about` to the capability map.
- No settings keys. The page has no editable field, so `DEFAULTS` is inherited and unused.
- No i18n on the new labels; `About Us` matches SMO-143's existing untranslated `'Smooth'` / `'Menus'` strings.
- No second top-level menu, no separate `src/Admin/` namespace, no change to SMO-143's own identifiers.
- No REST endpoint, no new hooks, no `HOOKS.md` row, no `WpStubs.php` changes.

## Decisions

### 1. Submenu under the existing Smooth menu, not a second top-level menu

`registerMenu()` adds one `add_submenu_page( MenuAdminScreen::MENU_SLUG, ... )` after the Menus submenu. Reusing the existing parent means the capability, the `smooth`-containing slug (asset gate), and the `smooth_page_*` hook convention all apply unchanged. The original instructions called for a separate top-level menu at position `'26.7'`, which existed only because there was no Smooth menu to nest under; a submenu also makes the menu position argument moot, since `add_submenu_page()` has no position parameter and ordering follows registration order.

### 2. Reuse `MenuAdminScreen::MENU_SLUG` and `MenuAdminScreen::esc()`; derive everything else

`AboutUs::MENU_SLUG` is an alias, `PAGE_SLUG` is `MENU_SLUG . '-about-us'`, and `PAGE_HOOK` is `MENU_SLUG . '_page_' . PAGE_SLUG`. Deriving the page slug from the menu slug makes the asset-gate invariant structural rather than a convention that can drift, and deriving the hook suffix removes the second hand-written `smooth_page_...` string. Escaping calls `MenuAdminScreen::esc()` so there is one helper, not two.

Rejected: adding `AboutUs::esc()` (duplicated helper — the exact mentor finding), and typing `'smooth'` or `'smooth-about-us'` as literals in `AboutUs`.

### 3. `AboutUs extends Settings`, overriding only `OPTION`

`Settings` loses `final` and resolves `DEFAULTS`/`OPTION` via `static::`. `AboutUs` overrides `OPTION` to `smooth_about_us_settings` so the surface can never read or write the plugin-wide `smooth_settings`. `DEFAULTS` is deliberately **not** overridden: the page has no editable field, so there are no `about_us_*` keys to declare, and a subclass that redeclares `DEFAULTS` would need to widen the parent's array shape for PHPStan. Keys get added to `AboutUs::DEFAULTS` later only if a real field appears. Existing `SettingsTest` cases are untouched and stay green because they construct `new Settings()` directly.

### 4. `src/Core/AboutUs.php`, accepting a Core→Domains import

`folder-structure.md` says new domain logic goes in `src/Domains/<D>/`, but `AboutUs extends Settings` reads and writes options, and `DomainPurityTest`'s own docblock states that "options/postmeta reads belong to the platform layer". Placing the class in `src/Domains/` would pass the token scan only because the subclass never spells `get_option` itself — the inherited calls would still violate the documented rule invisibly. So the class lives beside its parent in `src/Core/`, which also satisfies `src/Core/AGENTS.md`'s "no domain logic here": `AboutUs` holds an option name and three identifiers, no logic.

The cost is that reusing `MenuAdminScreen` adds the repository's first `Core → Domains` import. Before this change `git grep` showed zero `Domains` imports in `src/Core/` and zero `Core` imports in `src/Domains/`, so the two layers were fully decoupled. The new edge carries only a slug constant and an escaping helper, and it is the direct alternative to duplicating both. Rejected: moving `esc()` into a shared `src/Core/Html.php` consumed by `MenuAdminScreen` too — that would remove the edge but requires editing SMO-143 code, which is out of scope for this change.

### 5. Capability re-check in the render callback

`add_submenu_page()` gates menu visibility, not a directly requested `admin.php?page=smooth-about-us`. `renderAboutPage()` therefore re-checks `current_user_can( MenuProvider::MANAGE_CAP )` with the same `function_exists` guard `registerMenu()` uses, mirroring the cap re-check in `renderMenusPage()`. `About Us` is read-only, so no nonce is involved.

### 6. Logged as follow-ups instead of fixed here

- **i18n on Smooth admin labels.** `registerMenu()` passes plain `'Smooth'` / `'Menus'`; the new `'About Us'` matches. Adding `__()` to only the new strings would make the file inconsistent, so all-or-nothing belongs in its own change.
- **`MenuAdminScreen::TOP_HOOK = 'toplevel_page_smooth'`.** Re-hard-codes the menu slug that `MENU_SLUG` already defines — the same class of duplication this change removes in `AboutUs`. Editing SMO-143's constants is out of scope here.

## Risks / Trade-offs

- `AboutUs` is effectively static today (option name + derived identifiers), so it is resolved by direct class reference in `AdminProvider` rather than bound in the container. That matches how `AdminProvider` already references `MenuAdminScreen::*` and avoids binding an unused singleton. If `AboutUs` gains real behaviour it becomes a container binding.
- Unfinalizing `Settings` and switching to `static::` is a public-API relaxation. It is additive — `new Settings()` and every existing test behave identically — but it does mean `Settings` can now be subclassed by anyone, which is the point.
