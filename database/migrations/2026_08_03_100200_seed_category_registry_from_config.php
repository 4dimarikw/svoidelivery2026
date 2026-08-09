<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One-time transplant of the `categories` + `category_resolution` arrays
     * that used to live in config/catalog_import.php (removed in the same
     * deploy as this migration — see git history for the array this snapshot
     * was taken from) into the categories/category_match_rules tables.
     *
     * The snapshot is inlined here, not read from config(), because the
     * config key won't exist anymore by the time this migration runs on a
     * fresh install. `rules` preserves the original array's declaration
     * order — that order was load-bearing (it was the match priority) and
     * is now made explicit via the `priority` column (10, 20, 30, ... in
     * encounter order, flattened across all categories).
     */
    private const array CATEGORIES = [
        'beer' => [
            'name' => 'Пиво',
            'expects_container' => true,
            'expects_volume' => true,
            'rules' => [
                ['type' => 'alcohol', 'match_when' => 'default', 'value' => null],
            ],
        ],
        'mead' => [
            'name' => 'Мёд',
            'expects_container' => true,
            'expects_volume' => true,
            'rules' => [
                ['type' => 'alcohol', 'match_when' => 'style', 'value' => 'mead'],
            ],
        ],
        'cider' => [
            'name' => 'Сидр',
            'expects_container' => true,
            'expects_volume' => true,
            'rules' => [
                ['type' => 'alcohol', 'match_when' => 'style', 'value' => 'cider'],
            ],
        ],
        'non-alcoholic' => [
            'name' => 'Безалкогольные напитки',
            'expects_container' => true,
            'expects_volume' => true,
            'rules' => [
                ['type' => 'alcohol', 'match_when' => 'no_abv', 'value' => null],
            ],
        ],
        'sauce' => [
            'name' => 'Соус',
            'expects_container' => true,
            'expects_volume' => true,
            'rules' => [
                ['type' => 'alcohol', 'match_when' => 'style', 'value' => 'sauce'],
            ],
        ],
        'not-defined' => [
            'name' => 'не определена',
            'rules' => [],
        ],
        'pet-tare-packages' => [
            'name' => 'ПЭТ ТАРА ПАКЕТЫ',
            'expects_container' => true,
            'expects_volume' => true,
            'container_code' => 'pet',
            'rules' => [
                ['type' => 'accessory_title', 'match_when' => null, 'value' => 'пэт'],
                ['type' => 'accessory_title', 'match_when' => null, 'value' => 'тара'],
                ['type' => 'accessory_title', 'match_when' => null, 'value' => 'пакет'],
            ],
        ],
        'for-beer' => [
            'name' => 'К пиву',
            'rules' => [
                ['type' => 'accessory_title', 'match_when' => null, 'value' => 'арахис'],
                ['type' => 'accessory_title', 'match_when' => null, 'value' => 'снэки'],
                ['type' => 'accessory_title', 'match_when' => null, 'value' => 'чипсы'],
            ],
        ],
        'clothes' => [
            'name' => 'Одежда',
            'rules' => [
                ['type' => 'accessory_title', 'match_when' => null, 'value' => 'футболка'],
                ['type' => 'accessory_title', 'match_when' => null, 'value' => 'толстовка'],
                ['type' => 'accessory_title', 'match_when' => null, 'value' => 'шапка'],
            ],
        ],
        'attributes' => [
            'name' => 'Атрибутика',
            'rules' => [
                ['type' => 'accessory_title', 'match_when' => null, 'value' => 'шеврон'],
                ['type' => 'accessory_title', 'match_when' => null, 'value' => 'маска'],
                ['type' => 'accessory_title', 'match_when' => null, 'value' => 'флаг'],
                ['type' => 'accessory_title', 'match_when' => null, 'value' => 'атрибутика'],
                ['type' => 'accessory_title', 'match_when' => null, 'value' => 'коврик'],
            ],
        ],
        'souvenirs' => [
            'name' => 'Сувениры',
            'rules' => [
                ['type' => 'alcohol', 'match_when' => 'advent', 'value' => null],
                ['type' => 'accessory_title', 'match_when' => null, 'value' => 'адвент'],
            ],
        ],
        'probes' => [
            'name' => 'Пробники',
            'expects_container' => true,
            'expects_volume' => true,
            'rules' => [
                ['type' => 'contains', 'match_when' => null, 'value' => 'пробники'],
            ],
        ],
        'souvenir-glasses' => [
            'name' => 'Сувенирные бокалы',
            'rules' => [
                ['type' => 'contains', 'match_when' => null, 'value' => 'сувенирные бокалы'],
            ],
        ],
    ];

    public function up(): void
    {
        $now = now();
        $priority = 0;

        foreach (self::CATEGORIES as $slug => $config) {
            $categoryId = DB::table('categories')->where('slug', $slug)->value('id');

            if ($categoryId === null) {
                // CategorySeeder already ran (via DatabaseSeeder/migrate --seed)
                // and created the row with the old flat name/code — just fill
                // in the new flag columns rather than skip it.
                $categoryId = DB::table('categories')->insertGetId([
                    'slug' => $slug,
                    'code' => str_replace('-', '_', $slug),
                    'name' => $config['name'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('categories')->where('id', $categoryId)->update([
                'expects_container' => $config['expects_container'] ?? false,
                'expects_volume' => $config['expects_volume'] ?? false,
                'price_exempt' => $config['price_exempt'] ?? false,
                'name_from_article' => $config['name_from_article'] ?? false,
                'default_brand' => $config['default_brand'] ?? null,
                'container_code' => $config['container_code'] ?? null,
            ]);

            // Idempotent: skip rule creation if this category already has
            // rules (re-running this migration, or an admin already edited
            // the registry, must not duplicate/reset them).
            $hasRules = DB::table('category_match_rules')->where('category_id', $categoryId)->exists();

            if ($hasRules) {
                $priority += count($config['rules']) * 10;

                continue;
            }

            foreach ($config['rules'] as $rule) {
                $priority += 10;

                DB::table('category_match_rules')->insert([
                    'category_id' => $categoryId,
                    'type' => $rule['type'],
                    'match_when' => $rule['match_when'],
                    'value' => $rule['value'],
                    'priority' => $priority,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $slugs = array_keys(self::CATEGORIES);

        $categoryIds = DB::table('categories')->whereIn('slug', $slugs)->pluck('id');

        DB::table('category_match_rules')->whereIn('category_id', $categoryIds)->delete();

        DB::table('categories')->whereIn('id', $categoryIds)->update([
            'expects_container' => false,
            'expects_volume' => false,
            'price_exempt' => false,
            'name_from_article' => false,
            'default_brand' => null,
            'container_code' => null,
        ]);
    }
};
