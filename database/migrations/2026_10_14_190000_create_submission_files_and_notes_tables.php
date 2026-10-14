<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paper file versions (no file is deleted on replace any more), the author's answers to the reviewers
 * with each revision, and internal notes the editors keep on a paper.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Every manuscript upload, and the response letter that came with a revision
        Schema::create('submission_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version'); // manuscript version this file belongs to (1, 2, ...)
            $table->string('kind', 20); // manuscript | response
            $table->string('stage', 20); // initial | edit (before screening) | revision (sent back)
            $table->unsignedSmallInteger('round')->default(0); // review round when uploaded
            $table->string('path');
            $table->string('name');
            $table->unsignedBigInteger('size');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['submission_id', 'version']);
        });

        // "How I addressed this comment": one per reviewer (review_id) or to the editor (null), per version
        Schema::create('revision_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->foreignId('review_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();
        });

        // Notes only admin and editors see (calls, agreements made outside the system)
        Schema::create('submission_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->boolean('pinned')->default(false);
            $table->timestamps();
        });

        // The current file of every paper becomes version 1
        foreach (DB::table('submissions')->get() as $paper) {
            DB::table('submission_files')->insert([
                'submission_id' => $paper->id, 'version' => 1, 'kind' => 'manuscript', 'stage' => 'initial', 'round' => 0,
                'path' => $paper->file_path, 'name' => $paper->file_name, 'size' => $paper->file_size,
                'user_id' => $paper->user_id, 'created_at' => $paper->created_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_notes');
        Schema::dropIfExists('revision_responses');
        Schema::dropIfExists('submission_files');
    }
};
