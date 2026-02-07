<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function characters()
    {
        return $this->hasMany(Character::class);
    }

    // Rooms this user created (owner/creator)
    public function rooms()
    {
        return $this->hasMany(Room::class, 'created_by');
    }

    // Rooms this user is a member of (DM membership lives here)
    public function memberRooms()
    {
        return $this->belongsToMany(Room::class, 'room_user_presence', 'user_id', 'room_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
