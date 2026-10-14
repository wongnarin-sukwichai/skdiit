<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Academic titles become a list admin manages (Settings › Academic titles) instead of fixed keys.
 * users.academic_title ('lecturer', 'asst_prof', ...) is replaced by users.academic_title_id.
 */
return new class extends Migration
{
    /** Starting titles; the key is the old users.academic_title value. */
    private const STARTING = [
        'lecturer' => ['Lect.', 'อ.', 'Lecturer', 'อาจารย์'],
        'asst_prof' => ['Asst. Prof.', 'ผศ.', 'Assistant Professor', 'ผู้ช่วยศาสตราจารย์'],
        'assoc_prof' => ['Assoc. Prof.', 'รศ.', 'Associate Professor', 'รองศาสตราจารย์'],
        'prof' => ['Prof.', 'ศ.', 'Professor', 'ศาสตราจารย์'],
    ];

    public function up(): void
    {
        Schema::create('academic_titles', function (Blueprint $table) {
            $table->id();
            $table->string('short_en', 50); // shown before a name, e.g. "Asst. Prof."
            $table->string('short_th', 50);
            $table->string('name_en'); // full title, e.g. "Assistant Professor"
            $table->string('name_th');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('academic_title_id')->nullable()->after('name')->constrained()->nullOnDelete();
        });

        $now = now();
        $order = 0;
        foreach (self::STARTING as $key => [$shortEn, $shortTh, $nameEn, $nameTh]) {
            $id = DB::table('academic_titles')->insertGetId([
                'short_en' => $shortEn, 'short_th' => $shortTh, 'name_en' => $nameEn, 'name_th' => $nameTh,
                'sort_order' => ++$order, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('users')->where('academic_title', $key)->update(['academic_title_id' => $id]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('academic_title');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('academic_title', 20)->nullable()->after('name');
        });

        $keys = array_keys(self::STARTING);
        foreach (DB::table('academic_titles')->orderBy('sort_order')->pluck('id') as $i => $id) {
            if (isset($keys[$i])) {
                DB::table('users')->where('academic_title_id', $id)->update(['academic_title' => $keys[$i]]);
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('academic_title_id');
        });
        Schema::dropIfExists('academic_titles');
    }
};
