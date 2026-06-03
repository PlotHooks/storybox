<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_access_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('character_id')->constrained('characters')->cascadeOnDelete();
            $table->string('type');
            $table->foreignId('created_by_character_id')
                ->nullable()
                ->constrained('characters')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['room_id', 'character_id', 'type'], 'room_access_type_uq');
            $table->index(['room_id', 'type'], 'room_access_room_idx');
            $table->index(['character_id', 'type'], 'room_access_char_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_access_entries');
    }
};
