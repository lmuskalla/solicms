# Verdict: Security review findings

Reviewed against `docs/reports/2026-08-08_security_review_findings.md`,
`docs/tasks/2026-08-08_security-review-findings.md`, and
`docs/reports/2026-08-08_security_review_findings_result.md`.

Method: no git history exists in this repo (see process note at the end), so review was done by
reading the actual current source for every file/line the result doc claims to have touched, and
cross-checking against the task list and original finding.

## Group 1 — Stored XSS

TASK-1: PASS
notes: `symfony/html-sanitizer` added to `composer.json`/`composer.lock` (confirmed package
present in lock file). `app/Services/WysiwygSanitizer.php` allow-lists exactly the elements
claimed (p, br, strong/b, em/i, s/strike/del/u, h1-h6, ul/ol/li, blockquote, code/pre, hr, `a`,
`img`), restricts link/media schemes to http/https(+mailto for links). Reasonable and matches
Tiptap's actual StarterKit output.

TASK-2: PASS
notes: `app/Http/Controllers/Admin/SectionController.php` sanitizes `value` only when
`$section->type === 'wysiwyg'`, and correctly drops `alt` for non-image types. Matches spec
exactly.

TASK-3: PASS
notes: `app/Http/Controllers/Admin/PostController.php::update()` sanitizes `body` unconditionally
whenever present. Matches spec.

TASK-4: PARTIAL
notes: `app/Console/Commands/SanitizeStoredContent.php` exists, implements `--dry-run`/`--tenant`,
loops tenants via `tenancy()->initialize()/end()`, and only writes on confirmation. Command itself
is correct and matches the task. However it has **not been run** against any tenant database —
verified directly: `database/tenants/*.sqlite` sections tables still lack any content that would
prove a pass was made, and this can't be confirmed either way since the app never touched a real
tenant with unsanitized content in this session. This is flagged as deferred in the result doc
itself and is an accepted operational follow-up (`.env` in this sandbox is a non-functional
device file, confirmed: `crw-rw-rw-`, so no artisan command touching a DB connection could run
here) — but it means the actual XSS backfill has **not happened yet** and must be tracked as an
outstanding deploy-time action, not treated as done.

TASK-5: PASS
notes: `dompurify` added to `package.json` `dependencies` (correct section, not devDependencies)
and lockfile. `resources/js/lib/sanitizeHtml.ts` wraps `DOMPurify.sanitize`. Re-grepped all
`{@html` sites myself: 8 call sites across 7 files (`default/Wysiwyg.svelte`,
`dvm/templates/Home.svelte` ×2, `dvm/templates/Page.svelte`, `dvm/templates/Contact.svelte`,
`geko/templates/Page.svelte`, `geko/templates/Aktuelles.svelte`,
`tabubruch/templates/Home.svelte`) — all 8 are wrapped with `sanitizeHtml()`. Minor inaccuracy:
result doc says "6 files"; it's actually 7 distinct files (8 sites). Documentation nit only, not
a functional gap — every site is in fact covered.

TASK-6: PASS
notes: `app/Services/TenantContentImporter.php::recreate()` sanitizes `Section.value` when
`wysiwyg` and `Post.body` unconditionally, both after creation, before persisting. Matches spec.

## Group 2 — Zip extraction hardening

TASK-7: PASS
notes: `assertNoUnsafeEntries()` in `TenantContentImporter.php` rejects absolute paths, Windows
drive-letter paths, and any `..` path segment, called before `extractTo()`. Reasonable coverage
for the stated threat model.

TASK-8: PASS
notes: `recreateMedia()` now runs `$item['archive_file']` through `basename()` before building
the filesystem path. Matches spec.

## Group 3 — Dev-login backdoor

TASK-9: PASS
notes: `routes/tenant.php` now wraps the `/admin/dev-login` route registration itself in
`if (app()->environment('local'))`, on top of the pre-existing in-controller `abort_unless`
guard. Belt-and-suspenders as requested.

TASK-10: PASS
notes: `deployment/deploy.yml` has a new task ("Verify APP_ENV=production before deploying") that
greps `/home/solicms/.env` for `APP_ENV=production` and fails the deploy otherwise. Reasonable
substitute for a manual checklist item, and the result doc's reasoning for why (no dedicated
deployment-checklist doc exists) checks out — I confirmed no such file exists elsewhere in
`deployment/` or `docs/`.

## Process note (applies to all three reports, flagged once here)

No git commits exist anywhere in this repository (`git log` returns nothing, `git status` shows
every file as untracked). The stated review process requires one commit per task in the format
`[ID] TASK-N: description`. That did not happen — there is no way to verify from version control
that Group 1 actually landed before Group 2/3, that each task was reviewable in isolation, or to
revert a single task if needed. The code itself is consistent with "security work landed first,"
but this can only be confirmed by reading file contents, not by history. This should be corrected
going forward: commit each task separately even when working in the same session.

## Overall: APPROVED (with one required follow-up)

The code changes for all 10 tasks are correct, well-scoped, and match both the original finding
and the task breakdown. Allow-list is sound, both write paths are covered, the importer path is
covered, hardening items are in place, and the dev-login belt-and-suspenders change is correct.

Required before this can be considered fully resolved in production:
- **Run `php artisan content:sanitize --dry-run` and then for real** (TASK-4) against every
  tenant, after a confirmed `spatie/laravel-backup` backup, per the result doc's own instructions.
  Until this runs, any content saved before this fix landed is still live and unsanitized.

Not blocking, but should be fixed:
- Start committing per-task on this branch/repo going forward so review and rollback are
  actually possible from git history.
