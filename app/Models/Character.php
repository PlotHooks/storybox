<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Character extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'avatar',
        'profile_html',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    /*
     |--------------------------------------------------------------------------
     | Relationships
     |--------------------------------------------------------------------------
     */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /*
     |--------------------------------------------------------------------------
     | Convenience helpers for styling
     |--------------------------------------------------------------------------
     | These prevent "undefined index" issues and give sane defaults.
     */

    public function style(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    public function hasFadeMessage(): bool
    {
        return (bool) ($this->settings['fade_message'] ?? false);
    }

    public function hasFadeName(): bool
    {
        return (bool) ($this->settings['fade_name'] ?? false);
    }

    public function textColors(): array
    {
        return array_values(array_filter([
            $this->settings['text_color_1'] ?? null,
            $this->settings['text_color_2'] ?? null,
            $this->settings['text_color_3'] ?? null,
            $this->settings['text_color_4'] ?? null,
        ]));
    }
}
