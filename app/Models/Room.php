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
