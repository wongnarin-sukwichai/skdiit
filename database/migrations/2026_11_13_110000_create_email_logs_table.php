<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every email the system sends (2026-11-13, Notifications › email log): to whom, what, sent or failed.
 * Password emails keep no body (the password is never stored); the others keep their text so they can be
 * opened and sent again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('submission_id')->nullable()->constrained()->nullOnDelete();
            $table->string('to_email');
            $table->string('to_name')->nullable();
            $table->string('kind', 60); // EmailTemplate key, 'announcement', 'password_registered' or 'password_reset'
            $table->string('subject');
            $table->text('body_th')->nullable();
            $table->text('body_en')->nullable();
            $table->string('button_url')->nullable();
            $table->string('status', 10); // sent | failed
            $table->text('error')->nullable();
            $table->unsignedInteger('resent')->default(0);
            $table->foreignId('resent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resent_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
