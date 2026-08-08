<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();

            // Referenced from Svelte templates, e.g. 'hero_text'. Never shown to clients.
            $table->string('key');

            // What the client actually sees in the admin, e.g. 'Hero Text'.
            $table->string('label');

            // text, textarea, wysiwyg, image, email, url, color, posts
            // ('posts' sections hold no `value` of their own — their content
            // is the section's `posts` relation, a repeatable, editor-managed
            // list; see App\Models\Post.)
            $table->string('type');

            $table->longText('value')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->unique(['page_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
