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
        // When the author should send a revised paper (set by the editor with a minor/major revision decision)
        Schema::table('submissions', function (Blueprint $table) {
            $table->date('revision_due_date')->nullable()->after('review_round');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn('revision_due_date');
        });
    }
};
