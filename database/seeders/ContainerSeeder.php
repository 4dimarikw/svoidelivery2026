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
        'pet_keg' => ['name' => 'ПЭТ-кег', 'label' => 'пэт кег'],
        'pet' => ['name' => 'ПЭТ-бутылка', 'label' => 'пэт'],
        'can' => ['name' => 'Алюминиевая банка', 'label' => 'ж/б'],
        'glass_bottle' => ['name' => 'Стеклянная бутылка', 'label' => 'ст. бут.'],
        'tin_can' => ['name' => 'Консервная банка', 'label' => 'конс./б'],
        'piece' => ['name' => 'Штучный товар', 'label' => ''],
        'pack' => ['name' => 'Пачка', 'label' => 'Пачка'],
        'gas_cylinder' => ['name' => 'Газовый баллон', 'label' => 'газ. баллон'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::CONTAINERS as $code => $row) {
            Container::query()->updateOrCreate(
                ['code' => $code],
                $row,
            );
        }
    }
}
