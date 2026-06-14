<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Room;
use App\Models\RoomRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RulesToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_viewer_can_read_rules_but_not_manage_them(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $firstRule = $room->roomRules()->create([
            'title' => 'No Godmodding',
            'body' => 'Respect other players\' agency.',
            'sort_order' => 1,
        ]);
        $room->roomRules()->create([
            'title' => 'Stay In Setting',
            'body' => 'Characters should fit the room premise.',
            'sort_order' => 2,
        ]);

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->getJson(route('rooms.rules.index', $room->slug))
            ->assertOk()
            ->assertJsonPath('permissions.can_create', false)
            ->assertJsonPath('permissions.can_manage', false)
            ->assertJsonPath('rules.0.id', $firstRule->id)
            ->assertJsonPath('rules.0.title', 'No Godmodding')
            ->assertJsonPath('rules.0.viewer_can_manage', false)
            ->assertJsonPath('rules.0.can_move_up', false)
            ->assertJsonPath('rules.1.can_move_down', false);
    }

    public function test_owner_can_create_edit_and_delete_rules(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $createdResponse = $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->postJson(route('rooms.rules.store', $room->slug), [
                'title' => 'No Godmodding',
                'body' => 'Respect other players\' agency.',
            ])
            ->assertOk()
            ->assertJsonPath('rule.title', 'No Godmodding');

        $ruleId = $createdResponse->json('rule.id');
        $rule = RoomRule::findOrFail($ruleId);

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->patchJson(route('rooms.rules.update', [$room->slug, $rule]), [
                'title' => 'No Godmodding',
                'body' => 'Respect scene partners and their choices.',
            ])
            ->assertOk()
            ->assertJsonPath('rule.body', 'Respect scene partners and their choices.');

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->deleteJson(route('rooms.rules.destroy', [$room->slug, $rule]))
            ->assertOk();

        $this->assertSoftDeleted('room_rules', [
            'id' => $ruleId,
        ]);
    }

    public function test_moderator_can_create_edit_and_delete_rules(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$moderatorUser, $moderatorCharacter] = $this->createUserWithCharacter('Moderator');
        $room = $this->createRoom($ownerUser, $ownerCharacter);
        $this->addModerator($room, $moderatorCharacter);

        $rule = $room->roomRules()->create([
            'title' => 'Keep IC and OOC Separate',
            'body' => 'Do not blend player conflict into scenes.',
            'sort_order' => 1,
        ]);

        $this->actingAs($moderatorUser)
            ->withSession(['active_character_id' => $moderatorCharacter->id])
            ->postJson(route('rooms.rules.store', $room->slug), [
                'title' => 'Stay In Setting',
                'body' => 'Characters should fit the room premise.',
            ])
            ->assertOk();

        $this->actingAs($moderatorUser)
            ->withSession(['active_character_id' => $moderatorCharacter->id])
            ->patchJson(route('rooms.rules.update', [$room->slug, $rule]), [
                'title' => 'Keep IC and OOC Separate',
                'body' => 'Do not drag OOC disputes into IC scenes.',
            ])
            ->assertOk();

        $this->actingAs($moderatorUser)
            ->withSession(['active_character_id' => $moderatorCharacter->id])
            ->deleteJson(route('rooms.rules.destroy', [$room->slug, $rule]))
            ->assertOk();

        $this->assertSoftDeleted('room_rules', [
            'id' => $rule->id,
        ]);
    }

    public function test_regular_participant_is_read_only_for_rules(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $rule = $room->roomRules()->create([
            'title' => 'No Godmodding',
            'body' => 'Respect other players\' agency.',
            'sort_order' => 1,
        ]);

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->postJson(route('rooms.rules.store', $room->slug), [
                'title' => 'Unauthorized',
                'body' => 'Should not work.',
            ])
            ->assertForbidden();

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->patchJson(route('rooms.rules.update', [$room->slug, $rule]), [
                'title' => 'Changed',
                'body' => 'Changed body.',
            ])
            ->assertForbidden();

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->postJson(route('rooms.rules.move', [$room->slug, $rule]), [
                'direction' => 'down',
            ])
            ->assertForbidden();

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->deleteJson(route('rooms.rules.destroy', [$room->slug, $rule]))
            ->assertForbidden();
    }

    public function test_rules_can_move_up_and_down_within_the_rule_list(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $first = $room->roomRules()->create([
            'title' => 'First',
            'body' => 'First body',
            'sort_order' => 1,
        ]);
        $second = $room->roomRules()->create([
            'title' => 'Second',
            'body' => 'Second body',
            'sort_order' => 2,
        ]);
        $third = $room->roomRules()->create([
            'title' => 'Third',
            'body' => 'Third body',
            'sort_order' => 3,
        ]);

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->postJson(route('rooms.rules.move', [$room->slug, $third]), [
                'direction' => 'up',
            ])
            ->assertOk()
            ->assertJsonPath('rules.1.id', $third->id)
            ->assertJsonPath('rules.1.can_move_up', true)
            ->assertJsonPath('rules.1.can_move_down', true);

        $this->assertSame(
            [$first->id, $third->id, $second->id],
            $room->fresh()->roomRules()->pluck('id')->all()
        );

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->postJson(route('rooms.rules.move', [$room->slug, $first]), [
                'direction' => 'up',
            ])
            ->assertOk()
            ->assertJsonPath('rules.0.id', $first->id)
            ->assertJsonPath('rules.0.can_move_up', false);
    }

    public function test_room_profile_renders_rules_from_the_same_source(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $room->roomRules()->create([
            'title' => 'No Godmodding',
            'body' => 'Respect other players\' agency.',
            'sort_order' => 1,
        ]);
        $room->roomRules()->create([
            'title' => 'Stay In Setting',
            'body' => 'Characters should fit the room premise.',
            'sort_order' => 2,
        ]);

        $room->update([
            'profile_rules' => 'Legacy blob should not render.',
        ]);

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->get(route('rooms.profile.show', $room->slug))
            ->assertOk()
            ->assertSee('No Godmodding')
            ->assertSee('Respect other players&#039; agency.', false)
            ->assertSee('Stay In Setting')
            ->assertDontSee('Legacy blob should not render.');
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
            'name' => 'Rules Room',
            'slug' => 'rules-room-' . Str::random(16),
            'description' => 'A room for rules testing.',
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
            'role' => \App\Models\RoomCharacterRole::ROLE_MODERATOR,
        ]);
    }
}
