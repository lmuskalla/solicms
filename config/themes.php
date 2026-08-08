<?php

/**
 * config('themes.*') is actually assembled by App\Providers\ThemeServiceProvider
 * from resources/themes/<slug>/theme.php — one file per theme, colocated with
 * that theme's templates/components — not from this file. This file exists
 * only so config:cache has something to serialize into; it stays empty.
 *
 * Building a new theme is always a developer task. Adding
 * resources/themes/<slug>/theme.php is what makes it assignable and
 * renderable afterwards, without touching any other code:
 *
 *   - A tenant's `theme` column (central DB) picks a top-level key.
 *   - A page's `template` column (tenant DB) must be one of that theme's
 *     `templates` keys; Admin\PageController enforces this and exposes the
 *     `label`s for the page editor's dropdown.
 *   - Frontend\PageController resolves `component` to an actual file under
 *     resources/themes/<theme>/<component>.svelte and hands that path to
 *     Pages/Frontend/Page.svelte, which resolves it via import.meta.glob —
 *     nothing there needs updating when a theme or template is added.
 *     `component` is a path relative to the theme's own folder, so a theme
 *     following the templates/components split (see THEMES.md §1) writes it
 *     as e.g. 'templates/Home', not just 'Home'.
 *   - `sections` is the template's section *schema* — the definitive answer
 *     to "what sections does a page with this template have." Both
 *     App\Services\TenantProvisioner (initial tenant seeding) and
 *     Admin\PageController::store() (an editor creating a new page) read
 *     this instead of each hardcoding their own guess — a page's actual
 *     sections must never depend on which of those two code paths created
 *     it, and two pages sharing a template must always end up with the same
 *     sections. If a template's component reads a section key, that key
 *     belongs here.
 */

return [];
