# Task lists — index and cross-report sequencing

Three independent task breakdowns, each derived from a report in `docs/reports/`:

- [`2026-08-08_security-review-findings.md`](./2026-08-08_security-review-findings.md)
- [`2026-08-08_feature-improvements.md`](./2026-08-08_feature-improvements.md)
- [`2026-08-08_seo-findings.md`](./2026-08-08_seo-findings.md)

Each file is internally ordered by its own report's priority. This file covers the parts that
don't belong to any single one of them: overall scheduling across all three, and two places
where tasks in *different* files depend on the same underlying decision or component.

---

## 1. Overall priority — security goes first, regardless of file order

All three reports independently say "fix before next client onboarding." But the security
report is the only one describing an **already-exploitable, currently-live** vulnerability
(stored XSS, confirmed unmitigated — no sanitizer exists anywhere in the codebase today) rather
than a UX or discoverability gap. Schedule accordingly:

1. `security-review-findings.md` TASK-1 → TASK-2 → TASK-3 (sanitizer selection + applied at
   both write paths) — before starting feature-improvements or seo-findings work.
2. `security-review-findings.md` TASK-4 (backfill-sanitize already-persisted content across
   every tenant) — only after TASK-1–3 have landed **and** a confirmed backup exists
   (`spatie/laravel-backup` is already configured for this). This task mutates live tenant data
   and is marked high risk for that reason — don't run it opportunistically alongside other work.
3. Everything else proceeds per each file's own internal ordering.

## 2. Coordinate the two schema/migration decision tasks

Two decision tasks in two different files hinge on the same underlying pattern: adding a new
persisted column to a tenant-scoped model (`Section` or `Page`) and rolling it out via a tenant
migration to **every existing tenant SQLite database**, not just newly-provisioned ones:

- `feature-improvements.md` TASK-4 / TASK-5 — alt text storage location
- `seo-findings.md` TASK-3 — meta description data source

Resolve these together even though they live in separate files. If a migration-rollout
mechanism (how existing tenants get the new column, how it's defaulted/backfilled, how
`tenancy:migrate` gets run across all of them) is worked out for one, reuse it for the other
rather than solving it twice independently.

Before either lands, confirm operationally:
- There's a working, repeatable process for running a tenant migration against every existing
  tenant DB (not just ones created after the migration exists).
- A non-production tenant is available to test the migration against first.

## 3. Build the shared SEO head component before any single-theme patch

`seo-findings.md` TASK-2 (a shared `SeoHead.svelte` component) should land before anyone patches
an individual theme's `<svelte:head>` block directly to unblock one specific client request.
Every theme currently owns its own head block independently — there's no shared layout that
renders `<svelte:head>` today — so a one-off fix to a single theme just recreates the
duplication the shared component exists to solve, and the next head-tag fix (description,
canonical, whatever comes after this list) repeats the "touch N files" problem again.

Before starting TASK-2 itself: run a full search for `<svelte:head>` across `resources/themes/`
to get the definitive list of files to update. The report's list of example files was
illustrative, not exhaustive.
