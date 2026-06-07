<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CharacterProfile extends Model
{
    use HasFactory;

    public const TEMPLATE_STORYBOX = 'storybox';

    protected $fillable = [
        'character_id',
        'template_type',
        'tagline',
        'avatar_url',
        'profile_image_url',
        'banner_url',
        'biography',
        'hooks',
        'external_links',
        'custom_html',
        'custom_css',
        'custom_js',
        'custom_profile_enabled',
        'custom_profile_disabled_by_admin',
    ];

    protected function casts(): array
    {
        return [
            'external_links' => 'array',
            'custom_profile_enabled' => 'boolean',
            'custom_profile_disabled_by_admin' => 'boolean',
        ];
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(CharacterProfileRevision::class)->latest();
    }

    public function shouldRenderCustomProfile(): bool
    {
        return $this->custom_profile_enabled
            && ! $this->custom_profile_disabled_by_admin
            && (
                filled($this->custom_html)
                || filled($this->custom_css)
                || filled($this->custom_js)
            );
    }

    public function createRevisionSnapshot(): CharacterProfileRevision
    {
        return $this->revisions()->create([
            'custom_html' => $this->custom_html,
            'custom_css' => $this->custom_css,
            'custom_js' => $this->custom_js,
        ]);
    }
}
