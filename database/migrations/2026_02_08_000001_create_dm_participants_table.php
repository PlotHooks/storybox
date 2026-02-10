<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dm_participants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('character_id')->constrained('characters')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['room_id', 'user_id']);
            $table->unique(['room_id', 'character_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dm_participants');
    }
};
