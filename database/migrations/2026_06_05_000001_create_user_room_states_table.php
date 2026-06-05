<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_room_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->boolean('is_following')->default(false);
            $table->foreignId('last_read_message_id')
                ->nullable()
                ->constrained('messages')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'room_id']);
            $table->index(['room_id', 'is_following'], 'urs_room_follow_idx');
        });

        $roomRows = DB::table('rooms')
            ->leftJoin('characters as owners', 'owners.id', '=', 'rooms.owner_character_id')
            ->where('rooms.type', 'public')
            ->select([
                'rooms.id as room_id',
                'rooms.created_by',
                'owners.user_id as owner_user_id',
            ])
            ->orderBy('rooms.id')
            ->get();

        $now = now();

        foreach ($roomRows as $roomRow) {
            $userIds = collect([
                $roomRow->created_by ? (int) $roomRow->created_by : null,
                $roomRow->owner_user_id ? (int) $roomRow->owner_user_id : null,
            ])->filter()->unique()->values();

            foreach ($userIds as $userId) {
                $lastReadMessageId = DB::table('character_conversation_reads as ccr')
                    ->join('characters', 'characters.id', '=', 'ccr.character_id')
                    ->where('characters.user_id', $userId)
                    ->where('ccr.conversation_id', $roomRow->room_id)
                    ->max('ccr.last_read_message_id');

                DB::table('user_room_states')->updateOrInsert(
                    [
                        'user_id' => $userId,
                        'room_id' => $roomRow->room_id,
                    ],
                    [
                        'is_following' => true,
                        'last_read_message_id' => $lastReadMessageId ?: null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_room_states');
    }
};
