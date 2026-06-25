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

    public function test_site_content_endpoint_returns_published_rules_and_faq_categories_in_sort_order(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Viewer');

        SiteContent::query()->create([
            'title' => 'FAQ',
            'slug' => 'faq',
            'collection' => SiteContent::CATEGORY_FAQ,
            'body' => 'Second body',
            'sort_order' => 2,
            'is_published' => true,
        ]);
        SiteContent::query()->create([
            'title' => 'Rules',
            'slug' => 'rules',
            'collection' => SiteContent::CATEGORY_RULES,
            'body' => '[b]First[/b] body',
            'sort_order' => 1,
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
            'slug' => 'legacy-faq',
            'collection' => SiteContent::PUBLIC_COLLECTION_RULES_FAQ,
            'body' => 'Legacy group body',
            'sort_order' => 3,
            'is_published' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->getJson('/site-content/rules-faq')
            ->assertOk()
            ->assertJsonPath('default_document_slug', 'rules')
            ->assertJsonCount(2, 'documents')
            ->assertJsonPath('documents.0.slug', 'rules')
            ->assertJsonPath('documents.1.slug', 'faq')
            ->assertJsonPath('documents.0.rendered_body_html', '<strong>First</strong> body');
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
