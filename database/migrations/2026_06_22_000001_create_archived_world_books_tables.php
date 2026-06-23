<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archived_world_books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('original_room_id')->unique();
            $table->string('original_room_name');
            $table->timestamp('room_deleted_at')->nullable();
            $table->unsignedInteger('entry_count')->default(0);
            $table->string('recovery_key', 64)->unique();
            $table->timestamp('archived_at');
            $table->timestamps();

            $table->index(['owner_user_id', 'archived_at']);
        });

        Schema::create('archived_world_book_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('archived_world_book_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('source_world_book_entry_id');
            $table->string('status', 20)->index();
            $table->unsignedInteger('sort_order')->nullable();
            $table->string('title')->nullable();
            $table->string('category', 40)->nullable()->index();
            $table->string('image_url', 2048)->nullable();
            $table->text('body')->nullable();
            $table->json('tags')->nullable();
            $table->string('draft_title')->nullable();
            $table->string('draft_category', 40)->nullable()->index();
            $table->string('draft_image_url', 2048)->nullable();
            $table->text('draft_body')->nullable();
            $table->json('draft_tags')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_note')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->unique(['archived_world_book_id', 'source_world_book_entry_id'], 'archived_world_book_entries_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archived_world_book_entries');
        Schema::dropIfExists('archived_world_books');
    }
};
