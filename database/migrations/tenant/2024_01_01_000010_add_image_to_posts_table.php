<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // Stores the uploaded media's URL directly, exactly like
            // Section.value does for an 'image'-type section — the actual
            // Media record (see Post::registerMediaCollections) is what
            // MediaController::store uploads into and returns a URL from,
            // but rendering never re-queries that relation, it just reads
            // this column.
            $table->string('image')->nullable()->after('excerpt');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }
};
