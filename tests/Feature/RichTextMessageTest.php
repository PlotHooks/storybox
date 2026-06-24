<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use Tests\TestCase;

class RichTextMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('message_edits')) {
            Schema::create('message_edits', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('message_id');
                $table->unsignedBigInteger('editor_user_id');
                $table->text('old_body');
                $table->text('new_body');
                $table->timestamps();
            });
        }
    }

    public function test_room_message_renders_supported_rich_text_tags(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Victor');
        $room = $this->createPublicRoom($user);

        Message::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'character_id' => $character->id,
            'type' => Message::TYPE_NORMAL,
            'body' => '[b]Bold[/b] [i]Italic[/i] [u]Under[/u] [s]Strike[/s] [small]Small[/small] [large]Large[/large]',
        ]);

        $content = $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<strong>Bold</strong>', $content);
        $this->assertStringContainsString('<em>Italic</em>', $content);
        $this->assertStringContainsString('<span class="msg-rich-underline">Under</span>', $content);
        $this->assertStringContainsString('<span class="msg-rich-strike">Strike</span>', $content);
        $this->assertStringContainsString('<span class="msg-rich-small">Small</span>', $content);
        $this->assertStringContainsString('<span class="msg-rich-large">Large</span>', $content);
    }

    public function test_dm_message_payload_contains_rendered_rich_text_html(): void
    {
        [$firstUser, $firstCharacter] = $this->createUserWithCharacter('Leaf');
        [$secondUser, $secondCharacter] = $this->createUserWithCharacter('Mina');
        $room = $this->createDmRoom($firstUser, $firstCharacter, $secondUser, $secondCharacter);

        Message::create([
            'room_id' => $room->id,
            'user_id' => $secondUser->id,
            'character_id' => $secondCharacter->id,
            'type' => Message::TYPE_NORMAL,
            'body' => '[b]Hello[/b] there',
        ]);

        $this->actingAs($firstUser)
            ->getJson(route('dms.messages.index', $room->slug))
            ->assertOk()
            ->assertJsonPath('messages.0.rendered_body_html', '<strong>Hello</strong> there');
    }

    public function test_rich_text_renders_inside_emote_messages(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Leaf');
        $room = $this->createPublicRoom($user);

        Message::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'character_id' => $character->id,
            'type' => Message::TYPE_EMOTE,
            'body' => 'leaned in and said, [b]"No."[/b]',
        ]);

        $content = $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk()
            ->getContent();

        $normalized = preg_replace('/\s+/', ' ', $content);

        $this->assertStringContainsString('>Leaf</span>&nbsp;<span class="msg-body', $normalized);
        $this->assertStringContainsString('leaned in and said, <strong>&quot;No.&quot;</strong>', $normalized);
    }

    public function test_unknown_tags_are_displayed_as_plain_text(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Victor');
        $room = $this->createPublicRoom($user);

        Message::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'character_id' => $character->id,
            'body' => '[foo]mystery[/foo]',
        ]);

        $content = $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('[foo]mystery[/foo]', $content);
        $this->assertStringNotContainsString('<foo>', $content);
    }

    public function test_raw_html_is_escaped(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Victor');
        $room = $this->createPublicRoom($user);

        Message::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'character_id' => $character->id,
            'body' => '<b>unsafe</b>',
        ]);

        $content = $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('&lt;b&gt;unsafe&lt;/b&gt;', $content);
        $this->assertStringNotContainsString('<b>unsafe</b>', $content);
    }

    public function test_script_and_event_handler_attempts_are_escaped(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Victor');
        $room = $this->createPublicRoom($user);

        Message::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'character_id' => $character->id,
            'body' => '<script>alert(1)</script><span onclick="evil()">x</span>',
        ]);

        $content = $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;&lt;span onclick=&quot;evil()&quot;&gt;x&lt;/span&gt;', $content);
        $this->assertStringNotContainsString('<span onclick="evil()">x</span>', $content);
    }

    public function test_editing_rich_text_message_updates_rendered_formatting(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Victor');
        $room = $this->createPublicRoom($user);
        $message = Message::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'character_id' => $character->id,
            'body' => 'plain text',
        ]);

        $this->actingAs($user)
            ->patchJson("/messages/{$message->id}", [
                'body' => '[b]Edited[/b] text',
            ])
            ->assertOk()
            ->assertJsonPath('message.body', '[b]Edited[/b] text')
            ->assertJsonPath('message.rendered_body_html', '<strong>Edited</strong> text');
    }

    public function test_plain_messages_still_render_unchanged(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Victor');
        $room = $this->createPublicRoom($user);

        Message::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'character_id' => $character->id,
            'body' => 'Hello there.',
        ]);

        $content = $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('>Hello there.</span>', $content);
        $this->assertStringNotContainsString('<strong>Hello there.</strong>', $content);
    }

    private function createUserWithCharacter(string $characterName): array
    {
        $user = User::factory()->create([
            'name' => 'user_' . Str::random(8),
        ]);

        return [$user, $this->createCharacter($user, $characterName)];
    }

    private function createCharacter(User $user, string $name): Character
    {
        return Character::create([
            'user_id' => $user->id,
            'name' => $name,
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
            'type' => Room::TYPE_PUBLIC,
            'visibility' => Room::VISIBILITY_PUBLIC,
        ]);
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

}
