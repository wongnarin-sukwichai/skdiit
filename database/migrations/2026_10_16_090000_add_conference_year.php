<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Conference years (item 39): every paper belongs to one year, and a registrant joins a year.
 * The current year is the setting 'conference_year'; "New conference year" in Settings moves it on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->default(2027)->after('id')->index();
        });
        Schema::table('users', function (Blueprint $table) {
            // The latest conference year this registrant joined (null for staff)
            $table->unsignedSmallInteger('registered_year')->nullable()->after('participation');
        });

        DB::table('users')->where('role', 'user')->update(['registered_year' => 2027]);
    }

    public function down(): void
    {
        Schema::table('submissions', fn (Blueprint $table) => $table->dropColumn('year'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('registered_year'));
    }
};
