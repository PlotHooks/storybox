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
}