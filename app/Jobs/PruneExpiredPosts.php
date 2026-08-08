<?php

namespace App\Jobs;

use App\Models\Post;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Deletes every Post with `auto_delete` set whose `starts_at` is in the
 * past — the opt-in cleanup an editor enables per-Termin (PostEditor.svelte)
 * rather than something applied to every event. Scheduled daily, see
 * routes/console.php. Runs across every tenant, one at a time within each,
 * since a single Laravel install serves all of them from one process.
 */
class PruneExpiredPosts implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        foreach (Tenant::all() as $tenant) {
            tenancy()->initialize($tenant);

            try {
                // One at a time, not a bulk delete — so each Post's own
                // `deleting` hook fires and its uploaded media is actually
                // removed from disk, not left orphaned. Mirrors
                // TenantContentImporter::wipe()'s identical reasoning.
                Post::where('auto_delete', true)
                    ->where('starts_at', '<', now())
                    ->get()
                    ->each->delete();
            } finally {
                tenancy()->end();
            }
        }
    }
}
