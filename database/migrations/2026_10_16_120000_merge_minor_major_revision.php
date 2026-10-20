<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Minor and major revision become one "revise" (client decision 2026-10-16), for the editor's decision
 * after review and for the reviewer's recommendation. The old level is not kept: down() turns everything into minor.
 */
return new class extends Migration
{
    public function up(): void
    {
        $old = ['revision_minor', 'revision_major'];
        DB::table('submissions')->whereIn('status', $old)->update(['status' => 'review_revision']);
        DB::table('submission_events')->whereIn('from_status', $old)->update(['from_status' => 'review_revision']);
        DB::table('submission_events')->whereIn('to_status', $old)->update(['to_status' => 'review_revision']);

        DB::table('reviews')->whereIn('recommendation', ['minor', 'major'])->update(['recommendation' => 'revise']);
        DB::table('submission_events')->whereIn('meta->recommendation', ['minor', 'major'])->update(['meta->recommendation' => 'revise']);

        // A rewritten minor-revision email carries over; the major one is dropped
        if (! DB::table('email_templates')->where('key', 'decision_revise')->exists()) {
            DB::table('email_templates')->where('key', 'decision_minor')->update(['key' => 'decision_revise']);
        }
        DB::table('email_templates')->whereIn('key', ['decision_minor', 'decision_major'])->delete();
    }

    public function down(): void
    {
        DB::table('submissions')->where('status', 'review_revision')->update(['status' => 'revision_minor']);
        DB::table('submission_events')->where('from_status', 'review_revision')->update(['from_status' => 'revision_minor']);
        DB::table('submission_events')->where('to_status', 'review_revision')->update(['to_status' => 'revision_minor']);
        DB::table('reviews')->where('recommendation', 'revise')->update(['recommendation' => 'minor']);
        DB::table('submission_events')->where('meta->recommendation', 'revise')->update(['meta->recommendation' => 'minor']);
        DB::table('email_templates')->where('key', 'decision_revise')->update(['key' => 'decision_minor']);
    }
};
