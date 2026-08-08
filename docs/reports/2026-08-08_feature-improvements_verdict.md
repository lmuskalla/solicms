# Verdict: Feature improvements (publish control, alt text, view-site link)

Reviewed against `docs/reports/2026-08-08_feature-improvements.md`,
`docs/tasks/2026-08-08_feature-improvements.md`, and
`docs/reports/2026-08-08_feature-improvements_result.md`.

## Group 1 — Publish/draft toggle

TASK-1: PASS
notes: `app/Http/Controllers/Admin/PageController.php::update()` validates `template` as
`sometimes|required` and `published` as `sometimes|boolean`, both applied via `$page->update()`.
Correctly allows the two to be saved independently, as the result doc claims.

TASK-2: PASS
notes: `resources/js/Pages/Admin/Pages/Edit.svelte` has a toggle switch (lines ~120-149) showing
"Entwurf"/"Veröffentlicht", wired to a `savePublished()` function that mirrors the existing
`saveTemplate()` PATCH pattern, with its own `publishSaved` confirmation state. Correct and
isolated from the template-save flow.

TASK-3: PASS
notes: `PageController::store()` explicitly sets `'published' => false` for newly created pages,
with a clear comment explaining the reasoning (new pages start with blank sections and would
otherwise be instantly live/indexable). Confirmed `TenantProvisioner`'s seeded starter pages
don't set `published` explicitly, so they still rely on the column's `true` default and remain
published-by-default as before — verified in `app/Services/TenantProvisioner.php`. Confirmed no
toggle was added to `Pages/Index.svelte`'s create form (grep shows `published` only used for the
existing "Unveröffentlicht" badge display) — matches the stated decision to keep the create form
simple. This is a genuine, defensible product decision, clearly documented, and correctly
implemented — not a case of guessing silently.

## Group 2 — Image alt text

TASK-4: PASS
notes: Decision-only task. Result doc chose a new `Section.alt` column over `Media` custom
properties, with clear reasoning (one image per section, reuses the existing save path). Consistent
with what was actually built in TASK-5/6.

TASK-5: PASS
notes: `database/migrations/tenant/2024_01_01_000014_add_alt_to_sections_table.php` adds a
nullable `alt` string column after `value`. `app/Models/Section.php`'s `#[Fillable(...)]` updated
to include `alt`. Migration has **not been run** against any existing tenant database — verified
directly against `database/tenants/*.sqlite`: the `sections` table on the `geko` tenant DB still
has no `alt` column. This matches what the result doc says ("not yet run"), and is explained by
the same non-functional `.env` sandbox limitation as the security report's TASK-4 (verified: `.env`
is a character device file here, not a real env file). Still, this is a real gap between "code
merged" and "feature actually usable" that needs to be tracked as a deploy step
(`php artisan tenants:migrate`), not silently assumed done.

TASK-6: PASS
notes: `resources/js/Components/Admin/ImageUpload.svelte` adds a bindable `alt` prop with a text
input shown once an image exists. `resources/js/Components/Admin/SectionField.svelte` threads
`alt` through and only includes it in the PATCH payload when `section.type === 'image'`
(`section.type === 'image' ? { value, alt } : { value }`). `Admin\SectionController::update()`
validates `alt` (`nullable|string|max:255`) and drops it via `unset()` for any non-image section.
Correct, and I traced the full request path (frontend payload → validation → persistence) to
confirm it's actually wired end-to-end, not just present in isolated files.

TASK-7: PASS
notes: Re-grepped `<img` across `resources/themes/` myself. Confirmed the 3 files the result doc
claims to have fixed now render `alt={sections.X.alt || ''}` instead of a hardcoded string:
`default/HomeStandard.svelte` (hero_image), `geko/templates/Home.svelte` (hero_image),
`tabubruch/templates/Home.svelte` (previously hardcoded `"Maria Neunteufel"`, now
`sections.ueber_mich_portrait.alt || ''`). Spot-checked the images the result doc says were
deliberately left alone — `dvm/templates/Page.svelte`'s logo watermark, `dvm/templates/Contact.svelte`'s
side image, `tabubruch/components/Hero.svelte`'s brand logo — all correctly retain
`alt="" aria-hidden="true"` (decorative) or a genuine static description, not tied to any
editor-controlled section. `geko/components/NewsCard.svelte` (Post image, out of scope per the
report) correctly left at `alt=""`, unmodified. Judgment calls here are sound.

TASK-8: PASS
notes: `resources/js/Components/Admin/TiptapEditor.svelte` — both call sites
(`insertContentAt`/`setImage`) now default `alt: ''` instead of the uploaded filename. Confirmed
directly.

## Group 3 — View-site link

TASK-9: PASS
notes: `resources/js/Components/Admin/Layout.svelte` — the sidebar domain label is now
`<a href="/" target="_blank" rel="noopener" title="Website ansehen">{domain}</a>`. Matches the
suggested fix exactly.

## Out-of-scope check

No evidence of speculative scope creep: no multi-user/roles UI, no SEO fields added here (tracked
separately), no shared media library, no scheduled-publishing status pipeline. `PostController`
changes visible in the diff (events/posts date validation) are pre-existing functionality, not
new work introduced by this task set, and aren't touched by this feature-improvements report.

## Process note

Same as the security verdict: no git commits exist in this repository at all. There is no way to
confirm from history that these 9 tasks landed as separate, reviewable commits. Code content is
consistent with the result doc's narrative, but this should be corrected going forward.

## Overall: APPROVED (with one required follow-up)

All 9 tasks are implemented correctly, match both the original report and the task breakdown, and
the two flagged product decisions (draft-by-default for new pages, `Section.alt` over `Media`
custom properties) are reasonable and clearly justified rather than guessed silently.

Required before this is fully live:
- **Run the TASK-5 migration** (`php artisan tenants:migrate`) against every existing tenant
  database. Until this runs, `alt` doesn't exist as a column anywhere, and any attempt to save an
  alt-text value in the admin UI will fail (or silently no-op depending on SQLite's schema
  strictness) against a real tenant.

Not blocking, but should be fixed:
- Commit per-task on this branch/repo going forward.
