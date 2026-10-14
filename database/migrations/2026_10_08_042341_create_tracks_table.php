<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tracks', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_th');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Which tracks each reviewer can be assigned papers from (set by admin)
        Schema::create('reviewer_track', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'track_id']);
        });

        // Starting tracks; admin renames them later in Settings
        $now = now();
        $names = [
            'Smart Knowledge Library and Information',
            'Smart Spaces and Cyber-Physical Environments',
            'Digital Media, Communication and Society',
            'Digital Innovation and Transformation',
            'Artificial Intelligence and Intelligent Technologies',
        ];
        DB::table('tracks')->insert(array_map(fn (string $name, int $i) => [
            'name_en' => $name,
            'name_th' => $name,
            'sort_order' => $i + 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $names, array_keys($names)));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviewer_track');
        Schema::dropIfExists('tracks');
    }
};
