<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_conversation_reads', function (Blueprint $table) {
            $table->id();

            $table->foreignId('character_id')
                ->constrained('characters')
                ->cascadeOnDelete();

            $table->foreignId('conversation_id')
                ->constrained('rooms')
                ->cascadeOnDelete();

            $table->foreignId('last_read_message_id')
                ->nullable()
                ->constrained('messages')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['character_id', 'conversation_id']);
            $table->index(['conversation_id', 'last_read_message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_conversation_reads');
    }
};
