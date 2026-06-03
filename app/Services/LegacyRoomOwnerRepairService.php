<?php

namespace App\Services;

use App\Models\Character;
use App\Models\Message;
use App\Models\Room;
use App\Models\RoomAccessEntry;
use Illuminate\Support\Collection;

class LegacyRoomOwnerRepairService
{
    public function inspectUnownedPublicRooms(): Collection
    {
        return Room::query()
            ->where('type', Room::TYPE_PUBLIC)
            ->whereNull('owner_character_id')
            ->orderBy('id')
            ->get()
            ->map(fn (Room $room) => $this->inspectRoom($room));
    }

    public function inspectRoom(Room $room): array
    {
        $creatorInference = $this->inferOwnerFromCreator($room);
        if ($creatorInference !== null) {
            return [
                'room' => $room,
                'candidate' => $creatorInference['character'],
                'reason' => $creatorInference['reason'],
                'would_change' => true,
                'auto_assignable' => true,
                'manual_review_required' => false,
            ];
        }

        $messageSuggestion = $this->suggestOwnerFromEarlyMessages($room);
        if ($messageSuggestion !== null) {
            return [
                'room' => $room,
                'candidate' => $messageSuggestion['character'],
                'reason' => $messageSuggestion['reason'],
                'would_change' => false,
                'auto_assignable' => false,
                'manual_review_required' => true,
            ];
        }

        return [
            'room' => $room,
            'candidate' => null,
            'reason' => 'Manual review required: no unambiguous owner inference found.',
            'would_change' => false,
            'auto_assignable' => false,
            'manual_review_required' => true,
        ];
    }

    public function repairUnownedPublicRooms(bool $apply = false): array
    {
        $inspections = $this->inspectUnownedPublicRooms();
        $updated = 0;

        if ($apply) {
            foreach ($inspections as $inspection) {
                if (! $inspection['auto_assignable'] || ! $inspection['candidate'] instanceof Character) {
                    continue;
                }

                $this->assignOwner($inspection['room'], $inspection['candidate']);
                $updated++;
            }
        }

        return [
            'inspections' => $inspections,
            'updated' => $updated,
        ];
    }

    public function assignOwner(Room $room, Character $character): void
    {
        if (! $room->isPublicRoom()) {
            throw new \InvalidArgumentException('Only public rooms can be assigned an owner.');
        }

        $room->forceFill([
            'owner_character_id' => $character->id,
        ])->save();

        RoomAccessEntry::query()
            ->where('room_id', $room->id)
            ->where('character_id', $character->id)
            ->delete();
    }

    private function inferOwnerFromCreator(Room $room): ?array
    {
        $creatorUserIds = collect([
            $room->created_by,
            $room->user_id,
        ])->filter(fn ($value) => (int) $value > 0)
            ->unique()
            ->values();

        if ($creatorUserIds->count() !== 1) {
            return null;
        }

        $creatorUserId = (int) $creatorUserIds->first();
        $characters = Character::query()
            ->where('user_id', $creatorUserId)
            ->orderBy('id')
            ->get();

        if ($characters->count() !== 1) {
            return null;
        }

        return [
            'character' => $characters->first(),
            'reason' => 'Creator user owns exactly one character.',
        ];
    }

    private function suggestOwnerFromEarlyMessages(Room $room): ?array
    {
        $earlyCharacterIds = Message::query()
            ->where('room_id', $room->id)
            ->whereNotNull('character_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(3)
            ->pluck('character_id')
            ->filter()
            ->values();

        if ($earlyCharacterIds->isEmpty()) {
            return null;
        }

        $candidateId = (int) $earlyCharacterIds->first();
        $candidate = Character::find($candidateId);
        if (! $candidate) {
            return null;
        }

        if ($earlyCharacterIds->unique()->count() === 1) {
            return [
                'character' => $candidate,
                'reason' => 'Suggestion only: the earliest message cluster was authored by a single character.',
            ];
        }

        return [
            'character' => $candidate,
            'reason' => 'Suggestion only: the earliest message was authored by this character, but early room authors are mixed. Manual review required.',
        ];
    }
}
