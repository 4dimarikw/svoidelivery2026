<?php

namespace Tests\Feature;

use Domain\Auth\Models\User;
use Domain\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Leeto\Seo\SeoManager;
use Tests\TestCase;

/**
 * Начальные SEO-записи создаются через DB::table(), чтобы события модели не инициализировали SeoManager до
 * HTTP-запроса. Контейнер теста общий для подготовки данных и запроса; singleton должен запомнить URL
 * проверяемого запроса.
 */
class SeoRenderingTest extends TestCase
{
    use RefreshDatabase;

    private function seedSeoRow(string $url, string $title, ?string $description = null): void
    {
        DB::table('seo')->insert([
            'url' => $url,
            'title' => $title,
            'description' => $description,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_seo_row_title_wins_over_an_explicit_title_prop(): void
    {
        // В layouts/app.blade.php SEO-заголовок текущего URL имеет приоритет над переданным свойством title
        // страницы.
        $this->seedSeoRow('/cart', 'From Seo Table');

        $response = $this->actingAs(User::factory()->create())->get(route('cart.index'));

        $response->assertOk();
        $response->assertSee('<title>From Seo Table</title>', false);
        // Текст «Корзина» допустим в мобильной навигации; проверяется только содержимое тега title.
        $response->assertDontSee('<title>'.__('account.cart.title').'</title>', false);
    }

    public function test_page_without_a_seo_row_shows_app_name_not_its_own_title_prop(): void
    {
        // Без SEO-записи пакет возвращает config('seo.default.title'), поэтому оператор ?? не использует title
        // страницы. Проверяется отображение APP_NAME по умолчанию.
        $response = $this->actingAs(User::factory()->create())->get(route('cart.index'));

        $response->assertOk();
        $response->assertSee('<title>'.config('app.name').'</title>', false);
    }

    public function test_home_page_falls_back_to_app_name_without_a_seo_row(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('<title>'.config('app.name').'</title>', false);
    }

    public function test_home_page_uses_the_seo_table_title_when_present(): void
    {
        $this->seedSeoRow('/', 'Custom Catalog Title');

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('<title>Custom Catalog Title</title>', false);
    }

    public function test_meta_description_renders_when_the_seo_row_has_one(): void
    {
        $this->seedSeoRow('/about', 'О нас', 'Тестовое описание');

        $response = $this->get(route('about'));

        $response->assertOk();
        $response->assertSee('name="description" content="Тестовое описание"', false);
    }

    public function test_meta_description_is_omitted_without_a_seo_row(): void
    {
        $response = $this->get(route('about'));

        $response->assertOk();
        $response->assertDontSee('name="description"', false);
    }

    public function test_product_page_renders_the_seo_text_block_raw(): void
    {
        // Product::factory()->create() сам пишет строку seo через
        // Product::booted() -> SyncProductSeoAction — тот же Seo::updateOrCreate(),
        // тот же created()/updated()-хук, что и в комментарии класса выше:
        // это тоже досрочно резолвит singleton SeoManager (внутри Seo::flushCache()),
        // на этот раз ДО того, как мы вообще начали GET-запрос к странице товара.
        // forgetInstance() заставляет seo() резолвиться заново внутри
        // $this->get() ниже, против настоящего url страницы товара.
        $product = Product::factory()->create();
        $this->app->forgetInstance(SeoManager::class);

        $response = $this->get(route('product.show', $product));

        $response->assertOk();
        $response->assertSee('<meta property="og:title"', false);
        $response->assertSee('<script type="application/ld+json">', false);
    }
}
