<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The conference is international: an affiliation may have an English name only (tr() falls back to it). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliations', function (Blueprint $table) {
            $table->string('name_th')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('affiliations', function (Blueprint $table) {
            $table->string('name_th')->nullable(false)->change();
        });
    }
};
