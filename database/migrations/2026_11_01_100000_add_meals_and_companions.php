<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Meal preferences (client request 2026-11-01): a list admin manages in Settings (meal_options),
 * the registrant's own choice, and one companion (name + meal) the registrant fills in.
 * users.meal / companion_meal keep the code; 'other' means a typed preference (*_other).
 */
return new class extends Migration
{
    private const STARTING = [
        'regular' => ['Regular', 'ทานปกติ'],
        'vegetarian' => ['Vegetarian', 'มังสวิรัติ'],
        'jay' => ['Vegan (Jay)', 'อาหารเจ'],
        'halal' => ['Halal', 'ฮาลาล'],
        'no_beef' => ['No beef', 'ไม่ทานเนื้อวัว'],
        'no_seafood' => ['No seafood', 'ไม่ทานอาหารทะเล'],
    ];

    public function up(): void
    {
        Schema::create('meal_options', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique(); // stored in users.meal / users.companion_meal
            $table->string('name_en');
            $table->string('name_th');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('meal', 40)->nullable()->after('phone');
            $table->string('meal_other')->nullable()->after('meal');
            $table->string('companion_name')->nullable()->after('meal_other');
            $table->string('companion_meal', 40)->nullable()->after('companion_name');
            $table->string('companion_meal_other')->nullable()->after('companion_meal');
        });

        $now = now();
        $order = 0;
        foreach (self::STARTING as $code => [$en, $th]) {
            DB::table('meal_options')->insert(['code' => $code, 'name_en' => $en, 'name_th' => $th, 'sort_order' => ++$order, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['meal', 'meal_other', 'companion_name', 'companion_meal', 'companion_meal_other']);
        });
        Schema::dropIfExists('meal_options');
    }
};
