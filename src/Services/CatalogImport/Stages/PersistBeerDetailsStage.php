<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Domain\Catalog\Models\BeerProductDetail;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;

/**
 * Сохранение пивных атрибутов товара в beer_product_details (1:1 с products,
 * PK = product_id — см. BeerProductDetail::$primaryKey).
 *
 * Логика:
 * Требует $ctx->product. Если ни один из abv/ibu/plato/ebc/beerStyle/untappdBeer
 * не задан — стейдж пропускается: не создаёт пустую строку под товары без
 * пивных атрибутов (сопутствующие товары, атрибутика и т.д.).
 * Create-only (firstOrCreate): эти поля теперь редактируются из админки
 * (ProductResource, вкладка «Пиво») — повторный импорт не должен затирать
 * ручную правку abv/ibu/plato/ebc/стиля у уже существующей строки.
 */
final class PersistBeerDetailsStage implements ImportStage
{
    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        if ($ctx->product === null) {
            return $next($ctx);
        }

        $attrs = $ctx->attributes;

        $data = [
            'beer_style_id' => $ctx->beerStyle?->id,
            'untappd_beer_id' => $ctx->untappdBeer?->id,
            'abv' => $attrs['abv'] ?? null,
            'ibu' => $attrs['ibu'] ?? null,
            'plato' => $attrs['plato'] ?? null,
            'ebc' => $attrs['ebc'] ?? null,
        ];

        if (array_filter($data, static fn ($value) => $value !== null) === []) {
            return $next($ctx);
        }

        BeerProductDetail::firstOrCreate(['product_id' => $ctx->product->id], $data);

        return $next($ctx);
    }
}
