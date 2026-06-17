<?php

namespace App\Services;

use App\Events\CharacterKickedFromRoom;
use App\Models\Character;
use App\Models\Room;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RoomEjectionService
{
    public function __construct(
        private readonly RoomParticipationStateService $participationState,
    ) {
    }

    public function eject(Room $room, Character $target, ?Character $actor = null, ?string $reason = null): void
    {
        $this->ejectCharacters($room, collect([$target]), $actor, $reason, 'Room character ejected.');
    }

    public function ejectAccount(Room $room, int $userId, ?Character $actor = null, ?string $reason = null): void
    {
        if ($userId <= 0) {
            return;
        }

        $targets = Character::query()
            ->where('user_id', $userId)
            ->orderBy('id')
            ->get(['id', 'user_id', 'name', 'slug']);

        if ($targets->isEmpty()) {
            return;
        }

        $this->ejectCharacters($room, $targets, $actor, $reason, 'Room account ejected.');
    }

    private function ejectCharacters(Room $room, $targets, ?Character $actor, ?string $reason, string $message): void
    {
        $targetIds = $targets->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        if ($targetIds->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($room, $targetIds, $targets) {
            DB::table('character_presences')
                ->where('room_id', $room->id)
                ->whereIn('character_id', $targetIds->all())
                ->delete();

            foreach ($targets as $target) {
                $this->participationState->clear($room, $target);
            }
        });

        Log::info($message, array_filter([
            'room_id' => $room->id,
            'target_character_ids' => $targetIds->all(),
            'target_user_id' => count(array_unique($targets->pluck('user_id')->all())) === 1 ? (int) $targets->first()->user_id : null,
            'actor_character_id' => $actor?->id,
            'reason' => $reason,
            'occurred_at' => now()->toISOString(),
        ], static fn ($value) => $value !== null && $value !== '' && $value !== []));

        foreach ($targets as $target) {
            event(new CharacterKickedFromRoom($room, $target, $actor, $reason));
        }
    }
}
