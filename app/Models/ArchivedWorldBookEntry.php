<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchivedWorldBookEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'archived_world_book_id',
        'source_world_book_entry_id',
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
        ];
    }

    public function archivedWorldBook(): BelongsTo
    {
        return $this->belongsTo(ArchivedWorldBook::class);
    }
}
