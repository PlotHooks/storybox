<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_pinned_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_character_id')->constrained('characters')->cascadeOnDelete();
            $table->string('title');
            $table->string('category', 40)->index();
            $table->text('body');
            $table->timestamp('expires_at')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['room_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_pinned_notes');
    }
};
