# Tasks: Admin editing gaps — publish control, alt text, view-site link

Source: `docs/reports/2026-08-08_feature-improvements.md`
Verdict: REVISIT. Priority order per report: (1) publish toggle, (2) alt text, (3) view-site link (small, can ride alongside 1/2).

Verified against current code: `Page` already has a `published` boolean column + cast +
fillable (`app/Models/Page.php`), and `Admin/Pages/Index.svelte` already renders an
"Unveröffentlicht" badge — but `Admin\PageController::store()`/`update()` never accept
`published`, and no UI sets it. `Section` currently stores **only** `page_id`/`key`/`value`
(`#[Fillable(['page_id', 'key', 'value'])]` in `app/Models/Section.php`) — `label`/`type`/`order`
are resolved live from `config/themes.php`, not persisted columns. Adding alt text is therefore
a real schema change, not just a UI tweak.

---

## Group 1 — Publish/draft toggle

### TASK-1
Accept `published` in `Admin\PageController::update()`'s validated payload (currently only
`template` is accepted/persisted).
- Files: `app/Http/Controllers/Admin/PageController.php`
- Dependencies: none
- Risk: **low** — additive validation rule on an already-fillable, already-cast boolean column; no migration needed.

### TASK-2
Add a "Veröffentlicht / Entwurf" toggle to `Pages/Edit.svelte`, wired to the endpoint from TASK-1 (mirror the existing `saveTemplate()` pattern in that file).
- Files: `resources/js/Pages/Admin/Pages/Edit.svelte`
- Dependencies: TASK-1
- Risk: **low** — isolated UI addition, existing save/PATCH pattern already established in this file.

### TASK-3 (needs a product decision — do not guess)
Decide whether newly created pages should default to draft or published, and implement that default in `Admin\PageController::store()` (and optionally surface it in the create form in `Pages/Index.svelte`). The report explicitly flags this as "worth a product call... which default is safer vs. more convenient" — this is not specified anywhere else in the codebase or docs.
- Files: `app/Http/Controllers/Admin/PageController.php`, possibly `resources/js/Pages/Admin/Pages/Index.svelte`
- Dependencies: TASK-1
- Risk: **low**, but **blocked on a product decision** — implementing without confirming the default risks either (a) silently publishing unfinished pages (status quo bug) or (b) silently hiding new pages editors expect to be live immediately.

Explicitly out of scope per report: a draft/published/scheduled status dropdown — keep this a plain boolean.

---

## Group 2 — Image alt text (accessibility, THEMES.md §6)

### TASK-4 (needs a decision before starting)
Decide where alt text is stored: a new persisted column on `Section` (report's suggested "simplest" option, since a section owns exactly one image) vs. `custom_properties` on the spatie `Media` model. This determines the shape of TASK-5 through TASK-7. Note `Section` currently has no persisted columns beyond `page_id`/`key`/`value` — this is a genuine schema addition either way.
- Files: none (decision only)
- Dependencies: none
- Risk: n/a — flagging because guessing wrong here means redoing TASK-5–7.

**Coordinate with:** `2026-08-08_seo-findings.md` TASK-3 (meta description data source) hinges
on the same underlying pattern — a new persisted column on a tenant-scoped model, rolled out via
tenant migration to every existing tenant DB. Resolve both decisions together and reuse one
migration-rollout mechanism rather than building it twice. See `docs/tasks/README.md` §2.

### TASK-5
Write the tenant migration (and update `Section::Fillable`/model) implementing the TASK-4 decision — e.g. add nullable `alt` column to the `sections` table.
- Files: `database/migrations/tenant/*` (new migration), `app/Models/Section.php`
- Dependencies: TASK-4
- Risk: **medium** — a tenant migration must be run against every existing tenant SQLite database, not just a shared central DB; needs `php artisan tenancy:migrate` (or equivalent) across all tenants as part of rollout, not just merged code.

### TASK-6
Add an "Alt-Text" input alongside `ImageUpload.svelte`'s preview for `image`-type sections, and accept/persist it via `Admin\SectionController::update()` (currently validates only `value`).
- Files: `resources/js/Components/Admin/ImageUpload.svelte`, `resources/js/Components/Admin/SectionField.svelte` (to pass the value through), `app/Http/Controllers/Admin/SectionController.php`
- Dependencies: TASK-5
- Risk: **medium** — touches the save contract for sections; must not affect non-image section types.

### TASK-7
Update theme templates to render the editor-set alt text instead of hardcoded strings, and fall back to `alt=""` (decorative) rather than nothing when unset. Confirmed hardcoded instance: `resources/themes/tabubruch/templates/Home.svelte:60` (`alt="Maria Neunteufel"`). Full set of theme templates rendering section-driven `<img>` tags needs auditing (found so far: `geko/templates/Page.svelte`, `geko/templates/Home.svelte`, `geko/templates/Aktuelles.svelte`, `dvm/templates/Page.svelte`, `dvm/templates/Home.svelte`, `dvm/templates/Contact.svelte`, `tabubruch/templates/Home.svelte`, plus the `default` theme).
- Files: `resources/themes/*/templates/*.svelte`, `resources/themes/*/*.svelte`
- Dependencies: TASK-5, TASK-6 (data must exist to read)
- Risk: **medium** — spans every theme; must distinguish images an editor controls from genuinely decorative/brand assets that correctly already use `alt="" aria-hidden="true"` (report calls out `dvm/templates/Home.svelte:60` as an example to leave alone) — requires per-file judgment, not a blanket find/replace.

### TASK-8
Fix `TiptapEditor.svelte`'s inline image insert to default `alt` to an empty string instead of the raw uploaded filename. Confirmed at both call sites: `chain.insertContentAt(pos, { type: 'image', attrs: { src: url, alt: filename } })` and `chain.setImage({ src: url, alt: filename })`.
- Files: `resources/js/Components/Admin/TiptapEditor.svelte`
- Dependencies: none — independent of TASK-4–7, can be done first as a quick win
- Risk: **low** — two-line change, no schema/migration involved.

Explicitly out of scope per report: don't add a per-inline-image alt field to the rich text flow yet; don't touch decorative brand assets already correctly marked.

---

## Group 3 — View-site link (small, do alongside 1/2)

### TASK-9
Make the tenant domain label in `Layout.svelte`'s sidebar (`{domain}`, confirmed at line 67) a link to the live public site (`<a href="/" target="_blank" rel="noopener">`), or add a small "Website ansehen ↗" link near it.
- Files: `resources/js/Components/Admin/Layout.svelte`
- Dependencies: none
- Risk: **low** — presentational only, no backend change.

---

## Explicitly out of scope (per report — do not build speculatively)

- Multi-user/roles admin UI (inviting additional editors)
- SEO meta title/description fields (tracked separately in the SEO findings task list)
- Shared cross-page media library
- Scheduled publishing / draft-review workflow
