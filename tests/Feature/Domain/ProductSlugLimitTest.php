<?php

namespace Tests\Feature\Domain;

use App\MoonShine\Resources\Product\Pages\ProductFormPage;
use Dimarik\LaravelSeo\Models\Seo;
use Dimarik\LaravelSeo\SeoUrl;
use Domain\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use MoonShine\Laravel\TypeCasts\ModelCaster;
use ReflectionMethod;
use Tests\TestCase;

class ProductSlugLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_generated_slugs_are_limited_after_transliteration_and_collision_suffixes(): void
    {
        $slugs = [];
        for ($index = 0; $index < 12; $index++) {
            $product = Product::factory()->create(['name' => str_repeat('щ', 128)]);
            $this->assertLessThanOrEqual(200, strlen($product->slug));
            $this->assertMatchesRegularExpression('/\A[a-z0-9-]+\z/', $product->slug);
            $url = route('product.show', $product, absolute: false);
            $this->assertSame($url, SeoUrl::forStorage($url));
            $this->assertDatabaseHas('seo', ['url' => $url]);
            $slugs[] = $product->slug;
        }
        $this->assertCount(12, array_unique($slugs));
        $this->assertStringEndsWith('-10', $slugs[10]);
        $this->assertSame(200, strlen($slugs[10]));
    }

    public function test_manual_200_character_slug_fits_with_maximum_bigint_id(): void
    {
        $product = Product::factory()->create(['slug' => str_repeat('a', 200)]);
        $this->assertSame(str_repeat('a', 200), $product->refresh()->slug);
        $key = $product->slug.$product->getSlugOptions()->selfHealingSeparator.'18446744073709551615';
        $url = route('product.show', $key, absolute: false);
        $this->assertSame(230, strlen($url));
        $this->assertSame($url, SeoUrl::forStorage($url));
        $this->assertSame('varchar(200)', collect(Schema::getColumns('products'))->firstWhere('name', 'slug')['type']);
        $this->assertTrue(Schema::hasIndex('products', ['slug'], 'unique'));
    }

    public function test_invalid_manual_slugs_are_rejected_before_product_is_saved(): void
    {
        foreach ([str_repeat('a', 201), 'кириллический-slug', 'slug with spaces', 'slug/with/path'] as $slug) {
            $count = Product::count();
            try {
                Product::factory()->create(['slug' => $slug]);
                $this->fail('Некорректный slug должен быть отклонён.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('slug', $exception->errors());
            }
            $this->assertSame($count, Product::count());
        }
    }

    public function test_form_enforces_the_same_slug_length_and_character_limits(): void
    {
        $page = app(ProductFormPage::class);
        $item = (new ModelCaster(Product::class))->cast(new Product);
        $rules = (new ReflectionMethod($page, 'rules'))->invoke($page, $item);
        $this->assertTrue(Validator::make(['slug' => str_repeat('a', 200)], ['slug' => $rules['slug']])->passes());
        foreach ([str_repeat('a', 201), 'кириллица', 'slug/with/path'] as $slug) {
            $this->assertTrue(Validator::make(['slug' => $slug], ['slug' => $rules['slug']])->fails());
        }
    }

    public function test_unrelated_updates_preserve_existing_slug_and_seo_url(): void
    {
        $product = Product::factory()->create(['name' => 'Existing product', 'slug' => 'existing-custom-address']);
        $url = route('product.show', $product, absolute: false);
        $seoId = Seo::where('url', $url)->value('id');
        $product->update(['stock_quantity' => 7, 'in_stock' => false]);
        $this->assertSame('existing-custom-address', $product->refresh()->slug);
        $this->assertSame($url, route('product.show', $product, absolute: false));
        $this->assertSame($seoId, Seo::where('url', $url)->value('id'));
    }
}
