<?php

namespace Tests\Feature;

use Domain\Auth\Models\User;
use Domain\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Leeto\Seo\SeoManager;
use Tests\TestCase;

/**
 * Seo::create()/updateOrCreate() are deliberately NOT used to seed rows
 * here — Leeto\Seo\Models\Seo::boot() fires flushCache() on created/updated,
 * which resolves the seo() singleton (SeoManager) right there and then,
 * snapshotting request()->getRequestUri() at THAT moment. In a real request
 * that's harmless (the manager instance dies with the process); in a
 * feature test the container — and thus the singleton — is shared across
 * both the seeding step and the later $this->get() call, so seeding via
 * the model would freeze the manager on the WRONG url before the request
 * we're actually testing even starts. Raw DB::table('seo')->insert() writes
 * the row without touching the model/events, so seo() only ever resolves
 * inside the real request below, against the real request url.
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
        // layouts/app.blade.php: seo()->meta()->title() ?? $title ?: config('app.name')
        // — the seo-table row for the current url takes priority over a
        // page's own :title prop (cart.blade.php passes :title explicitly,
        // resources/views/pages/cart.blade.php) when one exists.
        $this->seedSeoRow('/cart', 'From Seo Table');

        $response = $this->actingAs(User::factory()->create())->get(route('cart.index'));

        $response->assertOk();
        $response->assertSee('<title>From Seo Table</title>', false);
        // "Корзина" (account.cart.title) legitimately still appears elsewhere
        // on the page (mobile-nav link label) — assert only the <title> tag
        // itself doesn't contain it, not the whole response body.
        $response->assertDontSee('<title>'.__('account.cart.title').'</title>', false);
    }

    public function test_page_without_a_seo_row_shows_app_name_not_its_own_title_prop(): void
    {
        // Without a matching seo row, seo()->meta()->title() still resolves
        // non-null (package default = config('seo.default.title') = APP_NAME),
        // so the :title prop (cart's own __('account.cart.title')) never
        // actually surfaces here either — the ?? never reaches it. This
        // locks in the current (accepted) behavior: every page without its
        // own seo row shows APP_NAME, regardless of what :title it passed.
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
