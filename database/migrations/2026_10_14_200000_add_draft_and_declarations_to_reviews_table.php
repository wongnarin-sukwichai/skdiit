<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reviewer tools: a confidential comment for the editors, a saved draft, the no-conflict declaration made
 * when submitting, and "not available" (the reviewer tells the editors, who replace them).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->text('comment_to_editor')->nullable()->after('comment');
            $table->timestamp('draft_saved_at')->nullable()->after('attachment_size');
            $table->timestamp('conflict_declared_at')->nullable()->after('draft_saved_at');
            $table->timestamp('unavailable_at')->nullable()->after('conflict_declared_at');
            $table->text('unavailable_reason')->nullable()->after('unavailable_at');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['comment_to_editor', 'draft_saved_at', 'conflict_declared_at', 'unavailable_at', 'unavailable_reason']);
        });
    }
};
