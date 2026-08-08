<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Which entry in config/themes.php this tenant's public site uses.
            // Custom column — must be mirrored in Tenant::getCustomColumns().
            $table->string('theme')->default('default');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('theme');
        });
    }
};
