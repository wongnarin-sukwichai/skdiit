<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * 2026-11-12: a reviewer's file (often the PDF with notes on it) goes to the author with the decision, as
 * "Reviewer N's notes", unless an editor holds it back (e.g. it shows the reviewer's name).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->boolean('attachment_to_author')->default(true)->after('attachment_size');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn('attachment_to_author');
        });
    }
};
