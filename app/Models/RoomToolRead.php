<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomToolRead extends Model
{
    use HasFactory;

    public const TOOL_WORLD_BOOK = 'world_book';
    public const TOOL_NOTICE_BOARD = 'notice_board';
    public const TOOL_PINNED_NOTES = 'pinned_notes';

    protected $fillable = [
        'user_id',
        'room_id',
        'tool',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    public static function tools(): array
    {
        return [
            self::TOOL_WORLD_BOOK,
            self::TOOL_NOTICE_BOARD,
            self::TOOL_PINNED_NOTES,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
