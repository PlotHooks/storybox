<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rp_ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 16);
            $table->string('title');
            $table->text('body');
            $table->json('tags')->nullable();
            $table->boolean('is_nsfw')->default(false);
            $table->timestamp('refreshed_at')->nullable()->index();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'expires_at']);
            $table->index(['character_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rp_ads');
    }
};
