<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_presences', function (Blueprint $table) {
            $table->id();

            $table->foreignId('character_id')
                ->constrained()
                ->onDelete('cascade');

            $table->foreignId('room_id')
                ->constrained()
                ->onDelete('cascade');

            $table->timestamp('last_seen_at')->nullable()->index();

            $table->timestamps();

            // Enforce: one room per character
            $table->unique('character_id');

            // Speed up room sidebar counts
            $table->index(['room_id', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_presences');
    }
};
