<?php

use App\Models\CharacterProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('template_type')->default(CharacterProfile::TEMPLATE_STORYBOX);
            $table->string('tagline', 255)->nullable();
            $table->string('avatar_url', 2048)->nullable();
            $table->string('banner_url', 2048)->nullable();
            $table->text('biography')->nullable();
            $table->text('hooks')->nullable();
            $table->json('external_links')->nullable();
            $table->longText('custom_html')->nullable();
            $table->longText('custom_css')->nullable();
            $table->longText('custom_js')->nullable();
            $table->boolean('custom_profile_enabled')->default(false);
            $table->boolean('custom_profile_disabled_by_admin')->default(false);
            $table->timestamps();
        });

        DB::table('characters')
            ->orderBy('id')
            ->select(['id', 'avatar'])
            ->lazy()
            ->each(function (object $character): void {
                $avatar = is_string($character->avatar) ? trim($character->avatar) : null;
                $scheme = $avatar ? parse_url($avatar, PHP_URL_SCHEME) : null;

                DB::table('character_profiles')->insert([
                    'character_id' => $character->id,
                    'template_type' => CharacterProfile::TEMPLATE_STORYBOX,
                    'avatar_url' => in_array(strtolower((string) $scheme), ['http', 'https'], true) ? $avatar : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_profiles');
    }
};
