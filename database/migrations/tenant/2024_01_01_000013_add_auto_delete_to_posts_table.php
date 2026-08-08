<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Only meaningful on an 'events'-type section's posts (see Post's doc
 * comment) — an editor opts a Termin into being deleted automatically once
 * it's in the past, instead of it sitting there forever needing manual
 * cleanup. See App\Jobs\PruneExpiredPosts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->boolean('auto_delete')->default(false)->after('starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('auto_delete');
        });
    }
};
