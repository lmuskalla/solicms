# Tasks: wysiwyg editor floating nav

id: cap
status: open
analyst: architect
date: 2026-08-15

<!-- Produced by @analyst from brief.md. -->

## Context / investigation notes

- The wysiwyg toolbar (Fett, Kursiv, H2, H3, Liste, Nummeriert, Zitat, Link, Bild
  einfügen, Datei anhängen) lives in `resources/js/Components/Admin/TiptapEditor.svelte`
  — the `flex flex-wrap ... border-b` row at lines 181–205. It is the only wysiwyg
  toolbar in the app.
- `TiptapEditor` is used in exactly two places, which scroll in two different containers:
  - `SectionField.svelte` line 100 — the page editor (`Pages/Admin/Pages/Edit.svelte`).
    The page scrolls with the **viewport** (Layout's `<main>` has no inner scroll container).
  - `PostEditor.svelte` line 138 — the post/event editor inside the `PostsManager` modal,
    which scrolls in a nested `overflow-y-auto` container (`PostsManager.svelte` line 102 /
    `PostEditor.svelte` line 101, under `max-h-[85vh]`).
- The toolbar row is wrapped by `div.overflow-hidden.rounded-lg` (TiptapEditor line 181).
  `overflow: hidden` creates a clipping/scroll context that **prevents `position: sticky`
  from engaging** — the sticky element would stick relative to that wrapper, which never
  scrolls. This wrapper must be restructured before any sticky approach can work.
- After that restructure, pure-CSS `position: sticky; top: 0` is sufficient and works in
  both scroll contexts (viewport for the page editor; the modal's `overflow-y-auto` div
  for the post editor). No JS scroll listener is required. Sticky engages exactly when the
  toolbar would otherwise scroll out of view — the behavior the brief describes
  ("when scrolling down enough ... starts to float").
- Sticky-toolbar implementation notes: keep a solid background (the row already uses
  `bg-admin-bg`), add a `z-index` so editor content scrolls underneath, and split the
  outer `rounded-lg` into `rounded-t-lg` on the toolbar row / `rounded-b-lg` on the
  bottom of the content area so corners still clip once `overflow-hidden` is removed.
  The existing `border-b` already separates toolbar from content. A shadow that only
  appears while stuck would require a small scroll/IntersectionObserver check — an
  optional enhancement, not required by the brief.
- Because the change lands in the shared `TiptapEditor`, the floating behavior applies to
  both the page editor and the post-editor modal. The brief only mentions page editing;
  if the modal should NOT float, a per-instance opt-out prop is needed (open question).

## Task breakdown

TASK-1: Restructure the TiptapEditor wrapper so the toolbar can stick — drop `overflow-hidden` from the outer `div.rounded-lg` and move corner rounding onto the toolbar row (`rounded-t-lg`) and the bottom of the content area (`rounded-b-lg`) so borders/corners still render correctly.
     files: resources/js/Components/Admin/TiptapEditor.svelte
     depends: none
     risk: low — pure markup/class change with no behavior change; only risk is a cosmetic corner regression, needs a quick visual check in both usage contexts.

TASK-2: Make the toolbar float while scrolling — add `position: sticky; top: 0` plus a z-index to the toolbar row in TiptapEditor (keeping its solid `bg-admin-bg` background) so it stays pinned at the top of whichever scroll container it lives in (viewport for the page editor, the modal's `overflow-y-auto` area for the post editor) and editor content scrolls underneath without showing through.
     files: resources/js/Components/Admin/TiptapEditor.svelte
     depends: TASK-1
     risk: medium — sticky must behave correctly against two different scroll containers and must not overlap surrounding UI (the modal's own header bar, section labels); needs manual scroll testing in both contexts, and the `top: 0` offset may need a small gap to look right inside the modal.

TASK-3: Manual verification — run the dev server, edit a page with a long wysiwyg section and a long post/event body via the posts manager modal, scroll, and confirm: the toolbar floats at the top of the viewport (page editor) and of the modal's scroll area (post editor), toolbar buttons still work while floating, saving still works, and short editors show no visual regression (toolbar simply sits in place).
     files: none (manual/CLI verification only)
     depends: TASK-1, TASK-2
     risk: low — verification only; the one thing to watch is the modal context, where sticky `top: 0` must not overlap the modal's own header bar.

## Open questions for @analyst / requester before implementation

- Scope: the fix is in the shared `TiptapEditor`, so the floating toolbar also appears in the post/event editor modal. The brief only mentions page editing — is the modal in scope, or should it be excluded via a prop?
- Visual preference: is a plain sticky toolbar acceptable, or do we want a shadow/elevation that only appears once the toolbar is actually floating (requires a small scroll/IntersectionObserver check)?
- Top offset: the page editor has no fixed top header, so `top: 0` works, but confirm a small gap (e.g. 4–8px) isn't preferred for breathing room.
