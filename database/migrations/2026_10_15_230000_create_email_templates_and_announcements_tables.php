<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email templates editors and admin may rewrite (item 8; defaults in App\Models\EmailTemplate::DEFAULTS)
 * and announcements sent to a group of people (item 38).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Only templates someone changed are stored; the rest use the defaults
        Schema::create('email_templates', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('subject_th');
            $table->string('subject_en');
            $table->text('body_th');
            $table->text('body_en');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // who sent it
            $table->string('audience', 40); // App\Support\Audience key
            $table->string('subject');
            $table->text('body');
            $table->unsignedInteger('recipients');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('email_templates');
    }
};
