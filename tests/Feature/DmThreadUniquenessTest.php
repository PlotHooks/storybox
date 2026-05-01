<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DmThreadUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_character_pair_returns_the_same_dm_room(): void
    {
        [$firstUser, $firstCharacter] = $this->createUserWithCharacter();
        [$secondUser, $secondCharacter] = $this->createUserWithCharacter();

        $firstResponse = $this->actingAs($firstUser)->postJson(route('dms.start'), [
            'my_character_id' => $firstCharacter->id,
            'other_character_id' => $secondCharacter->id,
        ])->assertOk();

        $secondResponse = $this->actingAs($firstUser)->postJson(route('dms.start'), [
            'my_character_id' => $firstCharacter->id,
            'other_character_id' => $secondCharacter->id,
        ])->assertOk();

        $this->assertSame($firstResponse->json('slug'), $secondResponse->json('slug'));
        $this->assertSame(1, Room::where('type', 'dm')->count());
    }

    public function test_reversed_character_pair_returns_the_same_dm_room(): void
    {
        [$firstUser, $firstCharacter] = $this->createUserWithCharacter();
        [$secondUser, $secondCharacter] = $this->createUserWithCharacter();

        $firstResponse = $this->actingAs($firstUser)->postJson(route('dms.start'), [
            'my_character_id' => $firstCharacter->id,
            'other_character_id' => $secondCharacter->id,
        ])->assertOk();

        $secondResponse = $this->actingAs($secondUser)->postJson(route('dms.start'), [
            'my_character_id' => $secondCharacter->id,
            'other_character_id' => $firstCharacter->id,
        ])->assertOk();

        $this->assertSame($firstResponse->json('slug'), $secondResponse->json('slug'));
        $this->assertSame(1, Room::where('type', 'dm')->count());
    }

    public function test_duplicate_dm_key_is_blocked_at_the_database_level(): void
    {
        [$firstUser, $firstCharacter] = $this->createUserWithCharacter();
        [$secondUser, $secondCharacter] = $this->createUserWithCharacter();

        $dmKey = Room::normalizedDmKey($firstCharacter->id, $secondCharacter->id);

        Room::create([
            'name' => 'DM',
            'slug' => 'dm-' . Str::random(20),
            'user_id' => $firstUser->id,
            'created_by' => $firstUser->id,
            'type' => 'dm',
            'dm_key' => $dmKey,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        Room::create([
            'name' => 'DM',
            'slug' => 'dm-' . Str::random(20),
            'user_id' => $secondUser->id,
            'created_by' => $secondUser->id,
            'type' => 'dm',
            'dm_key' => $dmKey,
        ]);
    }

    public function test_non_dm_rooms_are_unaffected_by_dm_key_uniqueness(): void
    {
        $user = User::factory()->create();

        Room::create([
            'name' => 'Public One',
            'slug' => 'public-' . Str::random(20),
            'user_id' => $user->id,
            'created_by' => $user->id,
            'type' => 'public',
        ]);

        Room::create([
            'name' => 'Public Two',
            'slug' => 'public-' . Str::random(20),
            'user_id' => $user->id,
            'created_by' => $user->id,
            'type' => 'public',
        ]);

        $this->assertSame(2, Room::where('type', 'public')->count());
        $this->assertSame(0, Room::where('type', 'public')->whereNotNull('dm_key')->count());
    }

    private function createUserWithCharacter(): array
    {
        $user = User::factory()->create();

        $character = Character::create([
            'user_id' => $user->id,
            'name' => 'Character ' . Str::random(8),
            'slug' => 'character-' . Str::random(16),
        ]);

        return [$user, $character];
    }
}
