<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\CharacterBlock;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DmWindowComposerTest extends TestCase
{
    use RefreshDatabase;

    public function test_dm_window_composer_searches_valid_targets_and_reuses_existing_room(): void
    {
        [$senderUser, $senderCharacter] = $this->createUserWithCharacter();
        $otherOwnedCharacter = $this->createCharacter($senderUser);
        [$targetUser, $targetCharacter] = $this->createUserWithCharacter();

        $searchResponse = $this->actingAs($senderUser)
            ->getJson(route('dms.targets', [
                'from_character_id' => $senderCharacter->id,
                'query' => substr($targetCharacter->name, 0, 6),
            ]))
            ->assertOk();

        $searchResponse
            ->assertJsonFragment([
                'id' => $targetCharacter->id,
                'name' => $targetCharacter->name,
            ])
            ->assertJsonMissingPath('targets.0.user_name')
            ->assertJsonMissingPath('targets.0.user_id')
            ->assertJsonMissingPath('targets.0.owner')
            ->assertJsonMissing([
                'id' => $otherOwnedCharacter->id,
            ]);

        $firstStart = $this->actingAs($senderUser)
            ->postJson(route('dms.start'), [
                'my_character_id' => $senderCharacter->id,
                'other_character_id' => $targetCharacter->id,
            ])
            ->assertOk();

        $secondStart = $this->actingAs($senderUser)
            ->postJson(route('dms.start'), [
                'my_character_id' => $senderCharacter->id,
                'other_character_id' => $targetCharacter->id,
            ])
            ->assertOk();

        $this->assertSame($firstStart->json('slug'), $secondStart->json('slug'));
        $this->assertSame(1, Room::where('type', Room::TYPE_DM)->count());
    }

    public function test_dm_window_composer_search_excludes_blocked_targets(): void
    {
        [$senderUser, $senderCharacter] = $this->createUserWithCharacter();
        [$allowedUser, $allowedCharacter] = $this->createUserWithCharacter();
        [$blockedUser, $blockedCharacter] = $this->createUserWithCharacter();

        CharacterBlock::create([
            'blocker_character_id' => $senderCharacter->id,
            'blocked_character_id' => $blockedCharacter->id,
        ]);

        $response = $this->actingAs($senderUser)
            ->getJson(route('dms.targets', [
                'from_character_id' => $senderCharacter->id,
                'query' => 'Character',
            ]))
            ->assertOk();

        $response
            ->assertJsonFragment([
                'id' => $allowedCharacter->id,
                'name' => $allowedCharacter->name,
            ])
            ->assertJsonMissing([
                'id' => $blockedCharacter->id,
            ]);
    }


    public function test_dm_window_composer_search_does_not_match_owner_user_name(): void
    {
        [$senderUser, $senderCharacter] = $this->createUserWithCharacter('Sender User');
        [$targetUser, $targetCharacter] = $this->createUserWithCharacter('Hidden Owner');

        $this->actingAs($senderUser)
            ->getJson(route('dms.targets', [
                'from_character_id' => $senderCharacter->id,
                'query' => 'Hidden Owner',
            ]))
            ->assertOk()
            ->assertExactJson(['targets' => []]);

        $this->actingAs($senderUser)
            ->getJson(route('dms.targets', [
                'from_character_id' => $senderCharacter->id,
                'query' => $targetCharacter->name,
            ]))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $targetCharacter->id,
                'name' => $targetCharacter->name,
            ]);
    }

    public function test_dm_window_composer_search_rejects_unowned_sender_character(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$otherUser] = $this->createUserWithCharacter();

        $this->actingAs($otherUser)
            ->getJson(route('dms.targets', [
                'from_character_id' => $ownerCharacter->id,
                'query' => 'Character',
            ]))
            ->assertForbidden();
    }

    public function test_dm_start_rejects_unowned_sender_character(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$otherUser, $otherCharacter] = $this->createUserWithCharacter();
        [, $targetCharacter] = $this->createUserWithCharacter();

        $this->actingAs($otherUser)
            ->postJson(route('dms.start'), [
                'my_character_id' => $ownerCharacter->id,
                'other_character_id' => $targetCharacter->id,
            ])
            ->assertForbidden();

        $this->assertSame(0, Room::where('type', Room::TYPE_DM)->count());
    }


    public function test_dm_index_exposes_public_profile_url_for_other_participant(): void
    {
        [$firstUser, $firstCharacter] = $this->createUserWithCharacter();
        [$secondUser, $secondCharacter] = $this->createUserWithCharacter();
        $room = $this->createDmRoom($firstUser, $firstCharacter, $secondUser, $secondCharacter);

        $this->actingAs($firstUser)
            ->getJson(route('dms.index'))
            ->assertOk()
            ->assertJsonFragment([
                'slug' => $room->slug,
                'other_character_id' => $secondCharacter->id,
                'other_character_profile_url' => route('characters.profile.show', $secondCharacter),
            ]);
    }

    public function test_dm_messages_expose_public_profile_url_for_message_character(): void
    {
        [$firstUser, $firstCharacter] = $this->createUserWithCharacter();
        [$secondUser, $secondCharacter] = $this->createUserWithCharacter();
        $room = $this->createDmRoom($firstUser, $firstCharacter, $secondUser, $secondCharacter);
        $message = $this->createMessage($room, $secondUser, $secondCharacter, 'Hello from the other side.');

        $this->actingAs($firstUser)
            ->getJson(route('dms.messages.index', $room->slug))
            ->assertOk()
            ->assertJsonPath('room.other_character_profile_url', route('characters.profile.show', $secondCharacter))
            ->assertJsonPath('messages.0.character.profile_url', route('characters.profile.show', $secondCharacter));
    }

    public function test_dm_send_response_exposes_public_profile_url_for_sender_character(): void
    {
        [$firstUser, $firstCharacter] = $this->createUserWithCharacter();
        [$secondUser, $secondCharacter] = $this->createUserWithCharacter();
        $room = $this->createDmRoom($firstUser, $firstCharacter, $secondUser, $secondCharacter);

        $this->actingAs($firstUser)
            ->postJson(route('dms.messages.store', $room->slug), [
                'body' => 'New DM message.',
            ])
            ->assertOk()
            ->assertJsonPath('message.character.profile_url', route('characters.profile.show', $firstCharacter));
    }


    private function createDmRoom(User $firstUser, Character $firstCharacter, User $secondUser, Character $secondCharacter): Room
    {
        $room = Room::create([
            'name' => 'DM',
            'slug' => 'dm-' . Str::random(16),
            'user_id' => $firstUser->id,
            'created_by' => $firstUser->id,
            'type' => Room::TYPE_DM,
            'visibility' => Room::VISIBILITY_PUBLIC,
            'dm_key' => Room::normalizedDmKey($firstCharacter->id, $secondCharacter->id),
        ]);

        $now = now();

        \DB::table('dm_participants')->insert([
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

    private function createMessage(Room $room, User $user, Character $character, string $body)
    {
        return \App\Models\Message::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'character_id' => $character->id,
            'body' => $body,
        ]);
    }

    private function createUserWithCharacter(?string $userName = null): array
    {
        $user = User::factory()->create([
            'name' => $userName ?? ('user_' . Str::random(8)),
        ]);

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
}