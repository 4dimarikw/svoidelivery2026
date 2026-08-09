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
    private const array CONTAINERS = [
        'pet_keg' => ['name' => 'ПЭТ-кег', 'label' => 'пэт кег', 'is_active' => false],
        'pet' => ['name' => 'ПЭТ-бутылка', 'label' => 'пэт', 'is_active' => false],
        'can' => ['name' => 'Жестяная банка', 'label' => 'ж/б', 'is_active' => true],
        'glass_bottle' => ['name' => 'Стеклянная бутылка', 'label' => 'ст. бут.', 'is_active' => true],
        'tin_can' => ['name' => 'Консервная банка', 'label' => 'конс./б', 'is_active' => true],
        'piece' => ['name' => 'Штучный товар', 'label' => '', 'is_active' => true],
        'pack' => ['name' => 'Пачка', 'label' => 'Пачка', 'is_active' => true],
        'gas_cylinder' => ['name' => 'Газовый баллон', 'label' => 'газ. баллон', 'is_active' => false],
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
