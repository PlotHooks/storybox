<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('character_presences', function (Blueprint $table) {
            $table->dropUnique('character_presences_character_id_unique');
            $table->unique(['room_id', 'character_id'], 'character_presences_room_id_character_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('character_presences', function (Blueprint $table) {
            $table->dropUnique('character_presences_room_id_character_id_unique');
            $table->unique('character_id', 'character_presences_character_id_unique');
        });
    }
};
