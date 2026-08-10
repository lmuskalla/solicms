# Tasks: Footer navigation

id: k6ujxu
status: open
analyst: architect
date: 2026-08-10

<!-- Produced by @analyst from brief.md. -->

## Task breakdown

TASK-1: Add a `menu` column to `nav_items` (values `header`/`footer`, default `header`) via a new tenant migration, and teach the `NavItem` model about it.
     files: database/migrations/tenant/2026_08_10_000000_add_menu_to_nav_items_table.php (new), app/Models/NavItem.php
     depends: none
     risk: medium — touches every tenant's schema (needs `tenants:migrate`). Column default decides what existing nav items become; with `header` as default, existing tenants' footers will render an empty nav until an editor curates footer items. Name the file with a 2026 date so it sorts after the existing `2024_01_01_0000NN` tenant migrations. Add a `NavItem::MENUS` const (`['header', 'footer']`) as the single source of truth for validation, add `menu` to the `#[Fillable(...)]` list, and update the class docblock.

TASK-2: Scope `Admin\NavigationController` to per-menu ordering and grouping.
     files: app/Http/Controllers/Admin/NavigationController.php
     depends: TASK-1
     risk: medium — `store()` currently appends at global `max('order')+1` and `move()` swaps with a global order-neighbor; both must be scoped to the item's own menu or reordering in one menu can jump into the other. `index()` should return the items as two ordered lists (`headerItems`/`footerItems`, each ordered by `order` within its menu). `store()` gains an optional `menu` (validated `in: NavItem::MENUS`, default `header`). `update()` gains an optional `menu` change so an editor can move an item between menus (when changed, append at the target menu's end); `move()`'s neighbor lookup is filtered by `menu`.

TASK-3: Pass header and footer nav separately from the frontend controllers.
     files: app/Http/Controllers/Frontend/PageController.php, app/Http/Controllers/Frontend/PostController.php
     depends: TASK-1
     risk: low — both currently build one flat `nav`; change to `nav` = header items plus a new `footerNav` = footer items (same `{label, href}` shape, order preserved; mind `Collection::where(...)->values()`). Additive, two duplicated spots to update identically.

TASK-4: Preserve `menu` through tenant provisioning and content export/import.
     files: app/Services/TenantProvisioner.php, app/Services/TenantContentExporter.php, app/Services/TenantContentImporter.php
     depends: TASK-1
     risk: medium — `TenantProvisioner::seedPages()` seeds nav items (explicit `'menu' => 'header'` for clarity). `TenantContentExporter::buildManifest()` must include `menu` in each nav_items entry; `TenantContentImporter::recreate()` must restore it with a `'header'` fallback for archives exported before this change. Keep the manifest `schema_version` at 1 (additive, backward-compatible field) — verify an old archive still imports and a new archive survives an export→import round trip.

TASK-5: Extend the shared frontend contract with `footerNav`.
     files: resources/js/types.ts, resources/js/Pages/Frontend/Page.svelte
     depends: TASK-3
     risk: low — add `footerNav` to `ThemeProps` and accept/pass it through `Pages/Frontend/Page.svelte`. Additive; existing components that don't destructure the new prop are unaffected.

TASK-6: Render footer nav in the default theme.
     files: resources/js/Components/Frontend/SiteFooter.svelte, resources/themes/default/Wysiwyg.svelte, resources/themes/default/HomeStandard.svelte
     depends: TASK-5
     risk: low — `SiteFooter.svelte` currently takes only `config`; add a `footerNav` prop and render the links when non-empty. The two default templates pass `footerNav` to it (they already pass `nav` to `SiteHeader`).

TASK-7: Switch the dvm theme footer to `footerNav`.
     files: resources/themes/dvm/components/Footer.svelte, resources/themes/dvm/templates/Home.svelte, resources/themes/dvm/templates/Page.svelte, resources/themes/dvm/templates/Contact.svelte
     depends: TASK-5
     risk: low — mechanical. dvm templates declare local `Props` interfaces, so each needs `footerNav` added and passed to `<Footer>`; `Footer.svelte` renders `footerNav` instead of `nav`.

TASK-8: Switch the geko theme footer to `footerNav`.
     files: resources/themes/geko/components/Footer.svelte, resources/themes/geko/templates/Home.svelte, resources/themes/geko/templates/Page.svelte, resources/themes/geko/templates/Aktuelles.svelte
     depends: TASK-5
     risk: low — same as TASK-7; geko templates already use `ThemeProps`, so only the pass-through and `Footer.svelte` change.

TASK-9: Make the admin navigation screen manage two menus.
     files: resources/js/Pages/Admin/Navigation/Index.svelte (optionally plus a shared sub-component, e.g. resources/js/Pages/Admin/Navigation/NavList.svelte, to avoid duplicating the item-row markup)
     depends: TASK-2
     risk: medium — largest UI change. Two sections (German copy, e.g. "Kopfzeilen-Navigation" / "Fußzeilen-Navigation"), each with its own ordered item list, its own add-page/add-link forms (menu implied by which section is used), per-menu move up/down, edit, delete, and a per-item "move to the other menu" action.

TASK-10: Verify end-to-end.
     files: none (verification only)
     depends: TASK-2, TASK-4, TASK-6, TASK-7, TASK-8, TASK-9
     risk: low — run `php artisan tenants:migrate`, then `docker compose exec vite npx svelte-check --tsconfig ./tsconfig.json` (must stay at 0 errors per THEMES.md §7). Manually verify against a dev tenant: add items to header and footer nav in admin, reorder within each menu, move an item between menus, and confirm the public site renders header items in the header and footer items in the footer for at least the `default`, `dvm`, and `geko` themes. Optionally confirm a content export→import round trip keeps menus.

## Open questions for @analyst / requester before implementation

- Data migration of existing nav items: all current `nav_items` rows become `menu='header'`. That means dvm/geko footers (which today render the full nav list) will render an empty footer nav after deploy until an editor adds footer items. This is the intended direction ("top is for quick access, bottom is for less relevant things"), but confirming the empty-footer default is acceptable before implementation is worthwhile — the alternative (duplicating all items into the footer too) merely preserves the status quo the feature exists to fix.
- Should DVM's real footer menu (Kontakt, Impressum, …) be seeded/pre-configured as part of this job, or is it left for the editor to curate in the new UI? Brief doesn't specify; assumed out of scope.
- Export/import manifest: keeping `schema_version = 1` with a `menu ?? 'header'` fallback (backward compatible with existing archives) vs. bumping to v2 (would reject older archives). Recommended: keep v1.
- Per-theme menu definitions (e.g. a theme with no footer nav, or a third menu) are deliberately out of scope — `header`/`footer` is hardcoded platform-wide for now. Flagged as future flexibility, not built speculatively.
- Note: `resources/js/Themes/dvm/Home.svelte` is a stale, unreferenced leftover of an earlier theme system (nothing imports it). It must NOT be touched by this job; cleaning it up would be a separate task.
