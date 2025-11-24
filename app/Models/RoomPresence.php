<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class RoomPresence extends Model
{
    protected $table = 'room_presences';
    public $timestamps = false;

    protected $fillable = [
        'room_id',
        'user_id',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    // Relation: each presence belongs to a room
    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    // Relation: each presence belongs to a user
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
