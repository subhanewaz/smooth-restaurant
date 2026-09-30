## ADDED Requirements

### Requirement: About Us submenu registered under the existing Smooth menu

The system SHALL register an "About Us" submenu via `AdminProvider::registerMenu()` under the existing top-level Smooth menu, gated by `MenuProvider::MANAGE_CAP`, with a page slug derived from `MenuAdminScreen::MENU_SLUG`. Registration SHALL reuse `MenuAdminScreen::MENU_SLUG` and SHALL NOT re-declare the `smooth` menu slug as a literal in About Us code. The screen SHALL render server-side PHP and SHALL NOT register a React route, an admin sidebar entry, localized config, or About Us-specific assets.

#### Scenario: Admin sees Smooth → About Us

- **WHEN** an admin with `smooth_manage_menus` opens wp-admin
- **THEN** a "Smooth → About Us" entry is visible below "Smooth → Menus" and the page returns HTTP 200 with no PHP fatal or notice

#### Scenario: About Us page slug is derived from the menu slug

- **WHEN** the registered About Us page slug is compared against `MenuAdminScreen::MENU_SLUG`
- **THEN** the slug equals `MenuAdminScreen::MENU_SLUG . '-about-us'` and contains `smooth` so the existing asset gate passes

#### Scenario: Menu slug is defined once

- **WHEN** the About Us class constants are inspected
- **THEN** the menu slug is identical to `MenuAdminScreen::MENU_SLUG` and no About Us source file contains a standalone `smooth` menu-slug literal

#### Scenario: No About Us assets are introduced

- **WHEN** the About Us screen loads and the change diff is inspected
- **THEN** no file under `assets/` changed, no new enqueue call was added, and `AssetsProvider` is unmodified

### Requirement: Hook suffix derived and consistent with the Menus page

The system SHALL derive the About Us admin page hook suffix as `{MenuAdminScreen::MENU_SLUG}_page_{About Us page slug}`, matching the rule the sibling Menus page already follows (`MenuAdminScreen::MENUS_HOOK`). The suffix SHALL be exposed as an `AboutUs` class constant so it is asserted rather than reconstructed at call sites.

#### Scenario: Hook suffix matches the WordPress convention

- **WHEN** the About Us hook suffix is computed
- **THEN** it equals `smooth_page_smooth-about-us` and applying the same derivation to the Menus page reproduces `MenuAdminScreen::MENUS_HOOK`

### Requirement: About Us screen is capability-gated on direct access

`renderAboutPage()` SHALL re-check `current_user_can( MenuProvider::MANAGE_CAP )` with a `function_exists` guard before emitting output, because `add_submenu_page()` gates menu visibility but not a directly requested `admin.php?page=` URL. All rendered text SHALL be escaped through the single shared `MenuAdminScreen::esc()` helper. About Us SHALL NOT introduce its own escape helper.

#### Scenario: Unauthorized user sees nothing

- **WHEN** a user without `smooth_manage_menus` requests the About Us page directly
- **THEN** no markup is emitted and no PHP notice is raised

#### Scenario: Unauthorized user sees no menu entry

- **WHEN** a user without `smooth_manage_menus` opens wp-admin
- **THEN** no About Us entry is registered for them

#### Scenario: Output is escaped

- **WHEN** `renderAboutPage()` emits its heading and body text
- **THEN** both pass through `MenuAdminScreen::esc()` and the rendered heading is `<h1>About Us</h1>`

### Requirement: About Us extends Settings over a namespaced option

`AboutUs` SHALL extend `SmoothRestaurant\Core\Settings` and SHALL override only `OPTION`, resolving it to `smooth_about_us_settings`, so the surface can never read or write the plugin-wide `smooth_settings` option. `Settings` SHALL no longer be `final`, and `Settings::DEFAULTS` and `Settings::OPTION` SHALL resolve through `static::` so a subclass override is honoured. About Us SHALL NOT declare any `about_us_*` setting key; `DEFAULTS` is inherited and unused until the page grows a real field.

#### Scenario: Inherited defaults resolve through the subclass

- **WHEN** a default key is read from an `AboutUs` instance
- **THEN** the inherited default is returned, proving late static binding works

#### Scenario: Storage is namespaced away from plugin-wide settings

- **WHEN** `AboutUs::OPTION` and `Settings::OPTION` are compared
- **THEN** they differ, and an `AboutUs` instance is an instance of `Settings`

#### Scenario: Existing settings behaviour is unchanged

- **WHEN** the pre-existing `SettingsTest` suite runs
- **THEN** every case passes unmodified, since `Settings` resolves its own constants identically

#### Scenario: No invented keys

- **WHEN** the `AboutUs` class is inspected
- **THEN** it declares no `DEFAULTS` override and no `about_us_*` key exists anywhere in the change

### Requirement: No new hooks, stubs, or capability map entries

The change SHALL NOT add a `smooth_restaurant_`-prefixed action or filter, SHALL NOT modify `HOOKS.md`, SHALL NOT extend `tests/Support/WpStubs.php`, and SHALL NOT add a new capability to the `MenuProvider` capability map.

#### Scenario: Hook inventory unchanged

- **WHEN** the change diff is inspected
- **THEN** no new hook is added and `HOOKS.md` is untouched

#### Scenario: Capability map unchanged

- **WHEN** `MenuProvider`'s capability map is inspected
- **THEN** it still maps only `smooth_manage_menus` → `manage_options`, which is what gates About Us
