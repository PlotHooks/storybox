<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Room;
use App\Models\RoomNotice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NoticeBoardTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_can_view_notice_payload_with_prominent_author_metadata(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $activeNotice = RoomNotice::create([
            'room_id' => $room->id,
            'author_character_id' => $ownerCharacter->id,
            'title' => 'Courier Needed',
            'category' => RoomNotice::CATEGORY_JOBS,
            'body' => 'Deliver sealed documents to the harbor office.',
            'reward' => '250 Credits',
            'accent_color' => RoomNotice::ACCENT_BLUE,
            'status' => RoomNotice::STATUS_ACTIVE,
        ]);

        $closedNotice = RoomNotice::create([
            'room_id' => $room->id,
            'author_character_id' => $ownerCharacter->id,
            'title' => 'Goblin Problem',
            'category' => RoomNotice::CATEGORY_BOUNTIES,
            'body' => 'The goblins have already been cleared out.',
            'status' => RoomNotice::STATUS_CLOSED,
        ]);

        $archivedNotice = RoomNotice::create([
            'room_id' => $room->id,
            'author_character_id' => $ownerCharacter->id,
            'title' => 'Old Fair Announcement',
            'category' => RoomNotice::CATEGORY_EVENTS,
            'body' => 'Last month\'s fair archive.',
            'status' => RoomNotice::STATUS_ARCHIVED,
        ]);

        $response = $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->getJson(route('rooms.notice-board.index', $room->slug));

        $response->assertOk()
            ->assertJsonPath('notices.0.id', $activeNotice->id)
            ->assertJsonPath('notices.1.id', $closedNotice->id)
            ->assertJsonPath('notices.0.author_character.name', 'Owner')
            ->assertJsonPath('notices.0.author_character.user_id', $ownerUser->id)
            ->assertJsonPath('notices.0.author_character.avatar', null)
            ->assertJsonPath('notices.0.accent_color', RoomNotice::ACCENT_BLUE)
            ->assertJsonPath('notices.0.accent_color_label', 'Blue');

        $notices = collect($response->json('notices'))->keyBy('id');
        $this->assertSame(RoomNotice::STATUS_ARCHIVED, $notices[$archivedNotice->id]['status']);
    }

    public function test_participant_can_create_notice_as_active_with_owned_active_character(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$participantUser, $participantCharacter] = $this->createUserWithCharacter('Leaf');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $this->actingAs($participantUser)
            ->withSession(['active_character_id' => $participantCharacter->id])
            ->postJson(route('rooms.notice-board.store', $room->slug), [
                'title' => 'Wanted: Thorn',
                'category' => RoomNotice::CATEGORY_WANTED,
                'body' => 'Seeking witnesses who saw Thorn leave the south gate.',
                'reward' => '500 Gold',
                'location' => 'South Gate',
                'expires_at' => '2026-07-01',
                'accent_color' => RoomNotice::ACCENT_RED,
            ])
            ->assertOk()
            ->assertJsonPath('notice.author_character.name', 'Leaf')
            ->assertJsonPath('notice.author_character.user_id', $participantUser->id)
            ->assertJsonPath('notice.status', RoomNotice::STATUS_ACTIVE)
            ->assertJsonPath('notice.accent_color', RoomNotice::ACCENT_RED);

        $this->assertDatabaseHas('room_notices', [
            'room_id' => $room->id,
            'author_character_id' => $participantCharacter->id,
            'title' => 'Wanted: Thorn',
            'category' => RoomNotice::CATEGORY_WANTED,
            'reward' => '500 Gold',
            'location' => 'South Gate',
            'accent_color' => RoomNotice::ACCENT_RED,
            'status' => RoomNotice::STATUS_ACTIVE,
        ]);
    }

    public function test_author_can_edit_own_notice_and_change_status(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$authorUser, $authorCharacter] = $this->createUserWithCharacter('Captain Voss');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $notice = RoomNotice::create([
            'room_id' => $room->id,
            'author_character_id' => $authorCharacter->id,
            'title' => 'Old Posting',
            'category' => RoomNotice::CATEGORY_SERVICES,
            'body' => 'Original body.',
            'status' => RoomNotice::STATUS_ACTIVE,
        ]);

        $this->actingAs($authorUser)
            ->withSession(['active_character_id' => $authorCharacter->id])
            ->patchJson(route('rooms.notice-board.update', [$room->slug, $notice]), [
                'title' => 'Ship Escort Available',
                'category' => RoomNotice::CATEGORY_SERVICES,
                'body' => 'Armed escort available for merchant vessels.',
                'reward' => 'Negotiable',
                'location' => 'Dock 7',
                'expires_at' => '2026-08-01',
                'accent_color' => RoomNotice::ACCENT_GOLD,
                'status' => RoomNotice::STATUS_CLOSED,
            ])
            ->assertOk()
            ->assertJsonPath('notice.status', RoomNotice::STATUS_CLOSED)
            ->assertJsonPath('notice.accent_color', RoomNotice::ACCENT_GOLD);

        $this->assertDatabaseHas('room_notices', [
            'id' => $notice->id,
            'title' => 'Ship Escort Available',
            'reward' => 'Negotiable',
            'location' => 'Dock 7',
            'accent_color' => RoomNotice::ACCENT_GOLD,
            'status' => RoomNotice::STATUS_CLOSED,
        ]);
    }

    public function test_owner_and_moderator_can_edit_any_notice(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$moderatorUser, $moderatorCharacter] = $this->createUserWithCharacter('Moderator');
        [$authorUser, $authorCharacter] = $this->createUserWithCharacter('Author');
        $room = $this->createRoom($ownerUser, $ownerCharacter);
        $this->addModerator($room, $moderatorCharacter);

        $notice = RoomNotice::create([
            'room_id' => $room->id,
            'author_character_id' => $authorCharacter->id,
            'title' => 'Rumor Thread',
            'category' => RoomNotice::CATEGORY_RUMORS,
            'body' => 'Original rumor.',
            'status' => RoomNotice::STATUS_ACTIVE,
        ]);

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->patchJson(route('rooms.notice-board.update', [$room->slug, $notice]), [
                'title' => 'Rumor Thread',
                'category' => RoomNotice::CATEGORY_RUMORS,
                'body' => 'Owner-edited rumor.',
                'reward' => '',
                'location' => '',
                'expires_at' => '',
                'accent_color' => 'default',
                'status' => RoomNotice::STATUS_ACTIVE,
            ])
            ->assertOk();

        $this->actingAs($moderatorUser)
            ->withSession(['active_character_id' => $moderatorCharacter->id])
            ->patchJson(route('rooms.notice-board.update', [$room->slug, $notice]), [
                'title' => 'Rumor Thread',
                'category' => RoomNotice::CATEGORY_RUMORS,
                'body' => 'Moderator archived this notice.',
                'reward' => '',
                'location' => '',
                'expires_at' => '',
                'accent_color' => RoomNotice::ACCENT_PURPLE,
                'status' => RoomNotice::STATUS_ARCHIVED,
            ])
            ->assertOk();

        $this->assertDatabaseHas('room_notices', [
            'id' => $notice->id,
            'body' => 'Moderator archived this notice.',
            'accent_color' => RoomNotice::ACCENT_PURPLE,
            'status' => RoomNotice::STATUS_ARCHIVED,
        ]);
    }

    public function test_non_author_participant_cannot_edit_or_delete_notice(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$authorUser, $authorCharacter] = $this->createUserWithCharacter('Author');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $notice = RoomNotice::create([
            'room_id' => $room->id,
            'author_character_id' => $authorCharacter->id,
            'title' => 'Private Contract',
            'category' => RoomNotice::CATEGORY_JOBS,
            'body' => 'Do not touch.',
            'status' => RoomNotice::STATUS_ACTIVE,
        ]);

        $payload = [
            'title' => 'Changed',
            'category' => RoomNotice::CATEGORY_JOBS,
            'body' => 'Changed body.',
            'reward' => '',
            'location' => '',
            'expires_at' => '',
            'accent_color' => RoomNotice::ACCENT_GRAY,
            'status' => RoomNotice::STATUS_CLOSED,
        ];

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->patchJson(route('rooms.notice-board.update', [$room->slug, $notice]), $payload)
            ->assertForbidden();

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->deleteJson(route('rooms.notice-board.destroy', [$room->slug, $notice]))
            ->assertForbidden();
    }

    public function test_soft_delete_is_used_for_notice_removal(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $notice = RoomNotice::create([
            'room_id' => $room->id,
            'author_character_id' => $ownerCharacter->id,
            'title' => 'Temporary Event',
            'category' => RoomNotice::CATEGORY_EVENTS,
            'body' => 'Soon to be removed.',
            'status' => RoomNotice::STATUS_ACTIVE,
        ]);

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->deleteJson(route('rooms.notice-board.destroy', [$room->slug, $notice]))
            ->assertOk();

        $this->assertSoftDeleted('room_notices', [
            'id' => $notice->id,
        ]);
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

    private function createRoom(User $user, Character $ownerCharacter): Room
    {
        return Room::create([
            'name' => 'Room ' . Str::random(8),
            'slug' => 'room-' . Str::random(16),
            'user_id' => $user->id,
            'created_by' => $user->id,
            'type' => Room::TYPE_PUBLIC,
            'visibility' => Room::VISIBILITY_PUBLIC,
            'owner_character_id' => $ownerCharacter->id,
        ]);
    }

    private function addModerator(Room $room, Character $character): void
    {
        $room->roomCharacterRoles()->create([
            'character_id' => $character->id,
            'role' => 'moderator',
        ]);
    }
}
