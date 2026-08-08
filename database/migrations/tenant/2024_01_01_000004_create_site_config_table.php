<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_config', function (Blueprint $table) {
            $table->id();

            // e.g. 'site_name', 'color_primary', 'contact_email'
            $table->string('key')->unique();
            $table->string('label');

            // text, image, color, email, url
            $table->string('type');

            $table->string('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_config');
    }
};
