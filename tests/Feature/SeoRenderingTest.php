<?php

namespace Tests\Feature;

use Domain\Auth\Models\User;
use Domain\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

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

    public function test_page_without_a_seo_row_uses_its_title_prop(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('cart.index'));

        $response->assertOk();
        $response->assertSee('<title>'.__('account.cart.title').'</title>', false);
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
        $product = Product::factory()->create();

        $response = $this->get(route('product.show', $product));

        $response->assertOk();
        $response->assertSee('<meta property="og:title"', false);
        $response->assertSee('<script type="application/ld+json">', false);
    }

    public function test_site_indexing_is_disabled_for_pages_redirects_and_errors(): void
    {
        $this->assertFalse(config('seo.indexable'));
        $this->get(route('about'))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $this->get(route('cart.index'))
            ->assertRedirect()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/missing-seo-test-page')
            ->assertNotFound()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_robots_allows_crawling_to_read_noindex_headers(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee("User-agent: *\nDisallow:", false)
            ->assertDontSee('Sitemap:', false);
    }

    public function test_metadata_is_escaped_and_canonical_is_rendered_once(): void
    {
        $this->seedSeoRow('/about', 'Title <script>alert("x")</script>', 'Description "quoted" <tag>');
        $response = $this->get(route('about'))->assertOk();
        $response->assertSee('<title>'.e('Title <script>alert("x")</script>').'</title>', false);
        $response->assertSee('content="'.e('Description "quoted" <tag>').'"', false);
        $response->assertSee('<link rel="canonical" href="'.route('about').'">', false);
        $this->assertSame(1, substr_count($response->getContent(), '<title>'));
        $this->assertSame(1, substr_count($response->getContent(), 'rel="canonical"'));
    }

    public function test_query_specific_record_wins_and_changes_are_visible_on_next_request(): void
    {
        $this->seedSeoRow('/about', 'Path title');
        $this->seedSeoRow('/about?source=test', 'Query title');
        $this->get('/about?source=test')->assertSee('<title>Query title</title>', false);
        DB::table('seo')->where('url', '/about?source=test')->update(['title' => 'Updated title']);
        $this->get('/about?source=test')->assertSee('<title>Updated title</title>', false);
    }
}
