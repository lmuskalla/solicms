Verdict: REVISIT

  User perspective: The org running the site doesn't experience "SEO" directly — they experience it as "we posted
  our donation appeal on Facebook and the link looked broken" or "people can't find us on Google." Two of the
  gaps below hit exactly that, and one of them (social sharing) is arguably more central to this audience than
  organic search: a food bank or shelter promoting a fundraiser is far more likely to be shared via
  Facebook/WhatsApp/Instagram than found through a Google search. Right now that share will render with no title,
  no description, and no image — a bare link. That directly undermines the actual mission this product exists to
  serve.

  Scope assessment: Findings are concrete and scoped to specific files, not speculative:

  1. No Open Graph / Twitter Card tags anywhere. Grepped every theme template — zero og:*/twitter:* tags. Every
  <svelte:head> block only sets <title>. A shared link on Facebook/WhatsApp/LinkedIn gets no title, description,
  or preview image.
  2. No meta description anywhere. Same templates, same gap — Google's search snippet falls back to
  auto-extracted page text, which for a hero-driven homepage is often unusable ("Skip to content...").
  3. <html lang> is wrong for every real tenant today. resources/views/app.blade.php renders lang="{{ 
  app()->getLocale() }}", and .env.example sets APP_LOCALE=en. Every actual tenant's content is German (dvm,
  geko, tabubruch, default — "Startseite," "Über uns," German UI copy throughout). Since this is one shared
  Laravel install/.env across all tenants, there's currently no per-tenant override — so unless someone manually
  flips APP_LOCALE in production, every German site is marked lang="en". Wrong language signal for Google, and
  actively wrong for screen readers (mispronunciation).
  4. robots.txt is a single static file, identical for every tenant and the admin/superadmin domains, and it
  disallows nothing (Disallow: empty = allow all). /admin/login, /admin/forgot-password, /superadmin/login are
  all crawlable/indexable on every client's public domain. Not a data leak, but it's sloppy — an NGO's brand
  search shouldn't have a stray "Admin-Anmeldung" login page in its results.
  5. No sitemap.xml. Lower priority given these are small (3–10 page) sites reachable via normal nav links, but
  worth a line.
  6. Multi-domain tenants have no canonical tag. TenantProvisioner::provision() explicitly supports multiple
  domains per tenant (e.g. a .de and a legacy .org pointing at the same site) — with identical content served on
  two hosts and no <link rel="canonical">, that's textbook duplicate-content dilution.

  What's actually fine, for balance: page <title>s are unique per page (checked all templates), slugs are clean,
  and the lazy-loaded per-theme bundles + self-hosted fonts mean pages are lean — Core Web Vitals (a real ranking
  factor) aren't a problem here. This isn't a from-scratch SEO effort, it's closing specific, identifiable
  holes.

  Concerns: Items 1–3 aren't edge cases — they affect every single tenant site live today, not a hypothetical
  future one. Item 3 in particular means the product is currently shipping non-English sites with the wrong
  declared language, which cuts across both this SEO check and the accessibility baseline THEMES.md already
  claims to enforce.

  Recommendation: Fix 1–3 before the next client onboarding; 4 is a quick companion fix; 5 and 6 can wait for
  real signal (a client actually running two domains, or asking about search ranking).
