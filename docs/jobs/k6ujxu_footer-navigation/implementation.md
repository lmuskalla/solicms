# Implementation: Footer navigation

id: k6ujxu
status: open
developer:
date: 2026-08-10

<!-- Produced by @developer after implementation. -->

## Summary

The platform now supports two distinct navigations per tenant: a header menu and a footer menu.
A new `menu` column on `nav_items` (values `header`/`footer`, default `header`) discriminates the
two. The admin navigation screen was split into two side-by-side menus (Kopfzeilen-/Fußzeilen-
Navigation), each with its own item list, add-page/add-link forms, per-menu reordering, edit,
delete, and a per-item "move to the other menu" action. The public site now receives `nav`
(header items) and `footerNav` (footer items) separately; the default, dvm, and geko themes render
footer items in their footers instead of reusing the header nav. `menu` is carried through tenant
provisioning and content export/import (backward compatible — archives exported before this change
still import, with all items landing in the header menu).

## Changes

TASK-1: Added `database/migrations/tenant/2026_08_10_000000_add_menu_to_nav_items_table.php`
  (new) — `menu` string column, default `'header'`, so all existing nav items become header items.
  `app/Models/NavItem.php` — added `NavItem::MENUS` const (`['header', 'footer']`) as the single
  source of truth, added `menu` to `#[Fillable(...)]`, and documented the model/field in a class
  docblock.

TASK-2: `app/Http/Controllers/Admin/NavigationController.php` — `index()` now returns
  `headerItems`/`footerItems` (each ordered by `order` within its menu); `store()` validates an
  optional `menu` (default `header`) and scopes `max('order')+1` to the item's menu; `update()`
  accepts an optional `menu` change and appends the item at the target menu's end when the menu
  changes; `move()`'s neighbor lookup is filtered by `menu`. Extracted a shared `itemList()`
  helper for the two menu lists.

TASK-3: `app/Http/Controllers/Frontend/PageController.php` and
  `app/Http/Controllers/Frontend/PostController.php` — fetch nav items once, then split into
  `nav` (menu = header) and a new `footerNav` (menu = footer), same `{label, href}` shape,
  order preserved (`Collection::where(...)->values()` to re-index).

TASK-4: `app/Services/TenantProvisioner.php` — seeded nav items now set an explicit
  `'menu' => 'header'`. `app/Services/TenantContentExporter.php` — each nav_items manifest entry
  now includes `menu`. `app/Services/TenantContentImporter.php` — restores `menu` with a
  `'header'` fallback for archives exported before the column existed. `schema_version` kept at 1.

TASK-5: `resources/js/types.ts` — added `footerNav` to `ThemeProps`.
  `resources/js/Pages/Frontend/Page.svelte` — accepts `footerNav` and passes it to the theme
  template component.

TASK-6: `resources/js/Components/Frontend/SiteFooter.svelte` — new optional `footerNav` prop,
  renders the links (via Inertia `<Link>`) when non-empty. `resources/themes/default/Wysiwyg.svelte`
  and `resources/themes/default/HomeStandard.svelte` — accept and pass `footerNav` to
  `SiteFooter`.

TASK-7: `resources/themes/dvm/components/Footer.svelte` — renders `footerNav` instead of `nav`.
  `resources/themes/dvm/templates/{Home,Page,Contact}.svelte` — declare `footerNav` in their local
  `Props` and pass it to `<Footer>`; `<Header>` keeps `nav`.

TASK-8: `resources/themes/geko/components/Footer.svelte` — renders `footerNav` instead of `nav`
  (destructured from `ThemeProps`). `resources/themes/geko/templates/{Home,Page,Aktuelles}.svelte`
  — destructure `footerNav` from `ThemeProps` and pass it to `<Footer>`.

TASK-9: `resources/js/Pages/Admin/Navigation/Index.svelte` — rewritten to render two menus side
  by side from `headerItems`/`footerItems`. New
  `resources/js/Pages/Admin/Navigation/NavList.svelte` — shared sub-component encapsulating one
  menu's item list plus its add-page/add-link forms, per-menu move up/down, inline edit, delete
  (with the existing `ConfirmDialog`), and the "move to the other menu" action (PATCH with the
  new `menu`). German copy: "Kopfzeilen-Navigation" / "Fußzeilen-Navigation".

TASK-10: Verification. `php artisan tenants:migrate` applied the new migration to all four
  existing tenant DBs (verified: `menu` column present, existing items defaulted to `header`).
  `npx svelte-check --tsconfig ./tsconfig.json` reports 0 errors/0 warnings in every file this
  job touched. Controller/flow checks against a scratch dev tenant: per-menu `store()` ordering
  (footer orders 1,2 do not collide with header orders 1,2,3), `move()` reorders only within its
  own menu, `update()` with a `menu` change moves an item across menus and appends at the target
  menu's end, the frontend `nav`/`footerNav` split is correct, and an export→import round trip
  preserved menus (scratch tenant ended with header: 2, footer: 3). An archive whose nav_items
  lack `menu` imports with all items in `header` (backward compat). All test tenants and scratch
  scripts were cleaned up afterwards; the workspace's original four tenants remain.

## Known issues / follow-ups

- **Pre-existing svelte-check errors in a forbidden file**: `resources/js/Themes/dvm/Home.svelte`
  (the stale, unreferenced leftover of an earlier theme system — nothing imports it) already had 7
  TypeScript errors before this job and still does; per the task notes it must NOT be touched here.
  Cleaning it up is a separate task.
- **Environment quirk during verification**: docker isn't available in this sandbox, and the
  local `.env` is a character device that exposes no variables, so `central_connection`
  (`env('DB_CONNECTION', 'central')`) resolved to a non-existent `central` connection. Commands
  were run with `DB_CONNECTION=sqlite`, and `CACHE_STORE=array` had to be set for tenant
  provisioning because without it the spatie permission migration flushes the (non-existent)
  `cache` table in the tenant DB — the exact issue AGENTS.md pins via env vars in real
  deployments. No code change was needed.
- **Unrelated files swept into the TASK-1 commit**: `git add -A` on the first commit also picked
  up pre-existing untracked workspace scaffolding (`.opencode/`, `AGENTS.md`, `docs/CLAUDE.md`)
  that was sitting in the working tree before this job started. They are committed but untouched
  by this feature.
- **Empty-footer default**: existing tenants now show an empty footer nav until an editor curates
  footer items (all existing items became header items). This is the intended direction per the
  brief ("top is for quick access, bottom is for less relevant things"); confirmed in the task's
  open questions.
