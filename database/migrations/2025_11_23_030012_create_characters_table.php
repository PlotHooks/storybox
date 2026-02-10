<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ⚠️ TODO (SECURITY):
// profile_html MUST be sanitized before rendering.
// Do NOT render raw HTML from this column without a whitelist sanitizer
// (e.g. HTMLPurifier / Laravel Purifier).
// This feature is intentionally disabled until sanitization is enforced.
$table->longText('profile_html')->nullable();


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('characters', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->string('name');
        $table->string('avatar')->nullable();
        $table->string('slug')->unique(); // URL-friendly identifier
        $table->longText('profile_html')->nullable();
        $table->json('settings')->nullable(); // preferred colors, tags, etc
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('characters');
    }
};
