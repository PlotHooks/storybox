<?php

namespace App\Http\Controllers;

use App\Models\Character;
use Illuminate\Http\Request;

class CharacterController extends Controller
{
    public function index()
    {
        return auth()->user()->characters;
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
        ]);

        return auth()->user()->characters()->create([
            'name' => $request->name,
            'slug' => str()->slug($request->name) . '-' . uniqid(),
        ]);
    }

    public function switch(Character $character)
    {
        // ensure the logged-in user owns this character
        abort_if($character->user_id !== auth()->id(), 403);

        session(['active_character_id' => $character->id]);

        return response()->json(['active' => $character]);
    }
}
