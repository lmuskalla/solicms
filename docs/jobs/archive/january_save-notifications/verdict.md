# Verdict: save notifications

id: january
status: approved
reviewer: deepseek-v4-flash
date: 2026-08-15

<!-- Produced by @reviewer and/or @security after implementation. -->

## Review

Reviewed against `main` (4d0a105): `git diff main...HEAD` touches exactly the 11 files the
tasks specify (brief/implementation/tasks/verdict docs + 7 code files). No scope creep.

TASK-1: PASS
notes: `resources/js/lib/toast.svelte.ts` — module-level `$state({ message, visible })` + `showToast()`
with `clearTimeout` + 2500ms auto-dismiss. The final form (post-TASK-8 fix) correctly avoids Svelte 5's
`state_invalid_export` by mutating properties of a single exported `$state` object instead of reassigning
exported module-level `$state` bindings (which is what the initial TASK-1 commit did and would fail to
compile). This is the documented Svelte 5 pattern. Importing `../../lib/toast.svelte` resolving to
`toast.svelte.ts` is also the documented pattern.

TASK-2: PASS
notes: `resources/js/Components/Admin/Toast.svelte` — `fixed right-4 top-4 z-[60]`, `role="status"`
+ `aria-live="polite"`. All classes map to existing tokens in `resources/css/admin.css` @theme
(`--color-admin-card`, `--color-admin-border`, `--radius-admin-card`, `--shadow-admin-card`,
`--color-admin-success`, `--color-admin-text`); the file sits under `Components/Admin/` so it's covered
by admin.css's `@source '../js/Components/Admin/**/*.svelte'` glob. `z-[60]` is above the z-50
PostsManager/ConfirmDialog modals (verified both use `z-50`). Mounted once in `Layout.svelte` after
`</main>`, outside the `max-w-4xl` container. No ancestor of the mount point has `transform`/`filter`,
so `position: fixed` resolves to the viewport. Every admin page with a save flow mounts Layout
(Settings, Pages/Edit, Navigation/Index, Pages/Index, Index); Login is the only Layout-free admin page
and has no save flows.

TASK-3: PASS
notes: `SectionField.svelte` — save button wrapped in `<div class="sticky bottom-4 mt-4">`. Sticky is
not broken by any ancestor: Layout root (`flex min-h-screen …`), `<main>` (`flex-1 px-8 py-10`), the
`max-w-4xl` wrapper and the card (`rounded-admin-card … p-6`) all have no `overflow`/`transform`
(grep over `resources/css/*.css` and the svelte files finds no `overflow` anywhere). The button pins
against viewport scroll while its card is on screen, per the task's spec. Known edge case — two tall
cards visible at once can pin two buttons at the same `bottom-4` position (they would overlap) — is
exactly the risk the tasks' own open question acknowledged and accepted for the per-card approach;
requires the browser eyeball listed under TASK-8.

TASK-4: PASS
notes: `SectionField.svelte` `speichern()` `onSuccess` calls `showToast('Gespeichert')` after the
existing `saved` badge logic; inline badge kept.

TASK-5: PASS
notes: `ConfigField.svelte` `speichern()` `onSuccess` — same additive pattern, inline badge kept.

TASK-6: PASS
notes: `PostEditor.svelte` `save()` `onSuccess` — same additive pattern, inline badge kept.

TASK-7: PASS
notes: `Pages/Admin/Pages/Edit.svelte` — both `saveTemplate()` and `savePublished()` `onSuccess`
call `showToast('Gespeichert')`; inline badges kept.

TASK-8: PARTIAL
notes: The code portions are done and statically sound; I could not independently re-run
`svelte-check`/`build` (no `node_modules` in the workspace), but the claimed clean result is
consistent with the code — the store uses the supported Svelte 5 module-runes pattern and
svelte-check covers `resources/js/**/*.ts` including `toast.svelte.ts`. The manual browser
click-through against a scratch tenant was NOT performed (honestly disclosed in implementation.md);
as a result the visual behavior of the sticky button (especially the two-tall-cards overlap edge case
from TASK-3) and the toast in a real browser remain unverified.

## Security

- No secrets, credentials or tenant data introduced. Changes are client-side UI only
  (`showToast` calls + a `$state` store + CSS classes); no backend/route/model/DB changes, no
  auth/authorization surface touched.
- No new dependencies added (`package.json`/`composer.*` untouched).
- Toast content is a hardcoded string `'Gespeichert'` — no user-controlled input rendered into it.
- Nothing staged/committed that shouldn't be (see working-tree observation below).

## Overall

APPROVED — cleared to merge.

All eight tasks are implemented correctly and match the brief's two asks: (1) the section save button
is now sticky to the viewport bottom while its card is on screen, and (2) a fixed top-right
"Gespeichert" toast fires on every save flow (section, settings, post, page template/published),
visible regardless of scroll position — including saves made inside the z-50 modals. Commit discipline
is exemplary: one `[january] TASK-N: …` commit per task plus a dedicated `[january] implementation:`
commit. The only TASK-8 gap is the browser click-through, which this environment cannot perform and
which the developer disclosed — it should be done before relying on this UI in production, but is not
a code defect.

Observations (not blockers, not part of this branch's commits):
- `docs/jobs/january_save-notifications/tasks.md` — the analyst's full task breakdown exists only as an
  uncommitted working-tree change; HEAD still has the empty scaffold template. The breakdown should be
  committed (it is the reference this implementation was built and reviewed against).
- `AGENTS.md` — empty on `main`, populated in the working tree with the platform build plan; an
  environment-injected artifact, unrelated to this job. Neither file was touched by this branch's commits.

Fast-follow (before shipping, track separately): eyeball the page editor in a real browser — confirm
the toast renders fixed in the viewport and that two tall section cards do not visibly overlap their
pinned save buttons; if they do, revisit the page-level sticky save bar noted in tasks.md's open questions.
