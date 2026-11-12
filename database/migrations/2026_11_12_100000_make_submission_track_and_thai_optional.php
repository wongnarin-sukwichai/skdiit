<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Client request 2026-11-12 (international conference): authors no longer pick a track (the editor sets it at the
 * first gate, Submission::route()), and the Thai title, abstract and keywords are optional (English is required).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->foreignId('track_id')->nullable()->change();
            $table->string('title_th', 500)->nullable()->change();
            $table->text('abstract_th')->nullable()->change();
            $table->string('keywords_th', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->foreignId('track_id')->nullable(false)->change();
            $table->string('title_th', 500)->nullable(false)->change();
            $table->text('abstract_th')->nullable(false)->change();
            $table->string('keywords_th', 500)->nullable(false)->change();
        });
    }
};
