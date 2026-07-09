<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use App\Services\MarkPublicRoomRead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarkPublicRoomReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_missing_user_room_state_without_auto_following(): void
    {
        $user = User::factory()->create();
        $character = Character::create([
            'user_id' => $user->id,
            'name' => 'Character '.Str::random(8),
            'slug' => 'character-'.Str::random(16),
        ]);

        $room = Room::create([
            'name' => 'Room '.Str::random(8),
            'slug' => 'room-'.Str::random(16),
            'user_id' => $user->id,
            'created_by' => $user->id,
            'type' => Room::TYPE_PUBLIC,
        ]);

        $message = Message::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'character_id' => $character->id,
            'body' => 'Latest message.',
        ]);

        app(MarkPublicRoomRead::class)($user->id, $room->id, $message->id);

        $this->assertDatabaseHas('user_room_states', [
            'user_id' => $user->id,
            'room_id' => $room->id,
            'is_following' => false,
            'last_read_message_id' => $message->id,
        ]);
    }

    public function test_it_does_not_touch_current_user_room_state(): void
    {
        Carbon::setTestNow('2026-07-07 12:00:00');

        $user = User::factory()->create();
        $character = Character::create([
            'user_id' => $user->id,
            'name' => 'Character '.Str::random(8),
            'slug' => 'character-'.Str::random(16),
        ]);

        $room = Room::create([
            'name' => 'Room '.Str::random(8),
            'slug' => 'room-'.Str::random(16),
            'user_id' => $user->id,
            'created_by' => $user->id,
            'type' => Room::TYPE_PUBLIC,
        ]);

        $message = Message::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'character_id' => $character->id,
            'body' => 'Latest message.',
        ]);

        $timestamp = Carbon::parse('2026-07-07 11:00:00');

        DB::table('user_room_states')->insert([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'is_following' => true,
            'last_read_message_id' => $message->id,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        app(MarkPublicRoomRead::class)($user->id, $room->id, $message->id);

        $state = DB::table('user_room_states')
            ->where('user_id', $user->id)
            ->where('room_id', $room->id)
            ->first();

        $this->assertNotNull($state);
        $this->assertSame($message->id, (int) $state->last_read_message_id);
        $this->assertSame($timestamp->toDateTimeString(), Carbon::parse($state->updated_at)->toDateTimeString());

        Carbon::setTestNow();
    }
}
