<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The slot last emailed to the author ("Y-m-d H:i|room", 2026-11-13). Arranging sessions no longer emails on every
 * change; staff press "Notify authors" once and only papers whose slot differs from this one are emailed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->string('presentation_notified')->nullable()->after('presentation_room');
        });

        // Slots set before this change were already emailed
        DB::table('submissions')->whereNotNull('presentation_at')->update([
            'presentation_notified' => DB::raw("CONCAT(DATE_FORMAT(presentation_at, '%Y-%m-%d %H:%i'), '|', COALESCE(presentation_room, ''))"),
        ]);
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn('presentation_notified');
        });
    }
};
