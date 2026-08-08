<?php

use App\Services\ThemeMigrations\ThemeMigration;

/**
 * dvm_home's first narrative block used to be a one-off ("intro"); it's now
 * section_1, matching section_2/section_3. Renames the key in place so
 * existing pages' already-written text/media survive under the new name.
 * hero_image and intro_title never made it into this rework at all — no
 * current schema key covers them, so they're dropped outright rather than
 * left as permanent orphans; down() can only recreate them empty, not
 * un-delete whatever content they held.
 */
return new class extends ThemeMigration
{
    public function up(): void
    {
        $this->renameKey('dvm_home', 'intro_body', 'section_1_body');
        $this->renameKey('dvm_home', 'intro_readmore', 'section_1_readmore');
        $this->dropKey('dvm_home', 'hero_image');
        $this->dropKey('dvm_home', 'intro_title');
    }

    public function down(): void
    {
        $this->renameKey('dvm_home', 'section_1_body', 'intro_body');
        $this->renameKey('dvm_home', 'section_1_readmore', 'intro_readmore');
        $this->eachPage('dvm_home', function ($page) {
            $page->sections()->firstOrCreate(['key' => 'hero_image'], ['value' => '']);
            $page->sections()->firstOrCreate(['key' => 'intro_title'], ['value' => '']);
        });
    }
};
