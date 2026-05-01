<?php

use App\Models\Room;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('rooms', 'dm_key')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->string('dm_key')->nullable()->after('type');
            });
        }

        $dmParticipants = DB::table('rooms')
            ->join('dm_participants', 'dm_participants.room_id', '=', 'rooms.id')
            ->where('rooms.type', 'dm')
            ->orderBy('rooms.id')
            ->get([
                'rooms.id as room_id',
                'dm_participants.character_id',
            ])
            ->groupBy('room_id');

        $keysByRoom = [];
        $roomsByKey = [];

        foreach ($dmParticipants as $roomId => $participants) {
            $characterIds = $participants
                ->pluck('character_id')
                ->map(fn ($id) => (int) $id)
                ->values();

            $uniqueCharacterIds = $characterIds->unique()->values();

            if ($participants->count() !== 2 || $uniqueCharacterIds->count() !== 2) {
                continue;
            }

            $key = Room::normalizedDmKey($uniqueCharacterIds[0], $uniqueCharacterIds[1]);
            $keysByRoom[(int) $roomId] = $key;
            $roomsByKey[$key][] = (int) $roomId;
        }

        $duplicates = array_filter($roomsByKey, fn (array $roomIds) => count($roomIds) > 1);

        if ($duplicates !== []) {
            $details = collect($duplicates)
                ->map(fn (array $roomIds, string $key) => $key.' => room ids: '.implode(', ', $roomIds))
                ->implode('; ');

            throw new RuntimeException(
                'Duplicate DM rooms exist for character pairs. Resolve duplicates without deleting messages before adding rooms.dm_key uniqueness. '.$details
            );
        }

        foreach ($keysByRoom as $roomId => $key) {
            DB::table('rooms')
                ->where('id', $roomId)
                ->update(['dm_key' => $key]);
        }

        Schema::table('rooms', function (Blueprint $table) {
            $table->unique(['type', 'dm_key'], 'rooms_type_dm_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropUnique('rooms_type_dm_key_unique');
        });

        if (Schema::hasColumn('rooms', 'dm_key')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->dropColumn('dm_key');
            });
        }
    }
};
