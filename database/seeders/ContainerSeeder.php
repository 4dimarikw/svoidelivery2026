<?php

namespace Database\Seeders;

use Domain\Catalog\Models\Container;
use Illuminate\Database\Seeder;

class ContainerSeeder extends Seeder
{
    /**
     * From data/catalog-database-structure.md §4.4. Anything the CSV's
     * `Упаковка` can't map to one of these (`штучный товар`, `Упаковка N шт.`)
     * leaves `products.container_id` NULL rather than forcing a guess.
     */
    private const CONTAINERS = [
        'can' => 'Алюминиевая банка',
        'glass_bottle' => 'Стеклянная бутылка',
        'pet_keg' => 'ПЭТ-кег',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::CONTAINERS as $code => $name) {
            Container::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name],
            );
        }
    }
}
