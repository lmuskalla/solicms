<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            // Belongs to a Section of type 'posts' — that section is what
            // makes a repeatable list of posts show up on a page at all; see
            // Section::type doc comment.
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();

            $table->string('title');

            // Set only once a post has a body (below) — a post without one
            // is just a card/row, nothing to link to. Unique only among
            // non-null values; two bodyless posts can share no slug at all.
            $table->string('slug')->nullable()->unique();

            // Short teaser shown on cards; independent of body/detail page.
            $table->string('excerpt')->nullable();

            // Presence, not a separate "type" flag, is what makes a post
            // render as a dated entry (an "Aktuelle Termine" row) versus a
            // plain card — see THEMES.md and Post::isEvent().
            $table->dateTime('starts_at')->nullable();

            // Presence, not a separate flag, is what gives a post its own
            // detail page at /aktuelles/{slug} — see Frontend\PostController.
            $table->longText('body')->nullable();

            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
