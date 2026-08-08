<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Leeto\Seo\Models\Seo;

class SeoSeeder extends Seeder
{
    /**
     * Только по-настоящему публичные, индексируемые страницы. Товарные
     * строки заводит сама модель (Domain\Catalog\Models\Product::booted()),
     * остальные страницы (корзина/личный кабинет/auth-экраны) уже задают
     * <title> явным :title-пропом на своём layout — им seo-строка не нужна,
     * см. resources/views/components/layouts/app.blade.php.
     */
    private const PAGES = [
        '/' => [
            'title' => 'Каталог пива и напитков с доставкой — Свои Delivery',
            'description' => 'Свои Delivery — доставка крафтового пива и напитков от локальных производителей. Каталог сортов, брендов и объёмов.',
        ],
        '/about' => [
            'title' => 'О нас — Свои Delivery',
            'description' => 'Свои Delivery — доставка крафтового пива и напитков от локальных производителей.',
        ],
    ];

    public function run(): void
    {
        foreach (self::PAGES as $url => $row) {
            Seo::query()->updateOrCreate(['url' => $url], $row);
        }
    }
}
