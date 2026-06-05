<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\CharacterConversationRead;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdentityAuthorizationHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_spoofed_character_id_does_not_consume_that_characters_rate_limit_bucket(): void
    {
        config([
            'rate_limits.chat_message_character_max' => 1,
            'rate_limits.chat_message_character_decay' => 60,
            'rate_limits.chat_message_user_max' => 20,
        ]);

        [$firstUser, $firstCharacter] = $this->createUserWithCharacter();
        [$secondUser, $secondCharacter] = $this->createUserWithCharacter();
        $room = $this->createPublicRoom($firstUser);

        $this->actingAs($firstUser)->postJson(route('rooms.messages.store', $room->slug), [
            'character_id' => $secondCharacter->id,
            'body' => 'Spoofed character id.',
        ])->assertForbidden();

        $this->actingAs($secondUser)->postJson(route('rooms.messages.store', $room->slug), [
            'character_id' => $secondCharacter->id,
            'body' => 'Legitimate first send.',
        ])->assertOk();

        $this->actingAs($secondUser)->postJson(route('rooms.messages.store', $room->slug), [
            'character_id' => $secondCharacter->id,
            'body' => 'Legitimate second send.',
        ])->assertTooManyRequests();
    }

    public function test_successful_message_send_does_not_emit_suspicious_authorization_warning(): void
    {
        Log::spy();

        [$user, $character] = $this->createUserWithCharacter();
        $room = $this->createPublicRoom($user);

        $this->actingAs($user)->postJson(route('rooms.messages.store', $room->slug), [
            'character_id' => $character->id,
            'body' => 'Legitimate send.',
        ])->assertOk();

        Log::shouldNotHaveReceived('warning');
    }

    public function test_public_room_show_marks_user_room_read_without_touching_character_read_state(): void
    {
        [$user, $character] = $this->createUserWithCharacter();
        [$otherUser, $otherCharacter] = $this->createUserWithCharacter();
        $room = $this->createPublicRoom($otherUser);
        $message = $this->createMessage($room, $otherUser, $otherCharacter, 'Unread message.');

        $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk();

        $this->assertDatabaseHas('user_room_states', [
            'user_id' => $user->id,
            'room_id' => $room->id,
            'last_read_message_id' => $message->id,
        ]);

        $this->assertDatabaseMissing('character_conversation_reads', [
            'character_id' => $character->id,
            'conversation_id' => $room->id,
            'last_read_message_id' => $message->id,
        ]);

        $this->assertSame(0, CharacterConversationRead::count());
    }

    public function test_user_cannot_edit_message_after_losing_dm_participant_membership(): void
    {
        [$user, $character] = $this->createUserWithCharacter();
        [$otherUser, $otherCharacter] = $this->createUserWithCharacter();
        $room = $this->createDmRoom($user, $character, $otherUser, $otherCharacter);
        $message = $this->createMessage($room, $user, $character, 'Original body.');

        DB::table('dm_participants')
            ->where('room_id', $room->id)
            ->where('user_id', $user->id)
            ->delete();

        $this->actingAs($user)->patchJson(route('messages.update', $message), [
            'body' => 'Edited body.',
        ])->assertForbidden();

        $this->assertSame('Original body.', $message->fresh()->body);
    }

    public function test_user_cannot_delete_message_after_losing_dm_participant_membership(): void
    {
        [$user, $character] = $this->createUserWithCharacter();
        [$otherUser, $otherCharacter] = $this->createUserWithCharacter();
        $room = $this->createDmRoom($user, $character, $otherUser, $otherCharacter);
        $message = $this->createMessage($room, $user, $character, 'Delete candidate.');

        DB::table('dm_participants')
            ->where('room_id', $room->id)
            ->where('user_id', $user->id)
            ->delete();

        $this->actingAs($user)->deleteJson(route('messages.delete', $message))
            ->assertForbidden();

        $this->assertFalse($message->fresh()->trashed());
    }

    private function createUserWithCharacter(): array
    {
        $user = User::factory()->create();

        return [$user, $this->createCharacter($user)];
    }

    private function createCharacter(User $user): Character
    {
        return Character::create([
            'user_id' => $user->id,
            'name' => 'Character ' . Str::random(8),
            'slug' => 'character-' . Str::random(16),
        ]);
    }

    private function createPublicRoom(User $user): Room
    {
        return Room::create([
            'name' => 'Room ' . Str::random(8),
            'slug' => 'room-' . Str::random(16),
            'user_id' => $user->id,
            'created_by' => $user->id,
            'type' => 'public',
        ]);
    }

    private function createDmRoom(
        User $firstUser,
        Character $firstCharacter,
        User $secondUser,
        Character $secondCharacter
    ): Room {
        $room = Room::create([
            'name' => 'DM',
            'slug' => 'dm-' . Str::random(16),
            'user_id' => $firstUser->id,
            'created_by' => $firstUser->id,
            'type' => 'dm',
            'dm_key' => Room::normalizedDmKey($firstCharacter->id, $secondCharacter->id),
        ]);

        $now = now();

        DB::table('dm_participants')->insert([
            [
                'room_id' => $room->id,
                'user_id' => $firstUser->id,
                'character_id' => $firstCharacter->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'room_id' => $room->id,
                'user_id' => $secondUser->id,
                'character_id' => $secondCharacter->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        return $room;
    }

    private function createMessage(Room $room, User $user, Character $character, string $body): Message
    {
        return Message::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'character_id' => $character->id,
            'body' => $body,
        ]);
    }
}
