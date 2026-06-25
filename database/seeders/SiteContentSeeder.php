<?php

namespace Database\Seeders;

use App\Models\SiteContent;
use Illuminate\Database\Seeder;

class SiteContentSeeder extends Seeder
{
    public function run(): void
    {
        $documents = [
            [
                'title' => 'Rules',
                'slug' => 'rules',
                'collection' => SiteContent::CATEGORY_RULES,
                'body' => implode("\n\n", [
                    '[b]Be respectful.[/b] Treat other players and staff with basic courtesy.',
                    '[b]Keep consent clear.[/b] Pause and clarify if a scene needs an OOC check-in.',
                    '[b]Use the room tools appropriately.[/b] DMs, room tools, and moderation features all exist for a reason.',
                ]),
                'sort_order' => 1,
                'is_published' => true,
            ],
            [
                'title' => 'FAQ',
                'slug' => 'faq',
                'collection' => SiteContent::CATEGORY_FAQ,
                'body' => implode("\n\n", [
                    '[b]How do I join a room?[/b] Pick an active character, then enter any room you can access.',
                    '[b]How do I contact another player privately?[/b] Use the DMs window in the global header.',
                    '[b]Where do I find room-specific information?[/b] Check that room\'s own Rules, World Book, Notice Board, and Pinned Notes tools.',
                ]),
                'sort_order' => 2,
                'is_published' => true,
            ],
            [
                'title' => 'Privacy Policy',
                'slug' => 'privacy-policy',
                'collection' => SiteContent::CATEGORY_PRIVACY_POLICY,
                'body' => 'Draft placeholder for the StoryBox privacy policy.',
                'sort_order' => 1,
                'is_published' => false,
            ],
            [
                'title' => 'Terms of Service',
                'slug' => 'terms-of-service',
                'collection' => SiteContent::CATEGORY_TERMS_OF_SERVICE,
                'body' => 'Draft placeholder for the StoryBox terms of service.',
                'sort_order' => 1,
                'is_published' => false,
            ],
            [
                'title' => 'About StoryBox',
                'slug' => 'about-storybox',
                'collection' => SiteContent::CATEGORY_ABOUT_STORYBOX,
                'body' => 'Draft placeholder for an About StoryBox page.',
                'sort_order' => 1,
                'is_published' => false,
            ],
            [
                'title' => 'Changelog',
                'slug' => 'changelog',
                'collection' => SiteContent::CATEGORY_CHANGELOG,
                'body' => 'Draft placeholder for release notes and product changes.',
                'sort_order' => 1,
                'is_published' => false,
            ],
        ];

        foreach ($documents as $document) {
            SiteContent::query()->updateOrCreate(
                ['slug' => $document['slug']],
                $document,
            );
        }
    }
}
