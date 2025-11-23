<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();

            // Which room this message belongs to
            $table->foreignId('room_id')
                ->constrained()
                ->cascadeOnDelete();

            // Which user sent it
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Optional: which character they were playing as
            $table->foreignId('character_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // The actual chat text
            $table->text('body');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
