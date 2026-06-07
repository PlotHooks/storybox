<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharacterProfileRevision extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'character_profile_id',
        'custom_html',
        'custom_css',
        'custom_js',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(CharacterProfile::class, 'character_profile_id');
    }
}
