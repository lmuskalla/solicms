<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a `menu` discriminator to nav_items so a tenant can maintain a
 * separate footer navigation instead of always reusing the header menu —
 * see docs/jobs/k6ujxu_footer-navigation. Values: 'header' (default) and
 * 'footer' (see App\Models\NavItem::MENUS).
 *
 * The default of 'header' means all pre-existing nav items become header
 * items; existing tenants' footers will render an empty nav until an editor
 * curates footer items.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nav_items', function (Blueprint $table) {
            $table->string('menu')->default('header');
        });
    }

    public function down(): void
    {
        Schema::table('nav_items', function (Blueprint $table) {
            $table->dropColumn('menu');
        });
    }
};
