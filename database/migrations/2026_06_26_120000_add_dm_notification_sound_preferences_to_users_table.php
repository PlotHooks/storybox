<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('dm_notification_sound_enabled')
                ->default(true)
                ->after('password');
            $table->string('dm_notification_sound_choice', 32)
                ->default(User::DM_NOTIFICATION_SOUND_DEFAULT)
                ->after('dm_notification_sound_enabled');
            $table->string('dm_notification_sound_url', 2048)
                ->nullable()
                ->after('dm_notification_sound_choice');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'dm_notification_sound_enabled',
                'dm_notification_sound_choice',
                'dm_notification_sound_url',
            ]);
        });
    }
};
