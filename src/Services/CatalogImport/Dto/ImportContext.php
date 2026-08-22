<?php

namespace Services\CatalogImport\Dto;

use Domain\Catalog\Models\BeerStyle;
use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\Container;
use Domain\Catalog\Models\Manufacturer;
use Domain\Catalog\Models\Product;
use Domain\Catalog\Models\Volume;
use Domain\Untappd\Models\UntappdBeer;
use Services\CatalogImport\LookupCache;

class ImportContext
{
    public ?Manufacturer $brand = null;

    public ?Category $category = null;

    public ?BeerStyle $beerStyle = null;

    public ?Container $container = null;

    public ?Volume $volume = null;

    public ?Product $product = null;

    public ?UntappdBeer $untappdBeer = null;

    /** @var array<string, mixed> */
    public array $attributes = [];

    /** @var list<array{stage: string, message: string, value: string}> */
    public array $warnings = [];

    public bool $skip = false;

    /**
     * Класс стадии, установившей $skip = true — используется для разбивки
     * "по стадии" в CatalogImportRowsSkipped. Не заполняется, если стадия
     * решила пропустить строку без addWarning() (см. FilterCategoryStage —
     * это намеренная фильтрация, а не дефект данных).
     */
    public ?string $skipStage = null;

    public bool $priceCoerced = false;

    public bool $stockCoerced = false;

    public bool $imageAttachedThisRow = false;

    public function __construct(
        public readonly RawRow $row,
        public readonly ?LookupCache $lookups = null,
        public readonly bool $dryRun = false,
        /** @var list<string> Slug-фильтр категорий; пустой массив = без фильтра. */
        public readonly array $categoryFilter = [],
    ) {}

    public function addWarning(string $stage, string $message, string $value = ''): void
    {
        $this->warnings[] = ['stage' => $stage, 'message' => $message, 'value' => $value];
    }
}
