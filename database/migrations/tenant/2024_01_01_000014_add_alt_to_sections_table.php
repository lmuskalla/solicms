<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alt text for an 'image'-type section's image — an editor-set description
 * for screen readers/SEO, e.g. a hero image. Stored directly on the section
 * (the simplest option: a section owns exactly one image) rather than on
 * the underlying spatie Media model's custom_properties — see
 * docs/tasks/2026-08-08_feature-improvements.md TASK-4/5. Meaningless for
 * every other section type, left null there.
 *
 * This must be rolled out to every existing tenant database, not just new
 * ones — run `php artisan tenants:migrate` (config/tenancy.php's
 * `migration_parameters` already points it at this directory) against all
 * tenants after deploying this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->string('alt')->nullable()->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn('alt');
        });
    }
};
