<?php

namespace Tests\Feature\Content;

use ContentCms\Actions\Content\LoadPublishedSectionByKey;
use ContentCms\Actions\Content\LoadPublishedSections;
use ContentCms\Actions\Menu\BuildMenu;
use ContentCms\Actions\Menu\MoveSiteMenuItemBranch;
use ContentCms\Content\ContentBlockTypeRegistry;
use ContentCms\Models\ContentBlock;
use ContentCms\Models\ContentBlockItem;
use ContentCms\Models\SiteMenu;
use ContentCms\Models\SiteMenuItem;
use ContentCms\Models\SiteSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Fixtures\Content\IntegrationBlockType;
use Tests\TestCase;

class PackageIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['content-cms.types' => [...config('content-cms.types'), IntegrationBlockType::class]]);
        $this->app->forgetInstance(ContentBlockTypeRegistry::class);
    }

    private function admin(): void
    {
        $this->actingAs(MoonshineUser::query()->create([
            'email' => 'integration@example.test', 'name' => 'Admin', 'password' => bcrypt('password'),
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
        ]), 'moonshine');
    }

    public function test_block_and_item_forms_validate_content_only_on_second_stage(): void
    {
        $this->admin();
        $section = SiteSection::factory()->create();
        $fields = ['site_section_id' => $section->id, 'type' => 'integration', 'key' => 'staged', 'title' => 'Staged', 'sort_order' => 0, 'is_active' => 0];
        $this->post('/admin/resource/content-block-resource/crud', $fields)->assertRedirect()->assertSessionHasNoErrors();
        $block = ContentBlock::query()->where('key', 'staged')->firstOrFail();
        $url = "/admin/resource/content-block-resource/crud/{$block->id}";
        $this->put($url, $fields)->assertSessionHasErrorsIn('content-block-resource', 'content.heading');
        $this->put($url, $fields + ['content' => ['heading' => 'Published heading']])->assertSessionHasNoErrors();
        $this->assertSame('Published heading', $block->fresh()->content['heading']);
        $this->get("/admin/resource/content-block-resource/content-block-form-page/{$block->id}")->assertOk()->assertSee('Файл: document');

        $fields = ['content_block_id' => $block->id, 'group_key' => 'cards', 'key' => 'card', 'title' => 'Card', 'sort_order' => 0, 'is_active' => 0];
        $this->post('/admin/resource/content-block-item-resource/crud', $fields)->assertRedirect()->assertSessionHasNoErrors();
        $item = ContentBlockItem::query()->where('key', 'card')->firstOrFail();
        $url = "/admin/resource/content-block-item-resource/crud/{$item->id}";
        $this->put($url, $fields)->assertSessionHasErrorsIn('content-block-item-resource', 'content.text');
        $this->put($url, $fields + ['content' => ['text' => 'Card text']])->assertSessionHasNoErrors();
        $this->assertSame('Card text', $item->fresh()->content['text']);
    }

    public function test_multiple_global_sections_and_local_menu_urls_are_supported_in_forms(): void
    {
        $this->admin();
        foreach (['header', 'footer'] as $key) {
            $this->post('/admin/resource/site-section-resource/crud', [
                'key' => $key, 'title' => $key, 'route_name' => '', 'fragment' => 'ignored', 'sort_order' => 0, 'is_active' => 1,
            ])->assertRedirect()->assertSessionHasNoErrors();
        }
        $global = SiteSection::query()->where('key', 'footer')->firstOrFail();
        $this->assertNull($global->route_name);
        $this->assertSame('', $global->fragment);
        $this->assertNull($global->url());
        $this->assertTrue(app(LoadPublishedSections::class)->handle('home')->isEmpty());
        $this->assertSame($global->id, app(LoadPublishedSectionByKey::class)->handle('footer')->id);

        $menu = SiteMenu::factory()->create();
        $fields = ['site_menu_id' => $menu->id, 'key' => 'local', 'external_url' => '/about#info', 'label' => 'Local', 'is_active' => 1];
        $this->post('/admin/resource/site-menu-item-resource/crud', $fields)->assertSessionHasNoErrors();
        $this->assertSame('/about#info', SiteMenuItem::query()->where('key', 'local')->firstOrFail()->resolvedUrl());
        $this->post('/admin/resource/site-menu-item-resource/crud', array_replace($fields, [
            'key' => 'global', 'external_url' => null, 'site_section_id' => $global->id,
        ]))->assertSessionHasErrorsIn('site-menu-item-resource', 'site_section_id');
    }

    public function test_media_uses_legacy_discriminators_and_public_rendering_needs_no_queries(): void
    {
        Storage::fake('public');
        $section = SiteSection::factory()->create(['route_name' => 'about', 'fragment' => 'cms-anchor']);
        $block = ContentBlock::factory()->ofType('integration')->create(['site_section_id' => $section->id, 'content' => ['heading' => '<script>unsafe</script>']]);
        $item = ContentBlockItem::factory()->inGroup($block, 'cards')->create();
        $cover = $block->addMedia(UploadedFile::fake()->image('cover.jpg'))->toMediaCollection('cover', 'public');
        $image = $item->addMedia(UploadedFile::fake()->image('card.jpg'))->toMediaCollection('image', 'public');
        $document = $block->addMediaFromString("%PDF-1.4\n")->usingFileName('guide.pdf')->toMediaCollection('document', 'public');
        $this->assertSame('Domain\\Content\\Models\\ContentBlock', $cover->model_type);
        $this->assertSame('Domain\\Content\\Models\\ContentBlockItem', $image->model_type);
        $this->assertInstanceOf(ContentBlock::class, Media::findOrFail($cover->id)->model);
        $this->assertInstanceOf(ContentBlockItem::class, Media::findOrFail($image->id)->model);

        config(['content.views.integration' => 'components.content.about-info']);
        $sections = app(LoadPublishedSections::class)->handle('about');
        DB::enableQueryLog();
        DB::flushQueryLog();
        $loaded = $sections->first()->publishedBlocks->first();
        $this->assertNotEmpty($loaded->getFirstMediaUrl('cover'));
        $this->assertNotEmpty($loaded->getFirstMediaUrl('document'));
        $this->assertNotEmpty($loaded->publishedItems->first()->getFirstMediaUrl('image'));
        $html = Blade::render('<x-content.sections :sections="$sections" />', ['sections' => $sections]);
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertStringContainsString('&lt;script&gt;unsafe&lt;/script&gt;', $html);
        $this->assertSame(1, substr_count($html, 'id="cms-anchor"'));

        $oldPath = $cover->getPathRelativeToRoot();
        $replacement = $block->addMedia(UploadedFile::fake()->image('replacement.jpg'))->toMediaCollection('cover', 'public');
        Storage::disk('public')->assertMissing($oldPath);
        $section->delete();
        foreach ([$replacement, $image, $document] as $media) {
            Storage::disk('public')->assertMissing($media->getPathRelativeToRoot());
        }
        $this->assertSame(0, Media::query()->count());
    }

    public function test_package_menu_transfer_preserves_tree_and_rejects_key_conflicts(): void
    {
        $source = SiteMenu::factory()->create();
        $target = SiteMenu::factory()->create();
        $parent = SiteMenuItem::factory()->external()->create(['site_menu_id' => $source->id, 'key' => 'parent']);
        $child = SiteMenuItem::factory()->external()->create(['site_menu_id' => $source->id, 'parent_id' => $parent->id, 'key' => 'child']);
        app(MoveSiteMenuItemBranch::class)->handle($parent, $target);
        $this->assertSame($target->id, $child->fresh()->site_menu_id);
        $this->assertSame($parent->id, $child->fresh()->parent_id);
        foreach ([$source, $target] as $menu) {
            $this->assertFalse(SiteMenuItem::scoped(['site_menu_id' => $menu->id])->isBroken());
        }
        SiteMenuItem::factory()->external()->create(['site_menu_id' => $source->id, 'key' => 'child']);
        $before = DB::table('site_menu_items')->orderBy('id')->get()->toJson();
        try {
            app(MoveSiteMenuItemBranch::class)->handle($parent->fresh(), $source);
            $this->fail('Duplicate destination key must be rejected');
        } catch (ValidationException) {
            $this->assertSame($before, DB::table('site_menu_items')->orderBy('id')->get()->toJson());
        }
        $parent->fresh()->update(['is_active' => false]);
        $this->assertSame([], app(BuildMenu::class)->handle($target->key));
    }
}
