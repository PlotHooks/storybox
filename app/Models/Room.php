<?php

namespace App\Models;

use App\Models\Character;
use App\Models\RoomAccessEntry;
use App\Models\RoomCharacterRole;
use App\Models\UserRoomState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const TYPE_PUBLIC = 'public';
    public const TYPE_DM = 'dm';

    public const VISIBILITY_PUBLIC = 'public';
    public const VISIBILITY_HIDDEN = 'hidden';

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
    ];

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
