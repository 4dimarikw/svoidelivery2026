<?php

namespace Tests\Feature\Content;

use ContentCms\Models\ContentBlock;
use ContentCms\Models\SiteSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

class AboutInfoJsonTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_edit_reorder_disable_and_clear_lists(): void
    {
        $block = ContentBlock::factory()->create(['type' => 'about_info']);
        $user = MoonshineUser::query()->create([
            'email' => 'json@test.local', 'password' => bcrypt('password'),
            'name' => 'Admin', 'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
        ]);
        $this->actingAs($user, 'moonshine');
        $this->get("/admin/resource/content-block-resource/content-block-form-page/{$block->id}")
            ->assertOk()->assertSee('Правила оформления заказа')->assertSee('Акции')
            ->assertSee('content[ordering_rules]', false)->assertSee('content[promotions]', false)
            ->assertDontSee('Элементы блока');

        $payload = [
            'site_section_id' => $block->site_section_id, 'key' => $block->key,
            'title' => $block->title, 'sort_order' => 0, 'is_active' => 1,
        ];
        $url = "/admin/resource/content-block-resource/crud/{$block->id}";
        foreach ([
            [['text' => 'First', 'is_active' => 1], ['text' => 'Second', 'is_active' => 1]],
            [['text' => 'Second', 'is_active' => 0], ['text' => 'First edited', 'is_active' => 1]],
            [],
        ] as $rows) {
            $this->put($url, $payload + ['content' => ['ordering_rules' => $rows, 'promotions' => $rows]])
                ->assertRedirect()->assertSessionHasNoErrors();
            foreach (['ordering_rules', 'promotions'] as $group) {
                $this->assertEquals($rows, $block->refresh()->content[$group]);
            }
        }

        // An empty browser repeater omits the nested key entirely.
        $this->put($url, $payload + ['content' => ['heading' => 'Heading']])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame([], $block->refresh()->content['ordering_rules']);
        $this->assertSame([], $block->content['promotions']);

        foreach (['ordering_rules', 'promotions'] as $group) {
            foreach (['', str_repeat('a', 501)] as $invalidText) {
                $this->put($url, $payload + ['content' => [$group => [['text' => $invalidText, 'is_active' => 'invalid']]]])
                    ->assertSessionHasErrors(["content.$group.0.text", "content.$group.0.is_active"], null, 'content-block-resource');
            }
        }
    }

    public function test_empty_and_missing_lists_do_not_render_headings(): void
    {
        $section = SiteSection::factory()->create(['key' => 'about', 'route_name' => 'about']);
        $block = ContentBlock::factory()->create([
            'site_section_id' => $section->id, 'type' => 'about_info',
            'content' => ['ordering_heading' => 'Rules heading', 'promo_lead' => 'Promo heading'],
        ]);
        foreach ([[], ['ordering_rules' => [], 'promotions' => []]] as $lists) {
            $block->update(['content' => array_merge($block->content, $lists)]);
            $this->get(route('about'))->assertOk()->assertDontSee('Rules heading')->assertDontSee('Promo heading');
        }
    }
}
