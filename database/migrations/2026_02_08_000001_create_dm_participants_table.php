<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dm_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('room_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('character_id');
            $table->timestamps();

            $table->unique(['room_id', 'user_id']);
            $table->unique(['room_id', 'character_id']);

            $table->index(['user_id']);
            $table->index(['character_id']);
            $table->index(['room_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dm_participants');
    }
};
