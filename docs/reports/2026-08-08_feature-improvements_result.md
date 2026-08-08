# Result: Admin editing gaps — publish control, alt text, view-site link

Implements `docs/tasks/2026-08-08_feature-improvements.md` in full, after the security work
(per `docs/tasks/README.md` §1).

## Group 1 — Publish/draft toggle

- **TASK-1**: `Admin\PageController::update()` now accepts `published` (in addition to
  `template`, which is now `sometimes` so the two can be saved independently).
- **TASK-2**: Added a toggle switch to `Pages/Edit.svelte` (mirrors the existing
  `saveTemplate()`/`saved` pattern) — shows "Entwurf"/"Veröffentlicht" and saves immediately on
  click.
- **TASK-3 (product decision)**: New editor-created pages now default to **draft**
  (`published => false` in `PageController::store()`), overriding the `pages.published` column's
  own DB-level default of `true`. Reasoning: a freshly created page has blank sections (see
  `provisionSections()`) and is immediately reachable/indexable at `/{slug}` regardless of
  whether it's in any nav menu — going live with no content by default is the worse failure
  mode of the two the report flagged. `TenantProvisioner`'s seeded starter pages (home/about/
  contact) are unaffected — they don't set `published` explicitly and still rely on the column
  default, so a freshly provisioned tenant's site is live immediately, as before. I didn't add a
  toggle to the create form (`Pages/Index.svelte`) — the editor lands on `Pages/Edit.svelte`
  right after creating a page and can publish there once ready, which keeps the create form itself
  simple, matching the "genuinely simple, non-technical" goal in `docs/CLAUDE.md`.

## Group 2 — Image alt text

- **TASK-4 (decision)**: Alt text stored as a new persisted column on `Section` (the report's own
  "simplest" option), not on the Media model's `custom_properties`. A section owns exactly one
  image for the `image` type, so this needed no polymorphic lookup and reuses the exact same
  save/validate path `value` already has.
- **TASK-5**: New tenant migration
  `database/migrations/tenant/2024_01_01_000014_add_alt_to_sections_table.php` (nullable
  `sections.alt`), `Section::Fillable` updated. **Not yet run against any real tenant database** —
  same `.env` limitation as the security report's TASK-4; run
  `php artisan tenants:migrate` (already configured in `config/tenancy.php` to target
  `database/migrations/tenant`) against every tenant before this is usable in production.
- **TASK-6**: `ImageUpload.svelte` gained a bindable `alt` prop + text input (shown once an image
  is set); `SectionField.svelte` threads it through and only includes `alt` in the PATCH payload
  for `type === 'image'`; `Admin\SectionController::update()` accepts `alt` but drops it for any
  non-image section.
- **TASK-7**: Re-audited every theme for section-driven `<img>` tags (grepped `<img` across
  `resources/themes/`, more thorough than the report's partial list). Updated the 3 that render an
  editor-set image with an actually-wrong/hardcoded alt: `default/HomeStandard.svelte`
  (`hero_image`), `geko/templates/Home.svelte` (`hero_image`), `tabubruch/templates/Home.svelte`
  (`ueber_mich_portrait`, previously hardcoded to `"Maria Neunteufel"`). Left every static/brand
  asset alone (`dvm`'s logo watermarks, `dvm/templates/Contact.svelte`'s `contact.png`,
  `tabubruch`'s Hero logo, `geko`'s footer icon) — these are already correctly either
  `alt="" aria-hidden="true"` (decorative) or a real, non-editor-controlled description. `Post`
  images (`geko/components/NewsCard.svelte`) are out of scope — the report only covers `Section`
  alt text.
- **TASK-8**: `TiptapEditor.svelte`'s `insertUploaded()` now defaults inline image `alt` to `''`
  instead of the raw filename, at both call sites.

## Group 3 — View-site link

- **TASK-9**: The tenant domain label in `Layout.svelte`'s sidebar is now an
  `<a href="/" target="_blank" rel="noopener">` link to the live public site.

## Not done / explicitly deferred

- **TASK-5 rollout**: migration written, not yet executed against any tenant DB — see above.
- Everything explicitly out-of-scope in the source report (multi-user admin UI, SEO meta
  fields — see the SEO result doc, shared media library, scheduled publishing) was left alone.
