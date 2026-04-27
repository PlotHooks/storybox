<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Support\Facades\Gate;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_banned' => 'boolean',
            'banned_until' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return Gate::allows('accessFilament', $this);
    }

    public function characters()
    {
        return $this->hasMany(Character::class);
    }

    public function rooms()
    {
        return $this->hasMany(Room::class, 'created_by');
    }

    public function memberRooms()
    {
        return $this->belongsToMany(Room::class, 'room_user_presence', 'user_id', 'room_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
