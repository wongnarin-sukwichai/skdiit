<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Journal workflow (client decision 2026-10-15): every paper goes to the journal first; papers the journal
 * does not take go through the conference track (editor screening, peer review, camera-ready).
 * Option A/B is gone, and presenters upload their slides.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Old publishing statuses: Option A becomes "waiting for camera-ready", Option B "selected by the journal"
        DB::table('submissions')->where('status', 'option_a')->update(['status' => 'accepted']);
        DB::table('submissions')->where('status', 'option_b')->update(['status' => 'journal_selected']);
        // Old history entries that no longer exist (the option choice) or changed meaning (the journal result)
        DB::table('submission_events')->whereIn('action', ['option_chosen', 'journal_result'])->delete();
        DB::table('submission_events')->whereIn('from_status', ['option_a', 'option_b'])->update(['from_status' => null, 'to_status' => null]);

        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['publish_option', 'journal_name']);

            // Presentation slides (after the paper has a slot; replaceable until the talk)
            $table->string('slides_path')->nullable()->after('camera_ready_at');
            $table->string('slides_name')->nullable()->after('slides_path');
            $table->unsignedBigInteger('slides_size')->nullable()->after('slides_name');
            $table->timestamp('slides_at')->nullable()->after('slides_size');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['slides_path', 'slides_name', 'slides_size', 'slides_at']);
            $table->char('publish_option', 1)->nullable()->after('revision_due_date');
            $table->string('journal_name')->nullable()->after('publish_option');
        });
    }
};
