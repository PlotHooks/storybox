<?php

namespace App\Http\Controllers;

use App\Models\Character;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CharacterController extends Controller
{
    public function index()
    {
        $characters = auth()->user()
            ->characters()
            ->orderBy('name')
            ->get();

        $activeId = session('active_character_id');

        return view('characters.index', compact('characters', 'activeId'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
        ]);

        auth()->user()->characters()->create([
            'name' => $request->name,
            'slug' => str()->slug($request->name) . '-' . uniqid(),
        ]);

        return redirect()
            ->route('characters.index')
            ->with('status', 'Character created.');
    }

    public function switch(Character $character)
    {
        abort_if($character->user_id !== auth()->id(), 403);

        session(['active_character_id' => $character->id]);

        return redirect()
            ->route('characters.index')
            ->with('status', 'Switched to ' . $character->name . '.');
    }

    public function show(Character $character)
    {
        return view('characters.show', compact('character'));
    }

    public function currentRoom(Character $character)
{
    abort_if($character->user_id !== auth()->id(), 403);

    $slug = DB::table('character_presences')
        ->join('rooms', 'rooms.id', '=', 'character_presences.room_id')
        ->where('character_presences.character_id', $character->id)
        ->value('rooms.slug');

    return response()->json(['room_slug' => $slug]);
}

public function updateStyle(Request $request, Character $character)
{
    abort_if($character->user_id !== auth()->id(), 403);

    $request->validate([
        'text_color_1' => ['required','regex:/^#?[0-9a-fA-F]{6}$/'],
        'text_color_2' => ['nullable','regex:/^#?[0-9a-fA-F]{6}$/'],
        'text_color_3' => ['nullable','regex:/^#?[0-9a-fA-F]{6}$/'],
        'text_color_4' => ['nullable','regex:/^#?[0-9a-fA-F]{6}$/'],
        'fade_message' => ['nullable'],
        'fade_name'    => ['nullable'],
    ]);

    $norm = function ($v) {
        if ($v === null || $v === '') return null;
        $v = ltrim($v, '#');
        return '#' . strtoupper($v);
    };

    $character->update([
        'text_color_1' => $norm($request->text_color_1),
        'text_color_2' => $norm($request->text_color_2),
        'text_color_3' => $norm($request->text_color_3),
        'text_color_4' => $norm($request->text_color_4),
        'fade_message' => $request->boolean('fade_message'),
        'fade_name'    => $request->boolean('fade_name'),
    ]);

    return redirect()
        ->route('characters.index')
        ->with('status', 'Style updated for ' . $character->name . '.');
}

}
