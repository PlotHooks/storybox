<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_character_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('character_id')->constrained('characters')->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();

            $table->unique(['room_id', 'character_id'], 'room_char_role_uq');
            $table->index(['room_id', 'role'], 'room_role_room_idx');
            $table->index(['character_id', 'role'], 'room_role_char_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_character_roles');
    }
};
