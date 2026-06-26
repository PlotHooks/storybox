<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'dm_notification_sound_enabled' => ['nullable', 'boolean'],
            'dm_notification_sound_choice' => [
                'nullable',
                'string',
                Rule::in(User::dmNotificationSoundChoices()),
            ],
            'dm_notification_sound_url' => [
                'nullable',
                'string',
                'max:2048',
                'url:http,https',
                Rule::requiredIf(fn () => $this->input('dm_notification_sound_choice') === User::DM_NOTIFICATION_SOUND_CUSTOM),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $path = (string) parse_url((string) $value, PHP_URL_PATH);
                    $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
                    $allowedExtensions = ['mp3', 'ogg', 'wav', 'm4a', 'aac', 'webm'];

                    if (!in_array($extension, $allowedExtensions, true)) {
                        $fail('The custom DM notification sound URL must point to a supported audio file.');
                    }
                },
            ],
        ];
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }
}
