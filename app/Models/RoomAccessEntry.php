<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomAccessEntry extends Model
{
    use HasFactory;

    public const TYPE_WHITELIST = 'whitelist';
    public const TYPE_BLACKLIST = 'blacklist';

    public const SCOPE_CHARACTER = 'character';
    public const SCOPE_ACCOUNT = 'account';

    protected $fillable = [
        'room_id',
        'character_id',
        'user_id',
        'type',
        'scope',
        'created_by_character_id',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdByCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'created_by_character_id');
    }
}
