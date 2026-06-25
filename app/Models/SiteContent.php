<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class SiteContent extends Model
{
    use HasFactory;

    public const CATEGORY_RULES = 'rules';
    public const CATEGORY_FAQ = 'faq';
    public const CATEGORY_PRIVACY_POLICY = 'privacy-policy';
    public const CATEGORY_TERMS_OF_SERVICE = 'terms-of-service';
    public const CATEGORY_ABOUT_STORYBOX = 'about-storybox';
    public const CATEGORY_CHANGELOG = 'changelog';

    public const PUBLIC_COLLECTION_RULES_FAQ = 'rules-faq';

    protected $fillable = [
        'title',
        'slug',
        'collection',
        'body',
        'sort_order',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public static function categoryOptions(): array
    {
        return [
            self::CATEGORY_RULES => 'Rules',
            self::CATEGORY_FAQ => 'FAQ',
            self::CATEGORY_PRIVACY_POLICY => 'Privacy Policy',
            self::CATEGORY_TERMS_OF_SERVICE => 'Terms of Service',
            self::CATEGORY_ABOUT_STORYBOX => 'About StoryBox',
            self::CATEGORY_CHANGELOG => 'Changelog',
        ];
    }

    public static function categoryLabel(?string $category): string
    {
        return self::categoryOptions()[$category ?? ''] ?? ($category ?: 'Unknown');
    }

    public static function publicCollectionOptions(): array
    {
        return [
            self::PUBLIC_COLLECTION_RULES_FAQ => 'Rules / FAQ',
        ];
    }

    public static function categoriesForPublicCollection(string $collection): array
    {
        return match ($collection) {
            self::PUBLIC_COLLECTION_RULES_FAQ => [
                self::CATEGORY_RULES,
                self::CATEGORY_FAQ,
            ],
            default => [],
        };
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeForCategory(Builder $query, string $category): Builder
    {
        return $query->where(function (Builder $builder) use ($category): void {
            $builder->where('collection', $category);

            if (in_array($category, [self::CATEGORY_RULES, self::CATEGORY_FAQ], true)) {
                $builder->orWhere(function (Builder $legacyBuilder) use ($category): void {
                    $legacyBuilder
                        ->where('collection', self::PUBLIC_COLLECTION_RULES_FAQ)
                        ->where('slug', $category);
                });
            }
        });
    }

    public function scopeForPublicCollection(Builder $query, string $collection): Builder
    {
        $categories = self::categoriesForPublicCollection($collection);

        return $query->where(function (Builder $builder) use ($categories, $collection): void {
            if ($categories !== []) {
                $builder->whereIn('collection', $categories);
            }

            if ($collection === self::PUBLIC_COLLECTION_RULES_FAQ) {
                $builder->orWhere(function (Builder $legacyBuilder): void {
                    $legacyBuilder
                        ->where('collection', self::PUBLIC_COLLECTION_RULES_FAQ)
                        ->whereIn('slug', [self::CATEGORY_RULES, self::CATEGORY_FAQ]);
                });
            }
        });
    }
}
