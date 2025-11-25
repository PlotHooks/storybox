<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\RoomPresence;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'created_by',
        'user_id',
    ];

    // relationships
    public function owner()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Computed attribute: number of "active" users in the last 5 minutes,
     * based on distinct user_ids that have posted in this room.
     */
    public function getActiveUsersAttribute(): int
    {
        $cutoff = now()->subMinutes(5);

        return $this->messages()
            ->where('created_at', '>=', $cutoff)
            ->distinct('user_id')
            ->count('user_id');
    }

     public function presences()
    {
        return $this->hasMany(RoomPresence::class);
    }

}
