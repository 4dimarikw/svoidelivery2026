<?php

namespace Database\Seeders;

use Domain\Catalog\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Гарантирует наличие стартовых категорий по slug, не перезаписывая правки администратора. Полный реестр и
 * правила импорта создаются миграциями и редактируются в MoonShine. code получается заменой дефисов slug на
 * подчёркивания.
 */
class CategorySeeder extends Seeder
{
    private const array CATEGORIES = [
        'beer' => 'Пиво',
        'mead' => 'Мёд',
        'cider' => 'Сидр',
        'non-alcoholic' => 'Безалкогольные напитки',
        'sauce' => 'Соус',
        'not-defined' => 'не определена',
        'pet-tare-packages' => 'ПЭТ ТАРА ПАКЕТЫ',
        'for-beer' => 'К пиву',
        'clothes' => 'Одежда',
        'attributes' => 'Атрибутика',
        'souvenirs' => 'Сувениры',
        'probes' => 'Пробники',
        'souvenir-glasses' => 'Сувенирные бокалы',
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $slug => $name) {
            Category::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'code' => str_replace('-', '_', $slug),
                    'name' => $name,
                ],
            );
        }
    }
}
