<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Optional details for staff and reviewer accounts (academic title: User::ACADEMIC_TITLES). */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('academic_title', 20)->nullable()->after('name');
            $table->string('phone', 30)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['academic_title', 'phone']);
        });
    }
};
