<?php

namespace App\Providers;

use App\Models\Room;
use App\Models\Message;
use App\Models\Character;
use App\Services\RoomAccessService;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Character ownership
        Gate::define('own-character', function ($user, Character $character) {
            return $character->user_id === $user->id;
        });

        // Message edit/delete
        Gate::define('modify-message', function ($user, Message $message) {
            return $message->user_id === $user->id || ($user->is_admin ?? false);
        });

        // rooms table is the conversation model. This Gate is a legacy user-level fallback;
        // controller message access uses character-level participant helpers.
        Gate::define('access-room', function ($user, Room $conversation) {
            if ($conversation->type === Room::TYPE_PUBLIC) {
                return app(RoomAccessService::class)->canViewRoom($user, $conversation, null);
            }

            if ($conversation->type === Room::TYPE_DM) {
                return \DB::table('dm_participants')
                    ->where('room_id', $conversation->id)
                    ->where('user_id', $user->id)
                    ->exists();
            }

            return false;
        });
    }
}
