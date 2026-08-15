## Summary

Added an always-visible success notification ("Gespeichert" toast) to the admin UI and made
the section save button stay in view while editing, addressing the brief's complaint that
saving at the bottom of a long section showed no feedback because the inline "Gespeichert"
badge sits at the top of the card.

The core is a new module-level toast store (`resources/js/lib/toast.svelte.ts`) backed by
Svelte 5 runes at module scope, plus a single `Toast.svelte` mounted once in the admin
`Layout.svelte`. Every per-resource save flow (section, settings config, post/event,
page template + published toggle) now calls `showToast('Gespeichert')` in its `onSuccess`,
so the toast appears in a fixed top-right position regardless of scroll position — including
saves made inside the z-50 `PostsManager`/`ConfirmDialog` modals (the toast sits at
`z-[60]`). The existing inline per-card badges are kept untouched.

The section save button is now wrapped in a `sticky bottom-4` container, so it pins to the
bottom of the viewport while its section card is on screen and only scrolls away with the
card. The same pattern was intentionally *not* applied to `ConfigField`/`PostEditor`
(matching the brief's "editing a page" wording).

## Changes

TASK-1: New `resources/js/lib/toast.svelte.ts` — module-level toast store.
- Exports `toast` (`$state({ message, visible })`) and `showToast(text)`, which sets the
  message, makes the toast visible and auto-dismisses after ~2.5s (mirrors the 2s pattern
  of the existing inline badges). State lives in one `$state` object because Svelte 5's
  compiler rejects exporting a module-level `$state` binding that is reassigned
  (`state_invalid_export`).

TASK-2: New `resources/js/Components/Admin/Toast.svelte` + mount in
`resources/js/Components/Admin/Layout.svelte`.
- Fixed top-right toast (`role="status"`, `aria-live="polite"`) reading the store; styled
  with the existing admin tokens (`bg-admin-card`, `border-admin-border`,
  `rounded-admin-card`, `shadow-admin-card`, `text-admin-success` checkmark icon) and
  `z-[60]` so it paints above the z-50 modals. Mounted once in the admin layout, outside
  the `max-w-4xl` content container, so every admin page renders it.

TASK-3: `resources/js/Components/Admin/SectionField.svelte` — wrapped the save button in a
`sticky bottom-4` container so it stays pinned to the viewport bottom while editing a long
section. Verified no ancestor of the card sets `overflow` (which would break sticky).

TASK-4: `SectionField.svelte` — `speichern()` `onSuccess` now also calls
`showToast('Gespeichert')`; inline badge kept.

TASK-5: `resources/js/Components/Admin/ConfigField.svelte` — `speichern()` `onSuccess` calls
`showToast('Gespeichert')`; inline badge kept.

TASK-6: `resources/js/Components/Admin/PostEditor.svelte` — `save()` `onSuccess` calls
`showToast('Gespeichert')`; inline badge kept.

TASK-7: `resources/js/Pages/Admin/Pages/Edit.svelte` — both `saveTemplate()` and
`savePublished()` `onSuccess` call `showToast('Gespeichert')`; inline badges kept.

TASK-8: Verification.
- `npx svelte-check --tsconfig ./tsconfig.json`: 0 errors / 0 warnings in every touched
  file. (The project has 7 pre-existing errors in `resources/js/Themes/dvm/Home.svelte`
  — an unrelated theme file, identical before and after this job.)
- `npm run build` succeeds; the compiled admin CSS contains the toast utilities
  (`position:fixed`, `z-index:60`) and the sticky button utilities (`position:sticky`,
  `bottom:calc(var(--spacing) * 4)`).
- The store was compiled with `svelte/compiler`'s `compileModule` to confirm the runtime
  output is valid (module-level `$state` + property mutation is the supported pattern).
- Note: a full click-through against a scratch dev tenant was not possible in this
  environment (no browser); the sticky-button overlap question from the open issues
  (two tall cards visible at once) remains to be eyeballed once in a real browser.

## Known issues / follow-ups

- The overlap question flagged in the tasks stays open: two tall section cards visible at
  the same time can each pin their own sticky save button to the viewport bottom at once.
  Realistically the user edits one section at a time, and each button scrolls away with its
  card, so overlap is a narrow edge case — but it should be eyeballed in a browser before
  shipping. If it's a problem, the follow-up is a single page-level sticky save bar with
  client-side change tracking (noted in the tasks' open questions).
- The `NavList` inline "Speichern" and `PostsManager` "Hinzufügen" flows still have no
  success indicator at all; wiring the toast there too would make behaviour uniform but was
  explicitly out of scope for this brief.
- The `dvm` theme's `Home.svelte` has 7 pre-existing `svelte-check` errors unrelated to
  this job; fixing them is a separate concern.
