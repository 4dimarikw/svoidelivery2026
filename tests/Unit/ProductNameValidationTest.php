<?php

namespace Tests\Unit;

use App\MoonShine\Resources\Category\Pages\CategoryFormPage;
use App\MoonShine\Resources\Container\Pages\ContainerFormPage;
use App\MoonShine\Resources\Manufacturer\Pages\ManufacturerFormPage;
use App\MoonShine\Resources\Product\Pages\ProductFormPage;
use App\MoonShine\Resources\Volume\Pages\VolumeFormPage;
use Domain\Catalog\Actions\SyncProductSeoAction;
use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\Container;
use Domain\Catalog\Models\Manufacturer;
use Domain\Catalog\Models\Product;
use Domain\Catalog\Models\Volume;
use Illuminate\Support\Facades\Validator;
use MoonShine\Laravel\TypeCasts\ModelCaster;
use ReflectionMethod;
use Tests\TestCase;

class ProductNameValidationTest extends TestCase
{
    public function test_catalog_forms_enforce_seo_component_limits(): void
    {
        foreach ([
            [ProductFormPage::class, Product::class, 'name', 128],
            [CategoryFormPage::class, Category::class, 'name', 32],
            [ManufacturerFormPage::class, Manufacturer::class, 'name', 40],
            [VolumeFormPage::class, Volume::class, 'label', 16],
            [ContainerFormPage::class, Container::class, 'name', 24],
            [ContainerFormPage::class, Container::class, 'label', 24],
        ] as [$pageClass, $modelClass, $field, $limit]) {
            $page = app($pageClass);
            $item = (new ModelCaster($modelClass))->cast(new $modelClass);
            $rules = (new ReflectionMethod($page, 'rules'))->invoke($page, $item);
            $this->assertTrue(Validator::make([$field => str_repeat('Я', $limit)], [$field => $rules[$field]])->passes(), $pageClass.' '.$field);
            $this->assertTrue(Validator::make([$field => str_repeat('Я', $limit + 1)], [$field => $rules[$field]])->fails(), $pageClass.' '.$field);
        }
    }

    public function test_maximum_component_lengths_fit_seo_title_with_container_fallback(): void
    {
        $product = new Product(['name' => str_repeat('Я', 128)]);
        $product->setRelation('category', new Category(['name' => str_repeat('К', 32)]));
        $product->setRelation('manufacturer', new Manufacturer(['name' => str_repeat('П', 40)]));
        $product->setRelation('volume', new Volume(['label' => str_repeat('О', 16)]));
        $container = new Container(['name' => str_repeat('У', 24), 'label' => str_repeat('Т', 24)]);
        $product->setRelation('container', $container);
        $action = new SyncProductSeoAction;
        $method = new ReflectionMethod($action, 'buildTitle');
        $this->assertSame(246, mb_strlen($method->invoke($action, $product, $product->name)));
        $container->label = null;
        $this->assertSame(246, mb_strlen($method->invoke($action, $product, $product->name)));
    }
}
