<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Files admin uploads for the public "Documents" page (templates, guidelines, announcements). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_documents', function (Blueprint $table) {
            $table->id();
            $table->string('name_th');
            $table->string('name_en');
            $table->string('path');
            $table->string('file_name');
            $table->unsignedBigInteger('size');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_documents');
    }
};
