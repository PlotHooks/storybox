<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Room;
use App\Services\RoomAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CharacterController extends Controller
{
    private function avatarValidationRules(): array
    {
        return [
            'nullable',
            'string',
            'max:2048',
            'url',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value === null || $value === '') {
                    return;
                }

                $scheme = parse_url($value, PHP_URL_SCHEME);

                if (! in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
                    $fail('The avatar must be an http or https URL.');
                }
            },
        ];
    }

    private function normalizeAvatar(?string $avatar): ?string
    {
        $avatar = trim((string) $avatar);

        return $avatar === '' ? null : $avatar;
    }

    private function redirectTarget(Request $request): string
    {
        $target = trim((string) $request->input('return_to', ''));

        if ($target === '') {
            return route('characters.index');
        }

        if (str_starts_with($target, '/')) {
            return $target;
        }

        $appHost = parse_url(url('/'), PHP_URL_HOST);
        $targetHost = parse_url($target, PHP_URL_HOST);

        if ($appHost && $targetHost && strcasecmp($appHost, $targetHost) === 0) {
            $path = parse_url($target, PHP_URL_PATH) ?: '/';
            $query = parse_url($target, PHP_URL_QUERY);

            return $query ? $path.'?'.$query : $path;
        }

        return route('characters.index');
    }

    private function redirectToTarget(Request $request, string $status): RedirectResponse
    {
        return redirect()
            ->to($this->redirectTarget($request))
            ->with('status', $status);
    }

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
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'avatar' => $this->avatarValidationRules(),
        ]);

        auth()->user()->characters()->create([
            'name' => $validated['name'] ?? $character->name,
            'avatar' => $this->normalizeAvatar($validated['avatar'] ?? null),
            'slug' => str()->slug($validated['name']) . '-' . uniqid(),
        ]);

        return $this->redirectToTarget($request, 'Character created.');
    }

    public function switch(Request $request, Character $character)
    {
        abort_if($character->user_id !== auth()->id(), 403);

        session(['active_character_id' => $character->id]);

        return $this->redirectToTarget($request, 'Switched to ' . $character->name . '.');
    }

    public function show(Character $character)
    {
        abort_if($character->user_id !== auth()->id(), 403);

        return view('characters.show', compact('character'));
    }

    public function currentRoom(Character $character)
    {
        abort_if($character->user_id !== auth()->id(), 403);

        $room = Room::query()
            ->join('character_presences', 'rooms.id', '=', 'character_presences.room_id')
            ->where('character_presences.character_id', $character->id)
            ->select('rooms.*')
            ->first();

        $slug = null;

        if ($room && app(RoomAccessService::class)->canViewRoom(auth()->user(), $room, $character)) {
            $slug = $room->slug;
        }

        return response()->json(['room_slug' => $slug]);
    }

    public function updateStyle(Request $request, Character $character)
    {
        abort_if($character->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'avatar' => $this->avatarValidationRules(),
            'text_color_1' => ['required', 'regex:/^#?[0-9a-fA-F]{6}$/'],
            'text_color_2' => ['nullable', 'regex:/^#?[0-9a-fA-F]{6}$/'],
            'text_color_3' => ['nullable', 'regex:/^#?[0-9a-fA-F]{6}$/'],
            'text_color_4' => ['nullable', 'regex:/^#?[0-9a-fA-F]{6}$/'],
            'fade_message' => ['nullable'],
            'fade_name' => ['nullable'],
        ]);

        $norm = function ($v) {
            if ($v === null || $v === '') return null;
            $v = ltrim($v, '#');
            return '#' . strtoupper($v);
        };

        $existing = $character->settings ?? [];

        $updated = array_merge($existing, [
            'text_color_1' => $norm($request->text_color_1),
            'text_color_2' => $norm($request->text_color_2),
            'text_color_3' => $norm($request->text_color_3),
            'text_color_4' => $norm($request->text_color_4),
            'fade_message' => $request->boolean('fade_message'),
            'fade_name' => $request->boolean('fade_name'),
        ]);

        $updates = [
            'name' => $validated['name'] ?? $character->name,
            'settings' => $updated,
        ];

        if ($request->has('avatar')) {
            $updates['avatar'] = $this->normalizeAvatar($request->input('avatar'));
        }

        $character->update($updates);

        return $this->redirectToTarget($request, 'Updated ' . $character->name . '.');
    }

    public function destroy(Request $request, Character $character)
    {
        abort_if($character->user_id !== auth()->id(), 403);

        if ((int) session('active_character_id', 0) === (int) $character->id) {
            return redirect()
                ->to($this->redirectTarget($request))
                ->withErrors([
                    'character_delete' => 'Switch away from the active character before deleting it.',
                ]);
        }

        $name = $character->name;

        DB::transaction(function () use ($character) {
            $character->delete();
        });

        return $this->redirectToTarget($request, 'Deleted ' . $name . '.');
    }
}
