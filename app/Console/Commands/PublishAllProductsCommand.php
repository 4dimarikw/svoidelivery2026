<?php

namespace App\Console\Commands;

use Domain\Catalog\Enums\ProductStatus;
use Domain\Catalog\Models\Product;
use Illuminate\Console\Command;

class PublishAllProductsCommand extends Command
{
    protected $signature = 'catalog:publish-all';

    protected $description = 'Установить статус "опубликован" всем товарам';

    public function handle(): int
    {
        $count = Product::query()->update(['status' => ProductStatus::PUBLISHED]);

        $this->info("Обновлено товаров: {$count}");

        return self::SUCCESS;
    }
}
