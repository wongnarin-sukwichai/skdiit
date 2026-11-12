<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * 2026-11-12: editors check the camera-ready file before it goes into the proceedings. A received file
 * (status camera_ready) waits for that check until camera_ready_approved_at is set; "fix" sends it back (accepted).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->timestamp('camera_ready_approved_at')->nullable()->after('camera_ready_at');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn('camera_ready_approved_at');
        });
    }
};
