<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use App\Services\RoomHistoryExportFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoomHistoryExportFormatterTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_history_export_formatter_outputs_transcript_storybox_tsv_and_csv(): void
    {
        $user = User::factory()->create();
        $character = Character::create([
            'user_id' => $user->id,
            'name' => 'Archivist',
            'slug' => 'character-' . Str::random(16),
        ]);
        $room = Room::create([
            'name' => 'Export Room',
            'slug' => 'export-room-' . Str::random(16),
            'user_id' => $user->id,
            'created_by' => $user->id,
            'type' => Room::TYPE_PUBLIC,
            'visibility' => Room::VISIBILITY_PUBLIC,
            'owner_character_id' => $character->id,
        ]);

        $normal = Message::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'character_id' => $character->id,
            'body' => 'Plain room line',
        ]);
        $normal->forceFill([
            'created_at' => now()->startOfDay()->addHour(),
            'updated_at' => now()->startOfDay()->addHours(2),
        ])->save();

        $emote = Message::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'character_id' => $character->id,
            'type' => Message::TYPE_EMOTE,
            'body' => 'glances toward the archive shelves.',
        ]);
        $emote->forceFill([
            'created_at' => now()->startOfDay()->addHours(3),
            'updated_at' => now()->startOfDay()->addHours(3),
        ])->save();

        $dice = Message::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'character_id' => $character->id,
            'type' => Message::TYPE_DICE,
            'body' => 'Archivist rolled 2d6: [4] [5] = 9',
            'structured_data' => [
                'expression' => '2d6',
                'rolls' => [4, 5],
                'modifier' => 0,
                'total' => 9,
            ],
        ]);
        $dice->forceFill([
            'created_at' => now()->startOfDay()->addHours(4),
            'updated_at' => now()->startOfDay()->addHours(4),
        ])->save();

        $deleted = Message::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'character_id' => $character->id,
            'body' => 'This line was removed.',
        ]);
        $deleted->forceFill([
            'created_at' => now()->startOfDay()->addHours(5),
            'updated_at' => now()->startOfDay()->addHours(5),
            'deleted_at' => now()->startOfDay()->addHours(6),
        ])->save();

        $messages = Message::withTrashed()->with('character')->whereKey([
            $normal->id,
            $emote->id,
            $dice->id,
            $deleted->id,
        ])->orderBy('id')->get();

        $formatter = app(RoomHistoryExportFormatter::class);
        $rows = $formatter->rowsFromMessages($messages);

        $this->assertSame('[deleted]', $rows[3]['body']);
        $this->assertSame($room->id, $rows[0]['dm_thread_id']);
        $this->assertSame('Archivist', $rows[0]['sender_character_name']);
        $this->assertSame($character->id, $rows[0]['sender_character_id']);
        $this->assertTrue($rows[0]['edited']);
        $this->assertSame('/me glances toward the archive shelves.', $rows[1]['storybox_line']);
        $this->assertSame('/roll 2d6', $rows[2]['storybox_line']);
        $this->assertStringContainsString('Archivist: Plain room line', $formatter->formatTranscript($rows));
        $this->assertStringContainsString('Archivist glances toward the archive shelves.', $formatter->formatTranscript($rows));
        $this->assertStringContainsString('Archivist rolled 2d6: [4] [5] = 9', $formatter->formatTranscript($rows));
        $this->assertStringContainsString("timestamp\tsender_character_name\tsender_character_id\tdm_thread_id", $formatter->formatTsv($rows));
        $this->assertStringContainsString('dm_thread_id,message_type,body,roll_expression,roll_result,edited,deleted', $formatter->formatCsv($rows));
        $this->assertStringContainsString(',2d6,', $formatter->formatCsv($rows));
    }
}
