<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Domain\Product\Models\Product;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;

/**
 * Сохранение Product: создаёт или обновляет запись в таблице products.
 *
 * Логика:
 * Требует $ctx->brand и $ctx->category (иначе skip).
 * Ключ уникальности: ['name', 'brand_id'] — один товар не дублируется при повторном импорте.
 * Заполняет все атрибуты из $ctx->attributes (abv, ibu, plato, ebc, описание и т.д.)
 * и сохраняет модель в $ctx->product. Флаги is_new/is_featured теперь на вариации.
 */
final class PersistProductStage implements ImportStage
{
    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        if ($ctx->brand === null || $ctx->category === null) {
            $ctx->skip = true;

            return $ctx;
        }

        $attrs = $ctx->attributes;

        $alwaysUpdate = [
            'category_id' => $ctx->category->id,
            'beer_style_id' => $ctx->beerStyle?->id,
            'description' => $attrs['description'] ?? null,
            'abv' => $attrs['abv'] ?? null,
            'ibu' => $attrs['ibu'] ?? null,
            'plato' => $attrs['plato'] ?? null,
            'ebc' => $attrs['ebc'] ?? null,
            'untappd_beer_id' => $ctx->untappdBeer?->id,
        ];

        $ctx->product = Product::firstOrCreate(
            ['name' => $attrs['name'], 'vendor_id' => $ctx->brand->id],
            $alwaysUpdate,
        );

//        if (!$ctx->product->wasRecentlyCreated) {
//            $ctx->product->fill($alwaysUpdate);
//            if ($ctx->product->isDirty()) {
//                $ctx->product->save();
//            }
//        }

        return $next($ctx);
    }
}
