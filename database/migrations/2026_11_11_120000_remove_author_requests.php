<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Only authors sign up now (user request 2026-11-11): the request to submit papers is gone. Its table and the
 * request emails go; the "you can now submit papers" email stays as `became_author` (admin switches participation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('author_requests');
        DB::table('email_templates')->whereIn('key', ['author_request_new', 'author_request_rejected'])->delete();
        DB::table('email_templates')->where('key', 'author_request_approved')->update(['key' => 'became_author']);
    }

    public function down(): void
    {
        // Not restored: run the 2026_10_19 migration again by hand if the feature ever comes back
        DB::table('email_templates')->where('key', 'became_author')->update(['key' => 'author_request_approved']);
    }
};