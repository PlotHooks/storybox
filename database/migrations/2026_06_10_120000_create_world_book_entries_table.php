<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('world_book_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_character_id')->constrained('characters')->cascadeOnDelete();
            $table->foreignId('reviewed_by_character_id')->nullable()->constrained('characters')->nullOnDelete();
            $table->string('status', 20)->index();
            $table->string('title')->nullable();
            $table->string('category', 40)->nullable()->index();
            $table->string('image_url', 2048)->nullable();
            $table->text('body')->nullable();
            $table->string('draft_title')->nullable();
            $table->string('draft_category', 40)->nullable()->index();
            $table->string('draft_image_url', 2048)->nullable();
            $table->text('draft_body')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['room_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('world_book_entries');
    }
};
