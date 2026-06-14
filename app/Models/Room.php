<?php

namespace App\Models;

use App\Models\Character;
use App\Models\RoomAccessEntry;
use App\Models\RoomCharacterRole;
use App\Models\UserRoomState;
use App\Models\RoomNotice;
use App\Models\WorldBookEntry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Room extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const TYPE_PUBLIC = 'public';
    public const TYPE_DM = 'dm';

    public const VISIBILITY_PUBLIC = 'public';
    public const VISIBILITY_HIDDEN = 'hidden';

    public const PROFILE_MODE_STANDARD = 'standard';
    public const PROFILE_MODE_ADVANCED = 'advanced';

    // rooms table is the conversation model.
    protected $fillable = [
        'name',
        'slug',
        'description',
        'created_by',
        'user_id',
        'type',
        'dm_key',
        'owner_character_id',
        'visibility',
        'profile_banner_url',
        'profile_summary',
        'profile_joining_information',
        'profile_rules',
        'profile_mode',
        'profile_custom_html',
        'profile_custom_css',
        'profile_custom_js',
        'last_posted_at',
    ];

    protected function casts(): array
    {
        return [
            'last_posted_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isDm(): bool
    {
        return $this->type === self::TYPE_DM;
    }

    public function isPublicRoom(): bool
    {
        return $this->type === self::TYPE_PUBLIC;
    }

    public function isHidden(): bool
    {
        return ($this->visibility ?? self::VISIBILITY_PUBLIC) === self::VISIBILITY_HIDDEN;
    }

    public function profileMode(): string
    {
        $mode = (string) ($this->profile_mode ?? self::PROFILE_MODE_STANDARD);

        return in_array($mode, [self::PROFILE_MODE_STANDARD, self::PROFILE_MODE_ADVANCED], true)
            ? $mode
            : self::PROFILE_MODE_STANDARD;
    }

    public function usesAdvancedProfile(): bool
    {
        return $this->profileMode() === self::PROFILE_MODE_ADVANCED;
    }

    public function lastActivityAt(): Carbon
    {
        return ($this->last_posted_at ?? $this->created_at ?? now())->copy();
    }

    public static function normalizedDmPair(int $firstCharacterId, int $secondCharacterId): array
    {
        if ($firstCharacterId === $secondCharacterId) {
            throw new \InvalidArgumentException('A DM requires two different characters.');
        }

        $pair = [$firstCharacterId, $secondCharacterId];
        sort($pair, SORT_NUMERIC);

        return $pair;
    }

    public static function normalizedDmKey(int $firstCharacterId, int $secondCharacterId): string
    {
        return implode(':', self::normalizedDmPair($firstCharacterId, $secondCharacterId));
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ownerCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'owner_character_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    // Matches your migration: character_presences.room_id -> rooms.id
    public function characterPresences(): HasMany
    {
        return $this->hasMany(CharacterPresence::class);
    }

    public function roomCharacterRoles(): HasMany
    {
        return $this->hasMany(RoomCharacterRole::class);
    }

    public function roomAccessEntries(): HasMany
    {
        return $this->hasMany(RoomAccessEntry::class);
    }

    public function userRoomStates(): HasMany
    {
        return $this->hasMany(UserRoomState::class);
    }

    public function worldBookEntries(): HasMany
    {
        return $this->hasMany(WorldBookEntry::class);
    }

    public function roomNotices(): HasMany
    {
        return $this->hasMany(RoomNotice::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Attributes
    |--------------------------------------------------------------------------
    */

    // True "active" count based on presence table, not recent posters
    public function getActiveUsersAttribute(): int
    {
        $cutoff = now()->subMinutes(5);

        return $this->characterPresences()
            ->where('last_seen_at', '>=', $cutoff)
            ->count();
    }
}
