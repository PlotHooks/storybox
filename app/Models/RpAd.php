<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class RpAd extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const TYPE_ROOM = 'room';
    public const TYPE_DM = 'dm';

    protected $fillable = [
        'character_id',
        'room_id',
        'type',
        'title',
        'body',
        'tags',
        'is_nsfw',
        'refreshed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'is_nsfw' => 'boolean',
            'refreshed_at' => 'datetime',
            'expires_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public static function types(): array
    {
        return [
            self::TYPE_ROOM => 'Room',
            self::TYPE_DM => 'DM',
        ];
    }

    public static function typeLabel(?string $type): string
    {
        return self::types()[$type] ?? 'Ad';
    }

    public static function normalizeTags(array|string|null $tags): array
    {
        $values = is_array($tags)
            ? $tags
            : preg_split('/[\n,]+/', (string) ($tags ?? ''));

        $seen = [];
        $normalized = [];

        foreach ($values as $value) {
            $tag = is_string($value) ? trim($value) : '';

            if ($tag === '') {
                continue;
            }

            $key = mb_strtolower($tag);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $normalized[] = $tag;
        }

        return $normalized;
    }

    public function isActive(): bool
    {
        return $this->deleted_at === null
            && $this->expires_at instanceof Carbon
            && $this->expires_at->isFuture();
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
