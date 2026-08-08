<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * label/type/order were a snapshot taken from config/themes.php's section
 * schema at the moment a row was created — editing the schema afterwards
 * never touched already-provisioned rows, which is exactly what made a
 * template's schema changes invisible on existing pages. App\Models\Section
 * now resolves all three live from config('themes...') by key instead, so
 * a schema edit is instantly correct everywhere with nothing left to sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn(['label', 'type', 'order']);
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->string('label')->default('');
            $table->string('type')->default('text');
            $table->integer('order')->default(0);
        });
    }
};
