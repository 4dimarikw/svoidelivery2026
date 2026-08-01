<?php

namespace Services\CatalogImport\Dto;

use Domain\Catalog\Models\BeerStyle;
use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\PackagingType;
use Domain\Catalog\Models\UntappdBeer;
use Domain\Catalog\Models\Vendor;
use Domain\Catalog\Models\Volume;
use Domain\Product\Models\Product;
use Domain\Product\Models\ProductVariation;
use Services\CatalogImport\LookupCache;


class ImportContext
{
    public ?Vendor $brand = null;

    public ?Category $category = null;

    public ?BeerStyle $beerStyle = null;

    public ?PackagingType $containerType = null;

    public ?Volume $volume = null;

    public ?Product $product = null;

    public ?ProductVariation $variation = null;

    public ?UntappdBeer $untappdBeer = null;

    /** @var array<string, mixed> */
    public array $attributes = [];

    /** @var list<array{stage: string, message: string, value: string}> */
    public array $warnings = [];

    public bool $skip = false;

    public int $barcodesCreatedThisRow = 0;

    public bool $imageAttachedThisRow = false;

    public function __construct(
        public readonly RawRow       $row,
        public readonly ?LookupCache $lookups = null,
        public readonly bool         $dryRun = false,
        /** @var list<string> Slug-фильтр категорий; пустой массив = без фильтра. */
        public readonly array        $categoryFilter = [],
    )
    {
    }

    public function addWarning(string $stage, string $message, string $value = ''): void
    {
        $this->warnings[] = ['stage' => $stage, 'message' => $message, 'value' => $value];
    }
}
