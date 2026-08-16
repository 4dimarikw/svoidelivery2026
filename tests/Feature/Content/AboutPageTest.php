<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use Domain\Content\Models\ContentBlock;
use Domain\Content\Models\ContentBlockItem;
use Domain\Content\Models\SiteSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /about полностью CMS-driven (Domain\Content) — см.
 * docs/cms-ai-agent-guideline.md и resources/views/pages/about.blade.php.
 * Рендер идёт через <x-content.sections> → config('content.views'), поэтому
 * здесь же проверяется и сам диспетчер (§10 регламента): незарегистрированный
 * тип блока не должен попасть в публичный вывод.
 */
class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_about_page_renders_active_block_content_and_items(): void
    {
        $section = SiteSection::factory()->create(['key' => 'about', 'route_name' => 'about']);

        $block = ContentBlock::factory()->create([
            'site_section_id' => $section->id,
            'key' => 'about',
            'type' => 'about_info',
            'content' => [
                'heading' => 'О нас',
                'lead' => 'Приветствуем вас в онлайн-баре тестовом',
                'schedule' => 'Заказы принимаем по будням',
                'ordering_heading' => 'Как оформить заказ:',
                'promo_lead' => 'Акции:',
                'notice' => 'ОБЯЗАТЕЛЬНО ПРОВЕРЬТЕ ВЛОЖЕНИЕ',
            ],
        ]);

        ContentBlockItem::factory()->create([
            'content_block_id' => $block->id,
            'group_key' => 'ordering_rules',
            'key' => 'rule-1',
            'content' => ['text' => 'заказ должен быть кратным 12'],
        ]);

        ContentBlockItem::factory()->create([
            'content_block_id' => $block->id,
            'group_key' => 'promotions',
            'key' => 'promo-1',
            'content' => ['text' => 'Скидка 5% на день рождения'],
        ]);

        $response = $this->get(route('about'));

        $response->assertOk();
        $response->assertSee('О нас');
        $response->assertSee('Приветствуем вас в онлайн-баре тестовом');
        $response->assertSee('Заказы принимаем по будням');
        $response->assertSee('заказ должен быть кратным 12');
        $response->assertSee('Скидка 5% на день рождения');
        $response->assertSee('ОБЯЗАТЕЛЬНО ПРОВЕРЬТЕ ВЛОЖЕНИЕ');
    }

    public function test_inactive_block_is_not_rendered(): void
    {
        $section = SiteSection::factory()->create(['key' => 'about', 'route_name' => 'about']);

        ContentBlock::factory()->create([
            'site_section_id' => $section->id,
            'type' => 'about_info',
            'content' => ['heading' => 'Скрытый заголовок'],
            'is_active' => false,
        ]);

        $this->get(route('about'))->assertOk()->assertDontSee('Скрытый заголовок');
    }

    public function test_block_type_without_view_mapping_is_not_rendered(): void
    {
        $section = SiteSection::factory()->create(['key' => 'about', 'route_name' => 'about']);

        // about_info зарегистрирован в config('content.types') (иначе
        // ContentBlockObserver отклонил бы создание — по-настоящему
        // незарегистрированный тип нельзя даже сохранить), но здесь мы
        // временно очищаем config('content.views') — тот же эффект, как
        // если бы у зарегистрированного типа не было записи вовсе:
        // <x-content.sections> должна молча пропустить блок, а не упасть
        // на несуществующем компоненте.
        config(['content.views' => []]);

        ContentBlock::factory()->create([
            'site_section_id' => $section->id,
            'type' => 'about_info',
            'content' => ['heading' => 'Без view-компонента'],
        ]);

        $this->get(route('about'))->assertOk()->assertDontSee('Без view-компонента');
    }
}
