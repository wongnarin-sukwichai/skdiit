<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reviewer scoring (client decision 2026-10-15): admin sets the criteria in Settings,
 * reviewers give every criterion a score from 1 to 5 with their evaluation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_criteria', function (Blueprint $table) {
            $table->id();
            $table->string('name_th');
            $table->string('name_en');
            $table->string('description_th', 500)->nullable(); // what reviewers should look at
            $table->string('description_en', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('review_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            // A criterion with scores cannot be deleted (renaming is fine)
            $table->foreignId('review_criterion_id')->constrained('review_criteria')->restrictOnDelete();
            $table->unsignedTinyInteger('score'); // 1..5
            $table->unique(['review_id', 'review_criterion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_scores');
        Schema::dropIfExists('review_criteria');
    }
};
