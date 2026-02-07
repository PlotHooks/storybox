<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\RoomPresence;
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
        'type', // IMPORTANT
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

    public function presences()
    {
        return $this->hasMany(RoomPresence::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Attributes
    |--------------------------------------------------------------------------
    */

    public function getActiveUsersAttribute(): int
    {
        $cutoff = now()->subMinutes(5);

        return $this->messages()
            ->where('created_at', '>=', $cutoff)
            ->distinct('user_id')
            ->count('user_id');
    }
}
