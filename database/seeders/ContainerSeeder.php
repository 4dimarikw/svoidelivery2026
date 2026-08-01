<?php

namespace Database\Seeders;

use Domain\Catalog\Models\Container;
use Illuminate\Database\Seeder;

class ContainerSeeder extends Seeder
{
    /**
     * From data/catalog-database-structure.md §4.4, one code per
     * `config('catalog_import.container_map')` value — ResolveContainerStage
     * warns "container code not seeded" for any code missing here.
     */
    private const CONTAINERS = [
        'pet_keg' => 'ПЭТ-кег',
        'pet' => 'ПЭТ-бутылка',
        'can' => 'Алюминиевая банка',
        'glass_bottle' => 'Стеклянная бутылка',
        'tin_can' => 'Консервная банка',
        'piece' => 'Штучный товар',
        'pack' => 'Пачка',
        'gas_cylinder' => 'Газовый баллон',
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
