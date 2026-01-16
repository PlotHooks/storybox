<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'room_id',
        'user_id',
        'character_id',
        'body',
        'deleted_by',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function room()
    {
        return $this->belongsTo(Room::class);
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
    | Helpers (optional but useful)
    |--------------------------------------------------------------------------
    */

    public function isDeleted(): bool
    {
        return $this->trashed();
    }

    public function canBeEditedBy(User $user): bool
    {
        return $this->user_id === $user->id || ($user->is_admin ?? false);
    }
}
