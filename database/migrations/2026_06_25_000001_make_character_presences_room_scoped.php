<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CHARACTER_FOREIGN_KEY = 'character_presences_character_id_foreign';
    private const CHARACTER_UNIQUE_KEY = 'character_presences_character_id_unique';
    private const ROOM_CHARACTER_UNIQUE_KEY = 'character_presences_room_id_character_id_unique';

    public function up(): void
    {
        Schema::table('character_presences', function (Blueprint $table) {
            $table->dropForeign(['character_id']);
            $table->dropUnique(self::CHARACTER_UNIQUE_KEY);
            $table->unique(['room_id', 'character_id'], self::ROOM_CHARACTER_UNIQUE_KEY);
            $table->foreign('character_id', self::CHARACTER_FOREIGN_KEY)
                ->references('id')
                ->on('characters')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('character_presences', function (Blueprint $table) {
            $table->dropForeign(['character_id']);
            $table->dropUnique(self::ROOM_CHARACTER_UNIQUE_KEY);
            $table->unique('character_id', self::CHARACTER_UNIQUE_KEY);
            $table->foreign('character_id', self::CHARACTER_FOREIGN_KEY)
                ->references('id')
                ->on('characters')
                ->cascadeOnDelete();
        });
    }
};
