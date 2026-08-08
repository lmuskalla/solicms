export interface AuthUser {
    id: number;
    name: string;
    email: string;
    role: string;
}

export interface Auth {
    user?: AuthUser | null;
}

/** One entry inside a 'posts'-type Section — see App\Models\Post's doc comment. */
export interface PostRecord {
    id: number;
    section_id: number;
    title: string;
    slug: string | null;
    excerpt: string | null;
    image: string | null;
    starts_at: string | null;
    auto_delete: boolean;
    body: string | null;
    order: number;
}

/**
 * Shared prop contract for every theme's template components (see
 * THEMES.md §2) — lives here rather than under resources/themes/<slug>/
 * because it's identical across every theme, not specific to one.
 *
 * `posts` is only present on a section whose `type` is 'posts' — every
 * other section type carries only `value`.
 */
export interface ThemeProps {
    page?: { title: string; template: string };
    sections: Record<string, { value: string; type?: string; alt?: string | null; posts?: PostRecord[] }>;
    config: Record<string, string>;
    nav: Array<{ label: string; href: string }>;
    /** This tenant's canonical URL for the current page — see Frontend\PageController and Components/Frontend/SeoHead.svelte. */
    url?: string;
}
