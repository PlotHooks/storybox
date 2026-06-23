<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArchivedWorldBook extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_user_id',
        'original_room_id',
        'original_room_name',
        'room_deleted_at',
        'entry_count',
        'recovery_key',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'room_deleted_at' => 'datetime',
            'archived_at' => 'datetime',
            'entry_count' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ArchivedWorldBookEntry::class);
    }
}
