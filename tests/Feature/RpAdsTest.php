<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Room;
use App\Models\RpAd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class RpAdsTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_room_ad_requires_a_linked_room(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Owner');

        $this->actingAs($user)
            ->postJson(route('rp-ads.store'), [
                'character_id' => $character->id,
                'type' => RpAd::TYPE_ROOM,
                'title' => 'Looking for haunted castle RP',
                'body' => 'Join me for gothic mystery.',
                'tags' => 'Horror, Slow Burn',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('room_id');
    }

    public function test_creating_a_dm_ad_does_not_require_a_linked_room(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Owner');

        $this->actingAs($user)
            ->postJson(route('rp-ads.store'), [
                'character_id' => $character->id,
                'type' => RpAd::TYPE_DM,
                'title' => 'Space opera plotting',
                'body' => 'Seeking longform sci-fi DM scenes.',
                'tags' => 'Sci-Fi, Multi-Para',
            ])
            ->assertOk()
            ->assertJsonPath('ad.type', RpAd::TYPE_DM)
            ->assertJsonPath('ad.room', null);
    }

    public function test_one_active_ad_per_character_is_enforced(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Owner');
        $this->createAd($character, RpAd::TYPE_DM);

        $this->actingAs($user)
            ->postJson(route('rp-ads.store'), [
                'character_id' => $character->id,
                'type' => RpAd::TYPE_DM,
                'title' => 'Second ad',
                'body' => 'This should fail.',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('character_id');
    }

    public function test_expired_ads_are_hidden_from_public_browsing(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Owner');
        $expiredAd = $this->createAd($character, RpAd::TYPE_DM, expiresAt: now()->subHour());
        $activeAd = $this->createAd($character, RpAd::TYPE_DM, title: 'Still active');

        $response = $this->actingAs($user)->getJson(route('rp-ads.index'));

        $response->assertOk()
            ->assertJsonPath('dm_ads.0.id', $activeAd->id);

        $publicDmAds = collect($response->json('dm_ads'));
        $this->assertFalse($publicDmAds->contains(fn (array $ad) => (int) $ad['id'] === (int) $expiredAd->id));
    }

    public function test_expired_ads_remain_visible_to_the_owner(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Owner');
        $expiredAd = $this->createAd($character, RpAd::TYPE_DM, expiresAt: now()->subHour());

        $response = $this->actingAs($user)->getJson(route('rp-ads.index'));

        $myAds = collect($response->json('my_ads'));

        $this->assertTrue($myAds->contains(fn (array $ad) => (int) $ad['id'] === (int) $expiredAd->id && (bool) $ad['is_expired'] === true));
    }

    public function test_refreshing_an_expired_ad_makes_it_active_again(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Owner');
        $ad = $this->createAd($character, RpAd::TYPE_DM, expiresAt: now()->subDay());

        $this->travelTo(Carbon::parse('2026-07-09 12:00:00'));

        $this->actingAs($user)
            ->postJson(route('rp-ads.refresh', $ad))
            ->assertOk()
            ->assertJsonPath('ad.is_active', true);

        $this->assertTrue($ad->fresh()->expires_at->greaterThan(now()));
    }

    public function test_refreshing_sets_expires_at_to_seven_days_from_now(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Owner');
        $ad = $this->createAd($character, RpAd::TYPE_DM, expiresAt: now()->subDay());

        $this->travelTo(Carbon::parse('2026-07-09 12:00:00'));

        $this->actingAs($user)
            ->postJson(route('rp-ads.refresh', $ad))
            ->assertOk();

        $this->assertTrue($ad->fresh()->expires_at->equalTo(now()->addDays(7)));
    }

    public function test_nsfw_ads_mark_public_bodies_as_obscured(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Owner');
        $this->createAd($character, RpAd::TYPE_DM, isNsfw: true);

        $this->actingAs($user)
            ->getJson(route('rp-ads.index'))
            ->assertOk()
            ->assertJsonPath('dm_ads.0.body_obscured', true);

        $this->actingAs($user)
            ->get(route('rooms.index'))
            ->assertSee('blur-sm', false);
    }

    public function test_room_ad_enter_room_action_links_to_the_room(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Owner');
        $room = $this->createRoom($user, $character);
        $ad = $this->createAd($character, RpAd::TYPE_ROOM, room: $room);

        $this->actingAs($user)
            ->getJson(route('rp-ads.index'))
            ->assertOk()
            ->assertJsonPath('room_ads.0.id', $ad->id)
            ->assertJsonPath('room_ads.0.action.url', route('rooms.show', $room->slug));
    }

    public function test_dm_ad_start_dm_uses_existing_dm_start_route_correctly(): void
    {
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        [$advertiserUser, $advertiserCharacter] = $this->createUserWithCharacter('Advertiser');
        $this->createAd($advertiserCharacter, RpAd::TYPE_DM);

        $response = $this->actingAs($viewerUser)->getJson(route('rp-ads.index'));
        $adPayload = collect($response->json('dm_ads'))->firstWhere('character.id', $advertiserCharacter->id);

        $this->assertNotNull($adPayload);

        $this->actingAs($viewerUser)
            ->postJson(route('dms.start'), [
                'my_character_id' => $viewerCharacter->id,
                'other_character_id' => $adPayload['action']['other_character_id'],
            ])
            ->assertOk()
            ->assertJsonStructure(['slug']);
    }

    public function test_users_cannot_create_ads_for_characters_they_do_not_own(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$otherUser] = $this->createUserWithCharacter('Other');

        $this->actingAs($otherUser)
            ->postJson(route('rp-ads.store'), [
                'character_id' => $ownerCharacter->id,
                'type' => RpAd::TYPE_DM,
                'title' => 'Forbidden',
                'body' => 'Nope.',
            ])
            ->assertForbidden();
    }

    public function test_users_cannot_link_room_ads_to_rooms_they_cannot_access(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        $hiddenRoom = $this->createRoom($ownerUser, $ownerCharacter, Room::VISIBILITY_HIDDEN);

        $this->actingAs($viewerUser)
            ->postJson(route('rp-ads.store'), [
                'character_id' => $viewerCharacter->id,
                'type' => RpAd::TYPE_ROOM,
                'room_id' => $hiddenRoom->id,
                'title' => 'Trying to advertise',
                'body' => 'Should be blocked.',
            ])
            ->assertForbidden();
    }

    private function createUserWithCharacter(string $name = 'Character'): array
    {
        $user = User::factory()->create();

        return [$user, $this->createCharacter($user, $name)];
    }

    private function createCharacter(User $user, string $name): Character
    {
        return Character::create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => 'character-' . Str::random(16),
        ]);
    }

    private function createRoom(User $user, Character $ownerCharacter, string $visibility = Room::VISIBILITY_PUBLIC): Room
    {
        return Room::create([
            'name' => 'Room ' . Str::random(8),
            'slug' => 'room-' . Str::random(16),
            'user_id' => $user->id,
            'created_by' => $user->id,
            'type' => Room::TYPE_PUBLIC,
            'visibility' => $visibility,
            'owner_character_id' => $ownerCharacter->id,
        ]);
    }

    private function createAd(
        Character $character,
        string $type,
        ?Room $room = null,
        ?Carbon $expiresAt = null,
        string $title = 'Ad title',
        bool $isNsfw = false,
    ): RpAd {
        return RpAd::create([
            'character_id' => $character->id,
            'room_id' => $room?->id,
            'type' => $type,
            'title' => $title,
            'body' => 'Ad body text.',
            'tags' => ['Fantasy', 'Slow Burn'],
            'is_nsfw' => $isNsfw,
            'refreshed_at' => now()->subHour(),
            'expires_at' => $expiresAt ?? now()->addDays(7),
        ]);
    }
}
