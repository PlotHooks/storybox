<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const TYPE_NORMAL = 'normal';
    public const TYPE_EMOTE = 'emote';

    protected $fillable = [
        'room_id',
        'user_id',
        'character_id',
        'type',
        'body',
        'deleted_by',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function room()
    {
        return $this->belongsTo(Room::class)->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function character()
    {
        return $this->belongsTo(Character::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isDeleted(): bool
    {
        return $this->trashed();
    }

    public function isEmote(): bool
    {
        return $this->type === self::TYPE_EMOTE;
    }

    public function canBeEditedBy(User $user): bool
    {
        return $this->user_id === $user->id || (bool) ($user->is_admin ?? false);
    }

    public function reports()
    {
        return $this->hasMany(MessageReport::class);
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
