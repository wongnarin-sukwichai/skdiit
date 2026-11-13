<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activity log (2026-11-13, option ข agreed with the user): sign-ins, accounts, settings, impersonation, new year,
 * backup. Paper and review activity is read from submission_events, not copied here. Kept one year (ActivityLog prunable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_name')->nullable(); // kept after the account is deleted
            $table->string('actor_role', 20)->nullable(); // User::accessRole() at the time; null = system / guest
            $table->string('category', 20); // auth | account | system | impersonation
            $table->string('action', 40);
            $table->string('target')->nullable(); // email of the account, or the paper code
            $table->string('detail')->nullable(); // section key (settings) or a short note
            $table->string('ip', 45)->nullable();
            $table->string('device', 60)->nullable();
            $table->boolean('flagged')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['category', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
