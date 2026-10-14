<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TRACKS = [
        'Smart Knowledge Library and Information',
        'Smart Spaces and Cyber-Physical Environments',
        'Digital Media, Communication and Society',
        'Digital Innovation and Transformation',
        'Artificial Intelligence and Intelligent Technologies',
    ];

    /**
     * Rename the placeholder "Track-N" rows on databases migrated before the names were set.
     */
    public function up(): void
    {
        foreach (self::TRACKS as $i => $name) {
            DB::table('tracks')
                ->where('name_en', 'Track-'.($i + 1))
                ->update(['name_en' => $name, 'name_th' => $name, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        //
    }
};
