<?php

namespace Tests\Feature;

use ContentCms\Models\ContentBlock;
use ContentCms\Models\SiteMenuItem;
use ContentCms\Models\SiteSection;
use Database\Seeders\TermsPageSeeder;
use Domain\Content\Types\TermsTextBlockType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use MoonShine\TinyMce\Fields\TinyMce;
use Tests\TestCase;

class TermsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_read_placeholder_and_registration_link(): void
    {
        $this->get(route('terms'))->assertOk()->assertSee(__('terms.empty'));
        $this->get(route('register'))->assertOk()->assertSee(route('terms'), false)
            ->assertSee('target="_blank"', false)->assertSee(__('terms.read_accessible'));
    }

    public function test_seeder_preserves_content_and_does_not_add_menu_items(): void
    {
        $menus = SiteMenuItem::count();
        $this->seed(TermsPageSeeder::class);
        $block = ContentBlock::where('type', 'terms_text')->sole();
        $block->update(['content' => ['body' => '<h2>Раздел</h2><p><strong>Правила</strong></p>']]);
        $this->seed(TermsPageSeeder::class);
        $this->assertSame(1, SiteSection::where('key', 'terms')->count());
        $this->assertSame(1, ContentBlock::where('type', 'terms_text')->count());
        $this->assertSame($menus, SiteMenuItem::count());
        $this->assertSame($block->content, $block->fresh()->content);
        $this->get(route('terms'))->assertOk()->assertSee('<h2>Раздел</h2>', false)
            ->assertSee('<strong>Правила</strong>', false)->assertDontSee(__('terms.empty'));
        $this->assertInstanceOf(TinyMce::class, (new TermsTextBlockType)->blockFields()[0]);
    }

    public function test_unsafe_html_is_removed_and_inactive_content_is_hidden(): void
    {
        $this->seed(TermsPageSeeder::class);
        $block = ContentBlock::where('type', 'terms_text')->sole();
        $block->update(['content' => ['body' => '<p onclick="attack()">Условия</p><script>attack()</script><a href="javascript:attack()">Ссылка</a>']]);
        $this->get(route('terms'))->assertOk()->assertSee('Условия')->assertDontSee('attack()', false);
        $block->update(['is_active' => false]);
        $this->get(route('terms'))->assertSee(__('terms.empty'));
        $block->update(['is_active' => true]);
        $block->section->update(['is_active' => false]);
        $this->get(route('terms'))->assertSee(__('terms.empty'));
    }

    public function test_admin_can_save_and_clear_the_editor(): void
    {
        $this->seed(TermsPageSeeder::class);
        $block = ContentBlock::where('type', 'terms_text')->sole();
        $admin = MoonshineUser::create([
            'email' => 'terms@test.local', 'password' => bcrypt('password'), 'name' => 'Admin',
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
        ]);
        $this->actingAs($admin, 'moonshine');
        $this->get("/admin/resource/content-block-resource/content-block-form-page/{$block->id}")
            ->assertOk()->assertSee('Текст условий')->assertSee('tinymce', false);
        foreach (['<h2>Правила</h2><p><strong>Текст</strong></p>', ''] as $body) {
            $this->put("/admin/resource/content-block-resource/crud/{$block->id}", [
                'site_section_id' => $block->site_section_id, 'key' => $block->key,
                'title' => $block->title, 'sort_order' => 0, 'is_active' => 1,
                'content' => ['body' => $body],
            ])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame($body, $block->fresh()->content['body'] ?? '');
        }
    }
}
