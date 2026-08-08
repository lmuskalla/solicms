<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which locale a tenant's public site is in — read by resources/views/app
 * .blade.php for the <html lang> attribute instead of the single, install-
 * wide app()->getLocale() (APP_LOCALE), which is 'en' while every real
 * tenant's content is German. See
 * docs/tasks/2026-08-08_seo-findings.md TASK-5/6.
 *
 * Decision: a column on the central `tenants` table (mirroring how `theme`
 * already works — see 2026_07_30_180000_add_theme_to_tenants_table.php),
 * not a per-theme default in config/themes.php, since locale is a property
 * of the client/content, not of which visual theme they happen to use.
 * Custom column — must be mirrored in Tenant::getCustomColumns().
 *
 * Defaulting to 'de' backfills every existing tenant automatically (all of
 * them — dvm, geko, tabubruch, default — are confirmed German-content sites,
 * see TASK-7), with nothing further to run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('locale', 10)->default('de');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
