<?php

namespace Tests\Feature\Domain;

use Domain\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductDescriptionPurificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dangerous_tags_are_stripped_on_write(): void
    {
        $product = Product::factory()->create([
            'description' => 'Текст <script>alert(1)</script> с описанием',
        ]);

        $this->assertStringNotContainsString('<script', $product->description);
        $this->assertStringNotContainsString('alert(1)', $product->description);
        $this->assertStringContainsString('Текст', $product->description);
    }

    public function test_allowed_formatting_tags_survive(): void
    {
        $product = Product::factory()->create([
            'description' => 'Светлое <b>нефильтрованное</b> пиво<br>с горчинкой',
        ]);

        $this->assertStringContainsString('<b>нефильтрованное</b>', $product->description);
        $this->assertStringContainsString('<br', $product->description);
    }

    public function test_entity_encoded_tags_are_decoded_before_cleaning(): void
    {
        // 1С отдаёт description entity-энкодленным — "&lt;b&gt;" должен стать
        // настоящим <b>, а не остаться текстом "&lt;b&gt;" на витрине.
        $product = Product::factory()->create([
            'description' => '&lt;b&gt;bold&lt;/b&gt; text',
        ]);

        $this->assertStringContainsString('<b>bold</b>', $product->description);
    }

    public function test_disallowed_attributes_are_neutralized(): void
    {
        $product = Product::factory()->create([
            'description' => '<img src=x onerror=alert(1)><a href="javascript:alert(1)">click</a>',
        ]);

        $this->assertStringNotContainsString('<img', $product->description);
        $this->assertStringNotContainsString('onerror', $product->description);
        $this->assertStringNotContainsString('javascript:', $product->description);
    }

    public function test_data_written_before_the_cast_self_heals_on_read(): void
    {
        // Обходим каст (прямой insert в БД) — имитирует одно из уже
        // сохранённых до PurifiedHtml описаний (все 888 текущих товаров).
        $product = Product::factory()->create();

        DB::table('products')->where('id', $product->id)->update([
            'description' => 'Грязный <script>alert(1)</script> текст',
        ]);

        $fresh = Product::query()->find($product->id);

        $this->assertStringNotContainsString('<script', $fresh->description);
        $this->assertStringContainsString('Грязный', $fresh->description);
    }

    public function test_product_page_renders_description_as_real_html(): void
    {
        $product = Product::factory()->create([
            'description' => 'Текст <b>жирным</b>',
        ]);

        $response = $this->get(route('product.show', $product));

        $response->assertOk();
        $response->assertSee('<b>жирным</b>', false);
        $response->assertDontSee('&lt;b&gt;');
    }
}
