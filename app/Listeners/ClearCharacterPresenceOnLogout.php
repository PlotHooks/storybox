<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\DB;

class ClearCharacterPresenceOnLogout
{
    public function handle(Logout $event): void
    {
        $user = $event->user;

        if (! $user) {
            return;
        }

        DB::table('character_presences')
            ->whereIn('character_id', function ($q) use ($user) {
                $q->select('id')
                  ->from('characters')
                  ->where('user_id', $user->id);
            })
            ->delete();
    }
}
