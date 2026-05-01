<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CharacterBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'blocker_character_id',
        'blocked_character_id',
    ];

    public function blockerCharacter()
    {
        return $this->belongsTo(Character::class, 'blocker_character_id');
    }

    public function blockedCharacter()
    {
        return $this->belongsTo(Character::class, 'blocked_character_id');
    }

    public static function existsBetween(int $firstCharacterId, int $secondCharacterId): bool
    {
        return static::query()
            ->where(function ($query) use ($firstCharacterId, $secondCharacterId) {
                $query->where('blocker_character_id', $firstCharacterId)
                    ->where('blocked_character_id', $secondCharacterId);
            })
            ->orWhere(function ($query) use ($firstCharacterId, $secondCharacterId) {
                $query->where('blocker_character_id', $secondCharacterId)
                    ->where('blocked_character_id', $firstCharacterId);
            })
            ->exists();
    }
}
