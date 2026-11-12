<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * 2026-11-12: the presentation schedule becomes real. Admin keeps a list of rooms and builds sessions
 * (a day, a time span, a room, a track, a chair typed in, minutes per paper); papers join a session in running order
 * (from the Presentations page, or editors on the paper page). submissions.presentation_at / presentation_room stay
 * as the computed slot (slides cut-off, emails, the public schedule).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presentation_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name_th');
            $table->string('name_en');
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('presentation_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->date('date');
            $table->string('start_time', 5); // H:i
            $table->string('end_time', 5);
            $table->foreignId('presentation_room_id')->constrained()->restrictOnDelete();
            $table->foreignId('track_id')->nullable()->constrained()->nullOnDelete();
            $table->string('chair')->nullable();
            $table->unsignedTinyInteger('minutes')->default(15);
            $table->timestamps();
            $table->index(['year', 'date']);
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->foreignId('presentation_session_id')->nullable()->after('presentation_room')->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('presentation_order')->nullable()->after('presentation_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('presentation_session_id');
            $table->dropColumn('presentation_order');
        });
        Schema::dropIfExists('presentation_sessions');
        Schema::dropIfExists('presentation_rooms');
    }
};
