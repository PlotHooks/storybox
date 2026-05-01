<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blocker_character_id')->constrained('characters')->cascadeOnDelete();
            $table->foreignId('blocked_character_id')->constrained('characters')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['blocker_character_id', 'blocked_character_id'],
                'character_blocks_unique_pair'
            );
            $table->index('blocker_character_id');
            $table->index('blocked_character_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_blocks');
    }
};
