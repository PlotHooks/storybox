<?php

namespace App\Models;

use App\Models\CharacterBlock;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Character extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'avatar',
        'profile_html',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    protected static function booted(): void
    {
        static::created(function (self $character): void {
            $character->profile()->create([
                'template_type' => CharacterProfile::TEMPLATE_STORYBOX,
                'avatar_url' => $character->externalAvatarUrl(),
            ]);
        });
    }

    /*
     |--------------------------------------------------------------------------
     | Relationships
     |--------------------------------------------------------------------------
     */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(CharacterProfile::class);
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

    public function ensureProfile(): CharacterProfile
    {
        return $this->profile()->firstOrCreate(
            [],
            [
                'template_type' => CharacterProfile::TEMPLATE_STORYBOX,
                'avatar_url' => $this->externalAvatarUrl(),
            ]
        );
    }

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

    public function externalAvatarUrl(): ?string
    {
        if (! is_string($this->avatar) || trim($this->avatar) === '') {
            return null;
        }

        $avatar = trim($this->avatar);
        $scheme = parse_url($avatar, PHP_URL_SCHEME);

        if (! in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
            return null;
        }

        return $avatar;
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

    public function getPublicHandleAttribute(): string
    {
        return self::formatPublicHandle($this->name, (int) $this->id);
    }

    public static function formatPublicHandle(string $name, int $characterId): string
    {
        return sprintf('%s#%s', $name, self::publicShortId($characterId));
    }

    public static function publicShortId(int $characterId): string
    {
        $normalized = (($characterId * 2654435761) % 0xFFFFFFFF + 0xFFFFFFFF) % 0xFFFFFFFF;

        return strtoupper(str_pad(substr(dechex($normalized), 0, 4), 4, '0'));
    }

    public static function resolvePublicHandle(string $handle): ?self
    {
        if (! preg_match('/^(.+)#([A-F0-9]{4})$/', trim($handle), $matches)) {
            return null;
        }

        $name = trim($matches[1]);
        $shortId = strtoupper($matches[2]);

        if ($name === '') {
            return null;
        }

        $characters = self::query()
            ->where('name', $name)
            ->get()
            ->filter(fn (self $character) => self::publicShortId((int) $character->id) === $shortId)
            ->values();

        return $characters->count() === 1 ? $characters->first() : null;
    }
}
