<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** The public About page was removed (2026-10-16): drop its saved texts from the site content. */
return new class extends Migration
{
    public function up(): void
    {
        $row = DB::table('settings')->where('key', 'site_content')->first();
        $content = $row ? json_decode((string) $row->value, true) : null;
        if (is_array($content) && array_key_exists('about', $content)) {
            unset($content['about']);
            DB::table('settings')->where('key', 'site_content')->update(['value' => json_encode($content, JSON_UNESCAPED_UNICODE)]);
        }
    }

    public function down(): void
    {
        // The texts are gone; SiteContent no longer has an About section to restore
    }
};
