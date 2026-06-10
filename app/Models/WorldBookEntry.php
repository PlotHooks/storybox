<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorldBookEntry extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_REJECTED = 'rejected';

    public const CATEGORY_LOCATION = 'location';
    public const CATEGORY_FACTION = 'faction';
    public const CATEGORY_NPC = 'npc';
    public const CATEGORY_LORE = 'lore';
    public const CATEGORY_TIMELINE_EVENT = 'timeline_event';
    public const CATEGORY_CUSTOM = 'custom';

    protected $fillable = [
        'room_id',
        'author_character_id',
        'reviewed_by_character_id',
        'status',
        'title',
        'category',
        'image_url',
        'body',
        'draft_title',
        'draft_category',
        'draft_image_url',
        'draft_body',
        'published_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public static function categories(): array
    {
        return [
            self::CATEGORY_LOCATION => 'Location',
            self::CATEGORY_FACTION => 'Faction',
            self::CATEGORY_NPC => 'NPC',
            self::CATEGORY_LORE => 'Lore',
            self::CATEGORY_TIMELINE_EVENT => 'Timeline Event',
            self::CATEGORY_CUSTOM => 'Custom',
        ];
    }

    public static function categoryLabel(?string $category): string
    {
        return self::categories()[$category] ?? 'Custom';
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function authorCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'author_character_id');
    }

    public function reviewedByCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'reviewed_by_character_id');
    }

    public function hasPublishedContent(): bool
    {
        return $this->published_at !== null
            && $this->title !== null
            && $this->category !== null
            && $this->body !== null;
    }

    public function hasPendingDraft(): bool
    {
        return $this->draft_title !== null
            && $this->draft_category !== null
            && $this->draft_body !== null;
    }
}
