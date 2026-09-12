<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Domain\Catalog\Models\BeerProductDetail;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;

/**
 * Сохраняет пивные атрибуты в beer_product_details с ключом product_id и связью 1:1 с products. Требует
 * ctx->product; при отсутствии всех пивных атрибутов строка не создаётся. firstOrCreate() сохраняет ручные
 * правки существующих атрибутов при повторном импорте.
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
