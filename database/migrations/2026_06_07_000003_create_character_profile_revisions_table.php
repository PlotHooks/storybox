<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_profile_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_profile_id')->constrained('character_profiles')->cascadeOnDelete();
            $table->longText('custom_html')->nullable();
            $table->longText('custom_css')->nullable();
            $table->longText('custom_js')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_profile_revisions');
    }
};
