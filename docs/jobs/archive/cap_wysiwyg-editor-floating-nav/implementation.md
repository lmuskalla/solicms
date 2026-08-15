## Summary

Made the Tiptap WYSIWYG editor's formatting toolbar float (stick to the top of the
scroll container) so it stays visible when editing long content, instead of scrolling
out of view. The change lands in the shared `TiptapEditor.svelte`, so both usage
contexts benefit: the page editor (`SectionField` → `Admin/Pages/Edit.svelte`, which
scrolls with the viewport) and the post/event editor modal (`PostEditor` inside
`PostsManager`, which scrolls in its own `overflow-y-auto` area).

The root cause was that the toolbar's outer wrapper used `overflow-hidden`, which
creates a clipping/scroll context that prevents `position: sticky` from ever engaging.
Removing it and splitting the corner rounding onto the toolbar row and the bottom of
the content area lets pure-CSS `position: sticky; top: 0` work — no JS scroll listener
needed. The sticky toolbar engages exactly when it would otherwise scroll out of view,
which is the behavior the brief describes.

## Changes

TASK-1: Restructure `TiptapEditor.svelte`'s wrapper so the toolbar can stick.
- Removed `overflow-hidden` from the outer `div.rounded-lg` (it created a clipping
  context that prevented `position: sticky` from engaging — the toolbar would have
  stuck relative to a wrapper that never scrolls).
- Moved the top corner rounding onto the toolbar row (`rounded-t-lg`) so its opaque
  `bg-admin-bg` background no longer squares off the outer border's rounded corners
  now that `overflow-hidden` no longer clips it.
- Added `rounded-b-lg` to the bottom-most element (the upload hint line) so the
  bottom corners keep matching the outer border. No behavior change — pure markup.

TASK-2: Make the toolbar float while scrolling.
- Added `sticky top-0 z-10` to the toolbar row. `position: sticky` pins it to the top
  of whichever scroll container it lives in (the viewport for the page editor; the
  modal's `overflow-y-auto` area for the post editor) once it would otherwise scroll
  out of view. `top: 0` is safe in the modal too — the modal's header bar is a
  sibling *outside* the scroll area, so the toolbar can never overlap it. The existing
  solid `bg-admin-bg` background keeps scrolling content from showing through, the
  existing `border-b` separates toolbar from content, and `z-10` guarantees the
  toolbar paints above the content scrolling underneath. Short editors are unaffected:
  sticky only engages once scrolling is actually needed.

TASK-3: Verification (no file changes, per tasks.md).
- `npm ci` + `npm run build` (vite production build) passes.
- `npx svelte-check` reports zero errors in `TiptapEditor.svelte` or any other file
  touched by this job. (The 7 reported errors are all pre-existing in the untouched
  `resources/js/Themes/dvm/Home.svelte` theme file.)
- Confirmed Tailwind generated `.sticky{position:sticky}`, `.top-0{top:0}` and
  `.z-10{z-index:10}` in the admin stylesheet, i.e. the new classes are picked up.
- Confirmed both usage contexts: the admin `Layout`'s `<main>` has no fixed header and
  no inner scroll container (viewport scrolling, so `top: 0` is correct), and the
  `PostsManager` modal's scroll area is `overflow-y-auto` under a fixed header (sticky
  `top: 0` engages against that container without overlapping the header).
- A real browser scroll test in both contexts (long wysiwyg section in the page
  editor, long post body in the posts modal, button/save behavior while floating)
  still needs to be done by a human in a running dev environment.

## Known issues / follow-ups

- `git add -A` on the TASK-1 commit also swept in two pre-existing uncommitted
  workspace files (`AGENTS.md`, and `tasks.md` as updated by the analyst) that were
  sitting in the worktree before this job's code changes. They are legitimate project
  docs and are now committed — flagging so the extra diff isn't a surprise.
- The brief only mentions page editing, but because the fix is in the shared
  `TiptapEditor`, the floating toolbar also appears in the post/event editor modal.
  Per the tasks.md open question this was treated as in-scope (the brief's UX goal
  applies equally there); if it should be excluded, a per-instance opt-out prop
  (`sticky`/`float` prop, default true) would be the way — not done here.
- No shadow/elevation appears while the toolbar is floating (plain sticky). The
  optional shadow-on-stuck enhancement would need a small scroll/IntersectionObserver
  check — left out per tasks.md (not required by the brief).
