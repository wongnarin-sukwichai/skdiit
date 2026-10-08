<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            // Publishing option chosen by the author after acceptance: 'a' Proceeding, 'b' journal
            $table->char('publish_option', 1)->nullable()->after('revision_due_date');

            // Option A: the final (camera-ready) file for the proceedings
            $table->string('camera_ready_path')->nullable()->after('file_size');
            $table->string('camera_ready_name')->nullable()->after('camera_ready_path');
            $table->unsignedBigInteger('camera_ready_size')->nullable()->after('camera_ready_name');
            $table->timestamp('camera_ready_at')->nullable()->after('camera_ready_size');

            // Option B: the journal the editor forwarded the paper to
            $table->string('journal_name')->nullable()->after('publish_option');

            // Presentation slot at the conference
            $table->dateTime('presentation_at')->nullable()->after('journal_name');
            $table->string('presentation_room')->nullable()->after('presentation_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn([
                'publish_option', 'camera_ready_path', 'camera_ready_name', 'camera_ready_size', 'camera_ready_at',
                'journal_name', 'presentation_at', 'presentation_room',
            ]);
        });
    }
};
