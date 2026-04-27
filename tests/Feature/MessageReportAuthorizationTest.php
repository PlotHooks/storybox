<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Message;
use App\Models\MessageReport;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessageReportAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dm_non_participant_cannot_report_dm_message(): void
    {
        [$room, $message] = $this->createDmWithMessage();
        $outsider = User::factory()->create();

        $response = $this->actingAs($outsider)->postJson(route('messages.report', $message), [
            'reason' => 'Needs moderator review.',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseCount('message_reports', 0);
    }

    public function test_dm_participant_can_report_dm_message(): void
    {
        [$room, $message, $sender, $reporter] = $this->createDmWithMessage();

        $response = $this->actingAs($reporter)->postJson(route('messages.report', $message), [
            'reason' => 'Needs moderator review.',
        ]);

        $response
            ->assertCreated()
            ->assertJson([
                'ok' => true,
            ])
            ->assertJsonPath('report_id', MessageReport::first()->id);

        $this->assertDatabaseHas('message_reports', [
            'message_id' => $message->id,
            'reporter_user_id' => $reporter->id,
            'status' => 'pending',
        ]);
    }

    public function test_same_user_reporting_same_message_twice_creates_one_report(): void
    {
        [$room, $message, $sender, $reporter] = $this->createDmWithMessage();

        $firstResponse = $this->actingAs($reporter)->postJson(route('messages.report', $message), [
            'reason' => 'First report.',
        ]);
        $secondResponse = $this->actingAs($reporter)->postJson(route('messages.report', $message), [
            'reason' => 'Second report.',
        ]);

        $firstResponse->assertCreated()->assertJson(['ok' => true]);
        $secondResponse->assertCreated()->assertJson([
            'ok' => true,
            'report_id' => $firstResponse->json('report_id'),
        ]);

        $this->assertDatabaseCount('message_reports', 1);
        $this->assertDatabaseHas('message_reports', [
            'message_id' => $message->id,
            'reporter_user_id' => $reporter->id,
            'reason' => 'First report.',
        ]);
    }

    public function test_different_users_can_report_same_message(): void
    {
        [$room, $message, $sender, $firstReporter] = $this->createDmWithMessage();
        $secondReporter = User::factory()->create();
        $secondReporterCharacter = $this->createCharacter($secondReporter);

        DB::table('dm_participants')->insert([
            'room_id' => $room->id,
            'user_id' => $secondReporter->id,
            'character_id' => $secondReporterCharacter->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($firstReporter)->postJson(route('messages.report', $message), [
            'reason' => 'First reporter.',
        ])->assertCreated();

        $this->actingAs($secondReporter)->postJson(route('messages.report', $message), [
            'reason' => 'Second reporter.',
        ])->assertCreated();

        $this->assertDatabaseCount('message_reports', 2);
        $this->assertDatabaseHas('message_reports', [
            'message_id' => $message->id,
            'reporter_user_id' => $firstReporter->id,
        ]);
        $this->assertDatabaseHas('message_reports', [
            'message_id' => $message->id,
            'reporter_user_id' => $secondReporter->id,
        ]);
    }

    private function createDmWithMessage(): array
    {
        $sender = User::factory()->create();
        $reporter = User::factory()->create();
        $senderCharacter = $this->createCharacter($sender);
        $reporterCharacter = $this->createCharacter($reporter);

        $room = Room::create([
            'name' => 'DM',
            'slug' => 'dm-' . Str::random(20),
            'user_id' => $sender->id,
            'created_by' => $sender->id,
            'type' => 'dm',
        ]);

        DB::table('dm_participants')->insert([
            [
                'room_id' => $room->id,
                'user_id' => $sender->id,
                'character_id' => $senderCharacter->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'room_id' => $room->id,
                'user_id' => $reporter->id,
                'character_id' => $reporterCharacter->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $message = Message::create([
            'room_id' => $room->id,
            'user_id' => $sender->id,
            'character_id' => $senderCharacter->id,
            'body' => 'Reported DM message.',
        ]);

        return [$room, $message, $sender, $reporter];
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
