<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Navigation is editor-built, not auto-derived from the page list — a page
 * can exist without being in any menu, and a nav item's label can differ
 * from its page's title. See app/Models/NavItem.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nav_items', function (Blueprint $table) {
            $table->id();

            // 'page' (links to an internal Page) or 'link' (arbitrary URL).
            $table->string('type');
            $table->foreignId('page_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('url')->nullable();
            $table->integer('order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nav_items');
    }
};
