<?php

namespace App\Providers;

use App\Models\Room;
use App\Models\Message;
use App\Models\Character;
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

        // Room access (future-proof)
        Gate::define('access-room', function ($user, Room $room) {
            if ($room->type === 'public') return true;

            if ($room->type === 'dm') {
                return \DB::table('dm_participants')
                    ->where('room_id', $room->id)
                    ->where('user_id', $user->id)
                    ->exists();
            }

            return false;
        });
    }
}
