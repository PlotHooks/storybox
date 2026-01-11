<?php

namespace App\Http\Controllers;

use App\Models\Character;
use Illuminate\Http\Request;

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
}
