<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_contents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('collection')->index();
            $table->text('body');
            $table->unsignedInteger('sort_order')->default(1);
            $table->boolean('is_published')->default(false)->index();
            $table->timestamps();

            $table->index(['collection', 'is_published', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_contents');
    }
};
