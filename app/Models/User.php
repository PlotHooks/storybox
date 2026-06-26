<?php

namespace App\Models;

use App\Models\UserRoomState;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Gate;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory;
    use Notifiable;

    public const DM_NOTIFICATION_SOUND_OFF = 'off';
    public const DM_NOTIFICATION_SOUND_DEFAULT = 'default';
    public const DM_NOTIFICATION_SOUND_SOFT_CHIME = 'soft_chime';
    public const DM_NOTIFICATION_SOUND_BELL = 'bell';
    public const DM_NOTIFICATION_SOUND_CLICK_TICK = 'click_tick';
    public const DM_NOTIFICATION_SOUND_CUSTOM = 'custom';

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'dm_notification_sound_enabled',
        'dm_notification_sound_choice',
        'dm_notification_sound_url',
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
            'dm_notification_sound_enabled' => 'boolean',
        ];
    }

    public static function dmNotificationSoundChoices(): array
    {
        return [
            self::DM_NOTIFICATION_SOUND_OFF,
            self::DM_NOTIFICATION_SOUND_DEFAULT,
            self::DM_NOTIFICATION_SOUND_SOFT_CHIME,
            self::DM_NOTIFICATION_SOUND_BELL,
            self::DM_NOTIFICATION_SOUND_CLICK_TICK,
            self::DM_NOTIFICATION_SOUND_CUSTOM,
        ];
    }

    public static function dmNotificationSoundOptions(): array
    {
        return [
            self::DM_NOTIFICATION_SOUND_OFF => 'Off',
            self::DM_NOTIFICATION_SOUND_DEFAULT => 'Default',
            self::DM_NOTIFICATION_SOUND_SOFT_CHIME => 'Soft Chime',
            self::DM_NOTIFICATION_SOUND_BELL => 'Bell',
            self::DM_NOTIFICATION_SOUND_CLICK_TICK => 'Click / Tick',
            self::DM_NOTIFICATION_SOUND_CUSTOM => 'Custom URL',
        ];
    }

    public function dmNotificationSoundPreferences(): array
    {
        $choice = in_array($this->dm_notification_sound_choice, self::dmNotificationSoundChoices(), true)
            ? $this->dm_notification_sound_choice
            : self::DM_NOTIFICATION_SOUND_DEFAULT;

        return [
            'enabled' => (bool) $this->dm_notification_sound_enabled && $choice !== self::DM_NOTIFICATION_SOUND_OFF,
            'choice' => $choice,
            'url' => $this->dm_notification_sound_url,
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

    public function roomStates()
    {
        return $this->hasMany(UserRoomState::class);
    }
}
