<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reminding one author from their paper's row (2026-11-13): the last reminder and who sent it, so editors see it was
 * done and nobody sends a second one by mistake. Covers the journal offer, a revision and the camera-ready file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->timestamp('author_reminded_at')->nullable();
            $table->foreignId('author_reminded_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('author_reminded_by');
            $table->dropColumn('author_reminded_at');
        });
    }
};
