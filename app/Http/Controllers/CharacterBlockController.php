<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\CharacterBlock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CharacterBlockController extends Controller
{
    public function store(Request $request, Character $blockerCharacter, Character $blockedCharacter)
    {
        $this->assertOwnsBlocker($blockerCharacter);

        abort_if($blockerCharacter->is($blockedCharacter), 422, 'A character cannot block itself.');

        $block = CharacterBlock::firstOrCreate([
            'blocker_character_id' => $blockerCharacter->id,
            'blocked_character_id' => $blockedCharacter->id,
        ]);

        return response()->json([
            'ok' => true,
            'block_id' => $block->id,
        ]);
    }

    public function destroy(Request $request, Character $blockerCharacter, Character $blockedCharacter)
    {
        $this->assertOwnsBlocker($blockerCharacter);

        CharacterBlock::query()
            ->where('blocker_character_id', $blockerCharacter->id)
            ->where('blocked_character_id', $blockedCharacter->id)
            ->delete();

        return response()->json(['ok' => true]);
    }

    private function assertOwnsBlocker(Character $blockerCharacter): void
    {
        abort_unless((int) $blockerCharacter->user_id === (int) Auth::id(), 403);
    }
}
