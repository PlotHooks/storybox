<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Your app uses room_user_presence for DM membership.
        // If it already exists, do nothing.
        if (Schema::hasTable('room_user_presence')) {
            return;
        }

        Schema::create('room_user_presence', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('room_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->unique(['room_id', 'user_id']);
            $table->index(['user_id', 'room_id']);
        });
    }

    public function down(): void
    {
        // Only drops what this migration would have created
        Schema::dropIfExists('room_user_presence');
    }
};
