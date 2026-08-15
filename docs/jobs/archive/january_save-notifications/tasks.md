# Tasks: save notifications

id: january
status: open
analyst: architect
date: 2026-08-15

<!-- Produced by @analyst from brief.md. -->

## Context / investigation notes

- The admin save UX currently shows a per-section inline "Gespeichert" badge in the
  **header** of each section card (SectionField.svelte), config card (ConfigField.svelte),
  and post editor (PostEditor.svelte), while the save button sits at the **bottom** of the
  same card. On a page with a long section, the badge is out of view when the user presses
  save — exactly the brief's complaint ("you press the save button at the bottom, you see no
  indicator").
- The brief asks for two things: (1) the save button should always be in view (sticky), and
  (2) an always-visible success notification when saving ("Gespeichert" toast).
- Saves are per-resource via `router.patch`/`router.post` with `onSuccess` callbacks in
  SectionField.svelte, ConfigField.svelte, PostEditor.svelte, Pages/Admin/Pages/Edit.svelte
  (template + published), and NavList.svelte. There is currently **no** toast/notification
  component or store anywhere in `resources/js` — the shared `flash` prop exists but is only
  rendered as an inline banner on Pages/Index and Navigation/Index.
- There is no frontend test runner; verification is `npx svelte-check --tsconfig ./tsconfig.json`
  (0 errors/warnings) plus a manual flow against a scratch dev tenant.
- Design tokens for the toast should reuse the existing admin CSS variables
  (`--color-admin-success`, `--color-admin-card`, `--radius-admin-card`, `--shadow-admin-card`)
  from `resources/css/admin.css`.

## Task breakdown

TASK-1: Create a module-level toast store in `resources/js/lib/toast.svelte.ts` (Svelte 5 runes)
using `$state` — expose reactive toast state (`message`, `visible`) and a `showToast(message)`
function that shows the toast and auto-dismisses after ~2.5s (mirrors the 2s pattern already used
by the inline "Gespeichert" badges).
     files: resources/js/lib/toast.svelte.ts (new)
     depends: none
     risk: low — new, self-contained module; Svelte 5 runes in `.svelte.ts` files are supported by
     the installed svelte 5 + svelte-check setup.

TASK-2: Create `resources/js/Components/Admin/Toast.svelte` — a fixed-position toast (e.g.
top-right) reading the TASK-1 store, with `role="status"`/`aria-live="polite"` for screen readers,
styled with the existing admin tokens — and mount it once in
`resources/js/Components/Admin/Layout.svelte` so every admin page renders it.
     files: resources/js/Components/Admin/Toast.svelte (new), resources/js/Components/Admin/Layout.svelte
     depends: TASK-1
     risk: low-medium — new component; must use a z-index above the existing z-50 modals
     (PostsManager, ConfirmDialog) so saves made inside modals are still visible, and the toast must
     sit outside the `max-w-4xl` content container.

TASK-3: Make the section save button always in view — in `SectionField.svelte`, make the save
button sticky to the bottom of the viewport (e.g. `sticky bottom-4` on the button wrapper) so it
stays visible while editing a long section on the page editor.
     files: resources/js/Components/Admin/SectionField.svelte
     depends: none
     risk: medium — `position: sticky; bottom` pins each button only while its own card is on
     screen, but two tall section cards visible simultaneously could show two pinned buttons at
     once; needs visual verification (see open questions for the alternative).

TASK-4: Trigger the toast on section save success — in `SectionField.svelte`'s `speichern()`
`onSuccess`, call `showToast('Gespeichert')` (the existing inline badge stays — no removal).
     files: resources/js/Components/Admin/SectionField.svelte
     depends: TASK-1, TASK-2, TASK-3 (same file — implement after TASK-3 to avoid conflicts)
     risk: low — additive `onSuccess` call in an existing flow.

TASK-5: Trigger the toast on settings save success — in `ConfigField.svelte`'s `speichern()`
`onSuccess`, call `showToast('Gespeichert')` (inline badge stays).
     files: resources/js/Components/Admin/ConfigField.svelte
     depends: TASK-1, TASK-2
     risk: low — same additive pattern as TASK-4.

TASK-6: Trigger the toast on post save success — in `PostEditor.svelte`'s `save()` `onSuccess`,
call `showToast('Gespeichert')` (inline badge stays).
     files: resources/js/Components/Admin/PostEditor.svelte
     depends: TASK-1, TASK-2
     risk: low — same additive pattern as TASK-4.

TASK-7: Trigger the toast on page template/published saves — in
`Pages/Admin/Pages/Edit.svelte`, `saveTemplate()` and `savePublished()` `onSuccess` call
`showToast('Gespeichert')` (inline badges stay).
     files: resources/js/Pages/Admin/Pages/Edit.svelte
     depends: TASK-1, TASK-2
     risk: low — same additive pattern as TASK-4.

TASK-8: Verification — run `npx svelte-check --tsconfig ./tsconfig.json` (expect 0 errors /
0 warnings in every touched file) and manually verify against a scratch dev tenant: saving a long
section shows the toast in a fixed viewport position regardless of scroll; the sticky save button
stays in view while editing and does not overlap across sections.
     files: none (verification only)
     depends: TASK-2 through TASK-7
     risk: low — verification step; this is what confirms or disproves TASK-3's overlap concern.

## Open questions for @analyst / requester before implementation

- Sticky save button scope: per-section sticky buttons (the approach above) vs. a single
  page-level sticky "Speichern" bar in the page editor. A page-level bar would need either a new
  batch-save backend route or client-side tracking of which sections changed — a noticeably larger
  change than per-card sticky buttons.
- Should the sticky button also apply to ConfigField (settings) and PostEditor for consistency?
  Currently scoped to SectionField only, matching the brief's "editing a page" wording.
- Keep or remove the per-section inline "Gespeichert" badges now that a global toast exists?
  Currently kept (no removal) — the brief only asks to *add* an always-visible notification.
- Toast placement (top-right vs. bottom-center) and auto-dismiss duration (~2.5s suggested to
  match the existing 2s badges) — defaults chosen unless directed otherwise.
- NavList inline "Speichern" and PostsManager "Hinzufügen" currently have no success indicator at
  all; wiring the toast there too would make behaviour uniform but is not required by the brief.
