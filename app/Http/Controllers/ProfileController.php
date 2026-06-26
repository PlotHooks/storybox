<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $choice = array_key_exists('dm_notification_sound_choice', $validated)
            ? ($validated['dm_notification_sound_choice'] ?: User::DM_NOTIFICATION_SOUND_DEFAULT)
            : ($user->dm_notification_sound_choice ?: User::DM_NOTIFICATION_SOUND_DEFAULT);
        $soundEnabled = array_key_exists('dm_notification_sound_enabled', $validated)
            ? ($choice !== User::DM_NOTIFICATION_SOUND_OFF && $request->boolean('dm_notification_sound_enabled', true))
            : (bool) $user->dm_notification_sound_enabled;
        $customSoundUrl = array_key_exists('dm_notification_sound_url', $validated)
            ? $this->nullableTrim($validated['dm_notification_sound_url'] ?? null)
            : $user->dm_notification_sound_url;

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'dm_notification_sound_enabled' => $soundEnabled,
            'dm_notification_sound_choice' => $choice,
            'dm_notification_sound_url' => $customSoundUrl,
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    private function nullableTrim(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
