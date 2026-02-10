<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CharacterPresence extends Model
{
    use HasFactory;

    protected $table = 'character_presences';

    protected $fillable = [
        'character_id',
        'room_id',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function character()
    {
        return $this->belongsTo(Character::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
