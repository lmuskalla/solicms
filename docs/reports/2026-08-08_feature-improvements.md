# Handoff: Admin editing gaps — publish control, alt text, view-site link

  **From:** Product review (outside-in audit against CLAUDE.md/THEMES.md north star)
  **Status:** Not started
  **Priority:** 1 and 2 before onboarding another client; 3 is small/cheap, do alongside

  ## Context

  Audited the current admin editing experience against the actual end user (non-technical
  NGO content editor, WordPress-migrant). Two real gaps and one small friction point were
  found — not speculative features, but places where the implementation contradicts either
  user expectations or the project's own written rules (THEMES.md §6 accessibility baseline).

  Full review verdict: REVISIT. Everything else in the current admin (theme/section
  architecture, no separate Media/Users/Events admin sections per THEMES.md §8, settings
  scope) is correctly sized for the product and should NOT be expanded speculatively.

  ---

  ## 1. Publish/draft toggle is missing from the UI (highest priority)

  **Problem:** `pages.published` exists (`database/migrations/tenant/2024_01_01_000002_create_pages_table.php`,
  default `true`), is cast as boolean on `App\Models\Page`, is read by
  `Frontend\PageController` to gate public visibility, and even renders an "Unveröffentlicht"
  badge in `Admin/Pages/Index.svelte` — but nothing in the admin UI ever sets it. Confirmed via
  grep: no `published` field in `Admin\PageController::store()` or `::update()`, no checkbox
  in `Pages/Index.svelte` or `Pages/Edit.svelte`. The only place it's ever set is
  `TenantContentImporter` (data import), not normal editing.

  **User impact:** An editor creating a new page (e.g. building out a "Volunteer" page over
  several sessions) has it live on the public internet — potentially indexed by Google under
  the org's real domain — from the very first save, with no way to hide it while unfinished.
  For a WordPress-migrant audience expecting Draft/Publish, this is a real foot-gun, not a
  missing nice-to-have.

  **Suggested fix:**
  - Add a "Veröffentlicht / Entwurf" toggle to `Pages/Edit.svelte` (and optionally the create
    form in `Pages/Index.svelte`, defaulting new pages to draft — worth a product call on
    which default is safer vs. more convenient).
  - `Admin\PageController::update()` needs to accept `published` in its validated payload
    alongside `template`.
  - Frontend already gates correctly (`Frontend\PageController` filters `published = true`) —
    no changes needed there.
  - Keep it a plain boolean toggle, not a status dropdown (draft/published/scheduled) — no
    validated need for scheduling yet, don't build it speculatively.

  ## 2. Image alt text is not editor-controlled (accessibility gap)

  **Problem:** THEMES.md §6 declares accessibility a "non-negotiable" baseline for every
  theme, but editor-uploaded images have no alt text field anywhere in the admin:
  - `Components/Admin/ImageUpload.svelte` renders its own preview with `alt=""` (line 64) —
    fine for the admin preview itself, but there's no field to capture real alt text to pass
    through to the public render.
  - Several theme components hardcode a literal alt string regardless of which image is
    actually uploaded, e.g. `resources/themes/tabubruch/templates/Home.svelte:60` —
    `alt="Maria Neunteufel"` — which goes stale the moment an editor replaces that image.
  - `Components/Admin/TiptapEditor.svelte` (lines 67, 69) sets inline image `alt` to the raw
    uploaded **filename** (e.g. `IMG_4821.jpg`) — actively worse than empty, since a screen
    reader reads it verbatim.
  - Most theme `<img>` tags for section-driven images use `alt=""` (decorative), which is
    only correct when the image is genuinely decorative — not guaranteed for e.g. a hero or
    body image that carries real meaning.

  **User impact:** Several real tenants (GEKO explicitly, per THEMES.md's own note about its
  multilingual/accessibility-conscious audience) plausibly have real accessibility
  obligations. Right now the tool works against them.

  **Suggested fix:**
  - Add an optional "Alt-Text" input alongside `ImageUpload.svelte`'s preview, for `image`-type
    sections. Store it either as a new column on `Section` (simplest — the section already
    owns one image's worth of content) or on the `Media` model's `custom_properties`.
  - Themes read that value for `alt` instead of a hardcoded string; fall back to empty string
    (decorative) rather than filename when no alt text is set.
  - Fix `TiptapEditor.svelte`'s inline-image insert to default `alt` to empty string, not
    filename, until/unless a similar per-image alt field is added to the rich text flow.
  - Scope this to images an editor actually controls (`Section`/`Post` images) — don't touch
    theme-hardcoded brand assets (logo, decorative background images already correctly using
    `alt=""` + `aria-hidden="true"`, e.g. `dvm/templates/Home.svelte:60`).

  ## 3. No "view site" link in the admin (small, do alongside 1/2)

  **Problem:** `Components/Admin/Layout.svelte` renders the tenant's domain as a plain label
  in the sidebar header (`{domain}`, line 67) but it's not a link. Nowhere in the admin is
  there a one-click way to open the live public site.

  **User impact:** After saving a change, the editor has no direct way to confirm what it
  looks like live — they have to know their own domain and open a new tab manually. Small
  friction, but closes a real feedback-loop gap given there's no live preview elsewhere in
  the editing flow.

  **Suggested fix:** Make the domain label in `Layout.svelte`'s sidebar an `<a href="/"
  target="_blank" rel="noopener">`, or add a small "Website ansehen ↗" link near it /on
  `Pages/Edit.svelte`. Trivial change, no backend work needed.

  ---

  ## Explicitly out of scope — do not build speculatively

  Flagging so this doesn't turn into feature creep once someone's in this code:
  - Multi-user/roles admin UI (invite additional editors) — no validated client need yet.
  - SEO meta title/description fields — no validated client need yet.
  - Shared cross-page media library — current section-scoped media model is intentional and
    matches the simplicity mandate (see THEMES.md).
  - Scheduled publishing / draft-review workflow — a plain boolean publish toggle (item 1
    above) is sufficient; don't build a status pipeline nobody's asked for.
