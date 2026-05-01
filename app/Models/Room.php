<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'created_by',
        'user_id',
        'type',
        'dm_key',
    ];

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isDm(): bool
    {
        return $this->type === 'dm';
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

    public function owner()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    // Matches your migration: character_presences.room_id -> rooms.id
    public function characterPresences()
    {
        return $this->hasMany(CharacterPresence::class);
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
