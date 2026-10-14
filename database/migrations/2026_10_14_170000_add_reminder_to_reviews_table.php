<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The latest reminder sent to a reviewer, and by whom, so editors don't chase the same person twice. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->timestamp('reminded_at')->nullable()->after('submitted_at');
            $table->foreignId('reminded_by')->nullable()->after('reminded_at')->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('reminders')->default(0)->after('reminded_by');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reminded_by');
            $table->dropColumn(['reminded_at', 'reminders']);
        });
    }
};
