# Feature: About Us Admin Page

## Overview

This branch adds an **About Us** page to the Smooth Restaurant plugin admin panel.

The feature follows the classic WordPress admin-page workflow — **add a menu, add a
submenu, load a page** — while integrating with the existing React admin SPA so the
plugin's architecture stays consistent.

## Mentor requirements

> "Adding an About Us page to the plugin's admin panel. Also add a menu, submenu,
> load a page, etc."

| Requirement | How it is satisfied |
| --- | --- |
| Add a **menu** | A new top-level `add_menu_page( 'About Us', ... )` registered on the `admin_menu` hook (`AdminMenu.php`). |
| Add a **submenu** | A classic same-slug first submenu via `add_submenu_page( 'smooth-restaurant-about', ... )` (`AdminMenu.php`). |
| **Load a page** | The menu slug loads a render callback (`render_about_page()`) that mounts the admin React SPA, deep-linked to the `#/about-us` route. |
| Page content | `AboutUs.tsx` React page (rendered through the existing `PlaceholderPage` component). |

## Architecture decisions

- **New top-level menu** — slug `smooth-restaurant-about`, dashicon `dashicons-info`,
  position `26` (directly below the existing Smooth Restaurant menu at `25`).
  The submenu shares the same slug, which is WP's canonical way to create a
  parent item with a first submenu.
- **SPA integration** — the About Us page reuses the existing React bundle. The
  render callback echoes the standard mount node
  `<div id="smooth-restaurant-admin"></div>`.
- **Deep-link** — `Assets.php` localizes `initialRoute` (via `wp_localize_script`)
  when the hook is `toplevel_page_smooth-restaurant-about`. That hook already
  matches the existing `is_plugin_page()` prefix check, so the SPA loads
  automatically. `index.tsx` applies `initialRoute` as the hash before mounting,
  and the `HashRouter` renders `/about-us` directly.
- **Navigation consistency** — the About Us route is also added to the SPA sidebar
  so it is reachable both from the WordPress admin menu and from inside the SPA.

## Files to change

| File | Change |
| --- | --- |
| `assets/src/admin/pages/AboutUs.tsx` | **New** page component using `PlaceholderPage`, strings wrapped in `__()`. |
| `assets/src/admin/config/routes.ts` | Lazy import `AboutUs`; add `{ path: '/about-us', ... }` as the last route. |
| `assets/src/admin/components/Sidebar.tsx` | Add `Info` to the `lucide-react` import; add `'/about-us': Info` to `iconMap`. |
| `assets/src/admin/index.tsx` | Apply localized `initialRoute` as the location hash before mounting. |
| `src/Admin/AdminMenu.php` | Register the top-level menu, same-slug submenu, and `render_about_page()`. |
| `src/Admin/Assets.php` | Localize `initialRoute => '/about-us'` on the About Us page hook. |

## Verification

- `npm run lint:js`
- `npm run build`
- `composer cs:check`
- `composer stan`
- `composer test:unit`
- `npm run test:js`

## PR steps

1. Commit the changes on `Feature/About-Us`.
2. `git push -u origin Feature/About-Us`.
3. `gh pr create --base main`.

## Status

- [x] Design doc created
- [x] AboutUs page component
- [x] Route registration
- [x] Sidebar entry + icon
- [x] Deep-link support
- [x] Admin menu + submenu (PHP)
- [x] Asset localization (PHP)
- [x] Verification passed (build, Jest, PHPUnit, PHPStan clean for changed files)
- [ ] Pull request opened
