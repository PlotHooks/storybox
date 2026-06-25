<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Room;
use App\Models\SiteContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SiteContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_content_endpoint_groups_published_rules_and_faq_documents_by_category_in_sort_order(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Viewer');

        SiteContent::query()->create([
            'title' => 'What is StoryBox?',
            'slug' => 'what-is-storybox',
            'collection' => SiteContent::CATEGORY_FAQ,
            'body' => 'Second FAQ body',
            'sort_order' => 2,
            'is_published' => true,
        ]);
        SiteContent::query()->create([
            'title' => '18+ Only',
            'slug' => '18-plus-only',
            'collection' => SiteContent::CATEGORY_RULES,
            'body' => '[b]First[/b] rules body',
            'sort_order' => 1,
            'is_published' => true,
        ]);
        SiteContent::query()->create([
            'title' => 'How do characters work?',
            'slug' => 'how-do-characters-work',
            'collection' => SiteContent::CATEGORY_FAQ,
            'body' => 'First FAQ body',
            'sort_order' => 1,
            'is_published' => true,
        ]);
        SiteContent::query()->create([
            'title' => 'No Harassment',
            'slug' => 'no-harassment',
            'collection' => SiteContent::CATEGORY_RULES,
            'body' => 'Second rules body',
            'sort_order' => 2,
            'is_published' => true,
        ]);
        SiteContent::query()->create([
            'title' => 'Privacy Policy',
            'slug' => 'privacy-policy',
            'collection' => SiteContent::CATEGORY_PRIVACY_POLICY,
            'body' => 'Draft body',
            'sort_order' => 1,
            'is_published' => true,
        ]);
        SiteContent::query()->create([
            'title' => 'Legacy FAQ',
            'slug' => 'faq',
            'collection' => SiteContent::PUBLIC_COLLECTION_RULES_FAQ,
            'body' => 'Legacy group body',
            'sort_order' => 3,
            'is_published' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->getJson('/site-content/rules-faq')
            ->assertOk()
            ->assertJsonPath('default_category', SiteContent::CATEGORY_RULES)
            ->assertJsonCount(3, 'categories')
            ->assertJsonPath('categories.0.key', SiteContent::CATEGORY_RULES)
            ->assertJsonPath('categories.0.label', 'Rules')
            ->assertJsonPath('categories.0.documents.0.slug', '18-plus-only')
            ->assertJsonPath('categories.0.documents.1.slug', 'no-harassment')
            ->assertJsonPath('categories.0.documents.0.rendered_body_html', '<strong>First</strong> rules body')
            ->assertJsonPath('categories.1.key', SiteContent::CATEGORY_FAQ)
            ->assertJsonPath('categories.1.label', 'FAQ')
            ->assertJsonPath('categories.1.documents.0.slug', 'how-do-characters-work')
            ->assertJsonPath('categories.1.documents.1.slug', 'what-is-storybox')
            ->assertJsonPath('categories.2.key', SiteContent::CATEGORY_PRIVACY_POLICY)
            ->assertJsonPath('categories.2.label', 'Privacy Policy')
            ->assertJsonPath('categories.2.documents.0.slug', 'privacy-policy');
    }

    public function test_site_content_endpoint_keeps_legacy_rules_faq_records_grouped_under_their_category_tab(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Viewer');

        SiteContent::query()->create([
            'title' => 'Legacy FAQ',
            'slug' => 'faq',
            'collection' => SiteContent::PUBLIC_COLLECTION_RULES_FAQ,
            'body' => 'Legacy FAQ body',
            'sort_order' => 1,
            'is_published' => true,
        ]);
        SiteContent::query()->create([
            'title' => 'Legacy Rules',
            'slug' => 'rules',
            'collection' => SiteContent::PUBLIC_COLLECTION_RULES_FAQ,
            'body' => 'Legacy Rules body',
            'sort_order' => 2,
            'is_published' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->getJson('/site-content/rules-faq')
            ->assertOk()
            ->assertJsonPath('default_category', SiteContent::CATEGORY_RULES)
            ->assertJsonPath('categories.0.key', SiteContent::CATEGORY_RULES)
            ->assertJsonPath('categories.0.documents.0.title', 'Legacy Rules')
            ->assertJsonPath('categories.1.key', SiteContent::CATEGORY_FAQ)
            ->assertJsonPath('categories.1.documents.0.title', 'Legacy FAQ');
    }

    public function test_site_content_endpoint_hides_categories_without_published_documents(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Viewer');

        SiteContent::query()->create([
            'title' => 'Terms of Service',
            'slug' => 'terms-of-service',
            'collection' => SiteContent::CATEGORY_TERMS_OF_SERVICE,
            'body' => 'Draft terms body',
            'sort_order' => 1,
            'is_published' => false,
        ]);
        SiteContent::query()->create([
            'title' => 'About StoryBox',
            'slug' => 'about-storybox',
            'collection' => SiteContent::CATEGORY_ABOUT_STORYBOX,
            'body' => 'About body',
            'sort_order' => 1,
            'is_published' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->getJson('/site-content/rules-faq')
            ->assertOk()
            ->assertJsonCount(1, 'categories')
            ->assertJsonPath('categories.0.key', SiteContent::CATEGORY_ABOUT_STORYBOX)
            ->assertJsonMissingPath('categories.1')
            ->assertJsonMissing(['key' => SiteContent::CATEGORY_TERMS_OF_SERVICE]);
    }

    public function test_admin_site_content_manager_renders_custom_storybox_layout(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        SiteContent::query()->create([
            'title' => 'Rules',
            'slug' => 'rules',
            'collection' => SiteContent::CATEGORY_RULES,
            'body' => 'Rules body',
            'sort_order' => 1,
            'is_published' => true,
        ]);
        SiteContent::query()->create([
            'title' => 'FAQ',
            'slug' => 'faq',
            'collection' => SiteContent::CATEGORY_FAQ,
            'body' => 'FAQ body',
            'sort_order' => 1,
            'is_published' => false,
        ]);

        $this->actingAs($admin)
            ->get('/panopticon/site-contents')
            ->assertOk()
            ->assertSee('Site Content Manager')
            ->assertSee('StoryBox Admin')
            ->assertSee('Rules')
            ->assertSee('FAQ')
            ->assertSee('Privacy Policy')
            ->assertSee('sb-site-manager-shell')
            ->assertSee('sb-site-manager-grid');
    }

    public function test_room_page_renders_rules_faq_button_and_removes_duplicate_top_room_header(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Owner');
        $roomName = 'Unique Room ' . Str::random(12);
        $room = Room::create([
            'name' => $roomName,
            'slug' => 'room-' . Str::random(16),
            'description' => 'Testing room header cleanup.',
            'user_id' => $user->id,
            'created_by' => $user->id,
            'type' => Room::TYPE_PUBLIC,
            'visibility' => Room::VISIBILITY_PUBLIC,
            'owner_character_id' => $character->id,
        ]);

        $content = $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk()
            ->assertSee('Rules / FAQ')
            ->assertSee('StoryBox')
            ->getContent();

        $this->assertStringContainsString('<h1 class="truncate text-lg font-semibold text-[#f2dfb5] md:text-xl">' . $roomName . '</h1>', $content);
        $this->assertStringNotContainsString('<header class="border-b border-[#2a241a] bg-[#0b0b0c]">', $content);
    }

    public function test_room_page_mounts_global_site_content_window_shell(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Owner');
        $room = Room::create([
            'name' => 'Docs Room',
            'slug' => 'room-' . Str::random(16),
            'description' => 'Testing global site content window mount.',
            'user_id' => $user->id,
            'created_by' => $user->id,
            'type' => Room::TYPE_PUBLIC,
            'visibility' => Room::VISIBILITY_PUBLIC,
            'owner_character_id' => $character->id,
        ]);

        $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk()
            ->assertSee('id="site-content-window"', false)
            ->assertSee('id="site-content-tabs"', false);
    }

    private function createUserWithCharacter(string $name): array
    {
        $user = User::factory()->create();

        return [$user, Character::create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => 'character-' . Str::random(16),
        ])];
    }
}
