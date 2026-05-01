<?php

namespace App\Models;

use App\Models\CharacterBlock;
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

    public function blockedCharacters()
    {
        return $this->belongsToMany(
            Character::class,
            'character_blocks',
            'blocker_character_id',
            'blocked_character_id'
        )->withTimestamps();
    }

    public function blockedByCharacters()
    {
        return $this->belongsToMany(
            Character::class,
            'character_blocks',
            'blocked_character_id',
            'blocker_character_id'
        )->withTimestamps();
    }

    public function hasBlocked(Character $character): bool
    {
        return CharacterBlock::query()
            ->where('blocker_character_id', $this->id)
            ->where('blocked_character_id', $character->id)
            ->exists();
    }

    public function isBlockedBy(Character $character): bool
    {
        return CharacterBlock::query()
            ->where('blocker_character_id', $character->id)
            ->where('blocked_character_id', $this->id)
            ->exists();
    }

    public function hasBlockRelationshipWith(Character $character): bool
    {
        return CharacterBlock::existsBetween($this->id, $character->id);
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
