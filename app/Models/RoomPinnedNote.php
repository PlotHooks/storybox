<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoomPinnedNote extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    public const CATEGORY_ANNOUNCEMENTS = 'announcements';
    public const CATEGORY_CURRENT_PLOT = 'current_plot';
    public const CATEGORY_SESSION_RECAPS = 'session_recaps';
    public const CATEGORY_EVENTS = 'events';
    public const CATEGORY_OTHER = 'other';

    public const ACCENT_RED = 'red';
    public const ACCENT_ORANGE = 'orange';
    public const ACCENT_GOLD = 'gold';
    public const ACCENT_GREEN = 'green';
    public const ACCENT_BLUE = 'blue';
    public const ACCENT_PURPLE = 'purple';
    public const ACCENT_PINK = 'pink';
    public const ACCENT_GRAY = 'gray';

    protected $fillable = [
        'room_id',
        'author_character_id',
        'title',
        'category',
        'body',
        'expires_at',
        'accent_color',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public static function categoryMeta(): array
    {
        return [
            self::CATEGORY_ANNOUNCEMENTS => ['label' => 'Announcements', 'icon' => '📢'],
            self::CATEGORY_CURRENT_PLOT => ['label' => 'Current Plot', 'icon' => '🧭'],
            self::CATEGORY_SESSION_RECAPS => ['label' => 'Session Recaps', 'icon' => '📝'],
            self::CATEGORY_EVENTS => ['label' => 'Events', 'icon' => '📅'],
            self::CATEGORY_OTHER => ['label' => 'Other', 'icon' => '📌'],
        ];
    }

    public static function statusMeta(): array
    {
        return [
            self::STATUS_ACTIVE => ['label' => 'Active'],
            self::STATUS_ARCHIVED => ['label' => 'Archived'],
        ];
    }

    public static function accentColorMeta(): array
    {
        return [
            self::ACCENT_RED => ['label' => 'Red'],
            self::ACCENT_ORANGE => ['label' => 'Orange'],
            self::ACCENT_GOLD => ['label' => 'Gold'],
            self::ACCENT_GREEN => ['label' => 'Green'],
            self::ACCENT_BLUE => ['label' => 'Blue'],
            self::ACCENT_PURPLE => ['label' => 'Purple'],
            self::ACCENT_PINK => ['label' => 'Pink'],
            self::ACCENT_GRAY => ['label' => 'Gray'],
        ];
    }

    public static function categoryLabel(?string $category): string
    {
        return self::categoryMeta()[$category]['label'] ?? 'Other';
    }

    public static function categoryIcon(?string $category): string
    {
        return self::categoryMeta()[$category]['icon'] ?? '📌';
    }

    public static function statusLabel(?string $status): string
    {
        return self::statusMeta()[$status]['label'] ?? 'Active';
    }

    public static function accentColorLabel(?string $accentColor): ?string
    {
        return $accentColor === null ? null : (self::accentColorMeta()[$accentColor]['label'] ?? null);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function authorCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'author_character_id');
    }
}
