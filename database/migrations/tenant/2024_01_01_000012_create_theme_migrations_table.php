<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks which resources/themes/<slug>/migrations/*.php have already run
 * against this tenant — see App\Services\ThemeMigrator. Same shape and
 * purpose as Laravel's own `migrations` table, just scoped to theme content
 * changes (renaming/adding/removing a template's section keys) instead of
 * database schema changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theme_migrations', function (Blueprint $table) {
            $table->id();
            $table->string('migration');
            $table->unsignedInteger('batch');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_migrations');
    }
};
