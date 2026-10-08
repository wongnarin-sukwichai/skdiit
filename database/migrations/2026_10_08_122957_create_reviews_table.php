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
        // Peer review rounds: 1 when a paper first passes screening, +1 each time it comes back for review
        Schema::table('submissions', function (Blueprint $table) {
            $table->unsignedSmallInteger('review_round')->default(0)->after('status');
        });

        // One reviewer's assignment and evaluation for one round of a paper
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('round');
            $table->date('due_date');
            $table->string('recommendation')->nullable(); // App\Enums\ReviewRecommendation
            $table->text('comment')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['submission_id', 'reviewer_id', 'round']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');

        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn('review_round');
        });
    }
};
