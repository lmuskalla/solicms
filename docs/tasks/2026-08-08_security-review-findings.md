# Tasks: Security review findings

Source: `docs/reports/2026-08-08_security_review_findings.md`
Priority per report: fix the stored-XSS finding before this goes live for real clients; the
zip-import and dev-login items are lower severity / lower urgency.

Verified against current code: `SectionController::update()` and `PostController::update()`
both validate the rich-text fields as `['nullable', 'string']` only — no sanitizer applied.
Confirmed no HTML sanitizer package present in either `composer.json` (no htmlpurifier/purifier)
or `package.json` (no dompurify). Confirmed `{@html sections.body.value}` in
`resources/themes/default/Wysiwyg.svelte:27`; other theme templates listed in the report were
not individually re-verified line-by-line and should be re-confirmed via grep before starting
TASK-2/TASK-3/TASK-5 below, since the report itself says "e.g." (non-exhaustive).

**Scheduling note:** Group 1 below (TASK-1 → TASK-2 → TASK-3) describes an already-exploitable,
currently-live vulnerability — not a UX gap. Schedule it ahead of the work in
`2026-08-08_feature-improvements.md` and `2026-08-08_seo-findings.md`, regardless of those
files' own internal priority notes. See `docs/tasks/README.md` §1 for the full cross-report
sequencing rationale.

---

## Group 1 — [HIGH] Stored XSS via unsanitized WYSIWYG content

### TASK-1
Select and add a server-side HTML sanitizer library restricted to the tag/attribute allow-list Tiptap's configured extensions actually produce (StarterKit: bold/italic/headings/lists/blockquote; plus `Link`, `Image`, and the plain `<a>` markup `TiptapEditor.svelte`'s `insertUploaded()` generates for non-image file attachments).
- Files: `composer.json`/`composer.lock` (new dependency), likely a new `config/purifier.php`-equivalent
- Dependencies: none
- Risk: **medium** — allow-list must be verified against actual Tiptap output (StarterKit + Link + Image + FileHandler-inserted `<a>` tags) or legitimate content will be stripped; get this list from `resources/js/Components/Admin/TiptapEditor.svelte`'s `onMount` extensions array, not from memory.

### TASK-2
Apply sanitization in `Admin\SectionController::update()` before persisting `value`, scoped to `wysiwyg`-type sections only (non-wysiwyg types — text/textarea/email/url/image — should not contain HTML at all and shouldn't be run through a sanitizer that could alter plain text unexpectedly).
- Files: `app/Http/Controllers/Admin/SectionController.php`
- Dependencies: TASK-1
- Risk: **medium** — must correctly branch on `$section->type`; incorrect scoping either leaves the hole open (wrong type check) or mangles plain-text sections.

### TASK-3
Apply the same sanitization in `Admin\PostController::update()` before persisting `body`.
- Files: `app/Http/Controllers/Admin/PostController.php`
- Dependencies: TASK-1
- Risk: **medium** — same shape as TASK-2, single field, lower branching complexity since `Post::body` is always rich text when present.

### TASK-4
Backfill/sanitize already-persisted content: every existing `Section.value` (wysiwyg type) and `Post.body` row, across **every** tenant database, was saved before this fix existed and may already carry unsanitized HTML from a benign editor (or worse). Needs a new Artisan command that loops tenants (`tenancy()->initialize()`/`end()`, matching the pattern in `SetupTenant`/`TenantContentImporter`) and re-saves sanitized values.
- Files: new file, e.g. `app/Console/Commands/SanitizeStoredContent.php`
- Dependencies: TASK-1, TASK-2, TASK-3
- Risk: **high** — mutates real, already-live content across every tenant's production database in one pass. Needs a dry-run/report mode and a confirmed backup immediately before running (per the existing `spatie/laravel-backup` setup); a sanitizer allow-list that's too aggressive will visibly corrupt real client pages. Don't bundle this into the same work session as unrelated feature/SEO changes — see `docs/tasks/README.md` §1.

### TASK-5 (optional, report frames as "and/or" — defense in depth, not a substitute for TASK-1–3)
Add client-side sanitization (e.g. DOMPurify) immediately before every `{@html}` usage in theme templates, as a second layer independent of server-side trust. Confirmed usage: `resources/themes/default/Wysiwyg.svelte:27`. Report also names `geko/templates/Page.svelte`, `dvm/templates/Page.svelte`, `dvm/templates/Contact.svelte`, `dvm/templates/Aktuelles.svelte` as other `{@html}` sites — re-grep for `{@html` across `resources/themes/` before starting, since the report's list is illustrative, not exhaustive.
- Files: `package.json` (add dompurify), all files matching `{@html` in `resources/themes/**/*.svelte`
- Dependencies: none (can proceed independently of TASK-1–4, though lower priority since server-side sanitization at the write path is the actual fix)
- Risk: **medium** — touches every theme's public-facing templates; needs the full, re-verified list of `{@html}` call sites first, not the report's partial example list.

### TASK-6
Decide whether `TenantContentImporter`'s import path (`Section.value`/`Post.body` written directly from `manifest.json` inside an imported zip, bypassing the controllers entirely) should also run through the TASK-1 sanitizer for consistency.
- Files: `app/Services/TenantContentImporter.php` (`recreate()`, around lines 166–239)
- Dependencies: TASK-1
- Risk: **low** — this is a CLI/operator-only path (not web-reachable), so urgency is lower than TASK-2/3, but leaving it unsanitized means a hostile/compromised export re-opens the same hole via `tenant:import`-equivalent tooling.

---

## Group 2 — [NEEDS REVIEW] Zip extraction: possible zip-slip / path traversal

Report notes this is CLI-only, operator-invoked (not web-reachable) — real-world exploitability depends on an operator importing an untrusted archive. Worth hardening regardless.

### TASK-7
Harden `TenantContentImporter::extract()` (confirmed at `app/Services/TenantContentImporter.php:98-117`) to reject zip entries containing `..` or absolute paths, and verify each extracted file's real path stays within `$extractDir` after extraction.
- Files: `app/Services/TenantContentImporter.php`
- Dependencies: none
- Risk: **low-medium** — operator-only attack surface, but the fix itself (path validation) is low-complexity and self-contained.

### TASK-8
Constrain `$item['archive_file']` (manifest-driven, used unvalidated in `recreateMedia()`, confirmed at `app/Services/TenantContentImporter.php:246-256`, building `$extractDir.'/media/'.$item['archive_file']`) via `basename()` or a strict `^[\w.\-]+$` allow-list before it's used in a path.
- Files: `app/Services/TenantContentImporter.php`
- Dependencies: none — can be done alongside TASK-7 in the same pass
- Risk: **low**.

---

## Group 3 — [LOW] Local dev-login backdoor always registered

Report states the existing runtime guard (`abort_unless(app()->environment('local'), 404)` in `AuthController::devLogin()`, confirmed at `app/Http/Controllers/Admin/AuthController.php:82`) is sound and "no code change strictly required." These are belt-and-suspenders items only.

### TASK-9
Additionally guard the `/admin/dev-login` route registration itself (confirmed at `routes/tenant.php:53`) so it isn't registered at all outside a local environment, rather than relying solely on the in-controller check.
- Files: `routes/tenant.php`
- Dependencies: none
- Risk: **low** — but confirm this is actually wanted before starting; the report explicitly frames it as optional hardening on top of an already-sound guard, not a required fix.

### TASK-10
Add an explicit post-deploy checklist item verifying `APP_ENV=production` in the deployment docs.
- Files: unclear — need to locate the actual deployment documentation first (candidates: `deployment/` directory, `docs/DESIGN.md`); this repo's existing deployment docs were not reviewed as part of this task breakdown.
- Dependencies: none
- Risk: **low** — documentation-only, no code risk, but scope of "which doc file" needs confirming before writing anything.

---

## No action needed (reviewed and found clean per report)

File uploads/MediaController, tenant DB isolation, auth (bcrypt, rate limiting, session regen, no user enumeration, CSRF), mass assignment (explicit `Fillable`/`$validated` throughout), secrets/config handling. Not included as tasks.
