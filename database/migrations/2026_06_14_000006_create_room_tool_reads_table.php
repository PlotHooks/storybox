<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_tool_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('tool', 40);
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['user_id', 'room_id', 'tool']);
            $table->index(['room_id', 'tool']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_tool_reads');
    }
};
