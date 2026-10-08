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
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique(); // e.g. SKDIIT27-0001, set after insert
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // submitting account = the contact
            $table->foreignId('track_id')->constrained()->restrictOnDelete(); // chosen by the author, editors may move it
            $table->string('title_th', 500);
            $table->string('title_en', 500);
            $table->text('abstract_th');
            $table->text('abstract_en');
            $table->string('keywords_th', 500);
            $table->string('keywords_en', 500);
            $table->string('status')->default('submitted'); // App\Enums\SubmissionStatus
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size');
            $table->timestamps();
        });

        // Authors in byline order; the submitter usually comes first but is not required to
        Schema::create('submission_authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('name');
            $table->string('affiliation');
            $table->string('email')->nullable(); // optional; helps the conflict-of-interest check
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submission_authors');
        Schema::dropIfExists('submissions');
    }
};
