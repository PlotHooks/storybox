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
    public const CATEGORY_CHARACTER = 'character';
    public const CATEGORY_LORE = 'lore';
    public const CATEGORY_TIMELINE_EVENT = 'timeline_event';
    public const CATEGORY_MAP = 'map';
    public const CATEGORY_CUSTOM = 'custom';

    protected $fillable = [
        'room_id',
        'author_character_id',
        'reviewed_by_character_id',
        'linked_character_id',
        'draft_linked_character_id',
        'status',
        'sort_order',
        'title',
        'category',
        'image_url',
        'body',
        'tags',
        'draft_title',
        'draft_category',
        'draft_image_url',
        'draft_body',
        'draft_tags',
        'published_at',
        'reviewed_at',
        'rejection_note',
        'rejected_at',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'draft_tags' => 'array',
            'sort_order' => 'integer',
            'published_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public static function categoryMeta(): array
    {
        return [
            self::CATEGORY_LOCATION => ['label' => 'Location', 'icon' => '📍'],
            self::CATEGORY_FACTION => ['label' => 'Faction', 'icon' => '⚔'],
            self::CATEGORY_NPC => ['label' => 'NPC', 'icon' => '👤'],
            self::CATEGORY_CHARACTER => ['label' => 'Character', 'icon' => '🪪'],
            self::CATEGORY_LORE => ['label' => 'Lore', 'icon' => '📜'],
            self::CATEGORY_TIMELINE_EVENT => ['label' => 'Timeline Event', 'icon' => '🕒'],
            self::CATEGORY_MAP => ['label' => 'Map', 'icon' => '🗺'],
            self::CATEGORY_CUSTOM => ['label' => 'Custom', 'icon' => '🏷'],
        ];
    }

    public static function categories(): array
    {
        return collect(self::categoryMeta())
            ->map(fn (array $meta) => $meta['label'])
            ->all();
    }

    public static function categoryLabel(?string $category): string
    {
        return self::categoryMeta()[$category]['label'] ?? 'Custom';
    }

    public static function categoryIcon(?string $category): string
    {
        return self::categoryMeta()[$category]['icon'] ?? '🏷';
    }

    public static function isCharacterCategory(?string $category): bool
    {
        return $category === self::CATEGORY_CHARACTER;
    }

    public static function normalizeTags(array|string|null $tags): array
    {
        $values = is_array($tags)
            ? $tags
            : preg_split('/[\n,]+/', (string) ($tags ?? ''));

        return collect($values)
            ->map(fn ($tag) => is_string($tag) ? trim(mb_strtolower($tag)) : '')
            ->filter(fn (string $tag) => $tag !== '')
            ->unique()
            ->values()
            ->all();
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

    public function linkedCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'linked_character_id');
    }

    public function draftLinkedCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'draft_linked_character_id');
    }

    public function hasPublishedContent(): bool
    {
        if (self::isCharacterCategory($this->category)) {
            return $this->published_at !== null
                && $this->linked_character_id !== null
                && $this->category !== null;
        }

        return $this->published_at !== null
            && $this->title !== null
            && $this->category !== null
            && $this->body !== null;
    }

    public function hasPendingDraft(): bool
    {
        if (self::isCharacterCategory($this->draft_category)) {
            return $this->draft_category !== null
                && $this->draft_linked_character_id !== null;
        }

        return $this->draft_title !== null
            && $this->draft_category !== null
            && $this->draft_body !== null;
    }

    public function effectiveCategory(bool $canSeeDraft = true): ?string
    {
        return $this->category ?? ($canSeeDraft ? $this->draft_category : null);
    }

    public function effectiveTitle(bool $canSeeDraft = true): ?string
    {
        return $this->title ?? ($canSeeDraft ? $this->draft_title : null);
    }

    public function effectiveLinkedCharacter(bool $canSeeDraft = true): ?Character
    {
        if ($this->hasPublishedContent()) {
            return $this->linkedCharacter;
        }

        if ($canSeeDraft && $this->hasPendingDraft()) {
            return $this->draftLinkedCharacter;
        }

        return $this->linkedCharacter ?? ($canSeeDraft ? $this->draftLinkedCharacter : null);
    }
}
