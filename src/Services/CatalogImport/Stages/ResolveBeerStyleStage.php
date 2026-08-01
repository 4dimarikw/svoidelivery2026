<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Domain\Catalog\Models\BeerStyle;
use Domain\Untappd\Models\UntappdBeer;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;
use Services\CatalogImport\UniqueSlugResolver;
use Services\Untappd\DTOs\BeerResponseDTO;
use Services\Untappd\Facades\Untappd;
use Support\Logging\Events\UntappdBeerSynced;
use Support\Logging\Events\UntappdBeerSyncFailed;

/**
 * Резолвит стиль пива и синкает Untappd-данные при наличии UntappdRef.
 *
 * Алгоритм:
 * 1. Парсит `UntappdRef` → $ctx->attributes['untappd_ref'] (int|null).
 * 2. Если UntappdRef задан:
 *    a. Cache-first: ищем в untappd_beers по beer_id. Если найдено — берём стиль без HTTP.
 *    b. Cache miss → `Untappd::get("beer/info/$beerId", ['db' => 1])`:
 *       - Успех → upsert untappd_beers, диспатч UntappdBeerSynced; стиль = $beer->beer_style;
 *         $ctx->attributes['untappd_description'] — beer_description из ответа API (не
 *         персистится в untappd_beers, доступен только на свежем запросе, не на cache-hit).
 *       - Ошибка → warning в $ctx, диспатч UntappdBeerSyncFailed; переходим к fallback.
 * 3. Fallback: если стиль пуст → берём `СтильПива` из CSV.
 * 4. Если итоговый стиль непуст → BeerStyle::firstOrCreate по normalized_name
 *    (NOT NULL unique, моделью не генерируется); name/slug заполняются явно.
 * 5. Всегда вызывает $next($ctx).
 */
final class ResolveBeerStyleStage implements ImportStage
{
    public function __construct(private readonly UniqueSlugResolver $slugs = new UniqueSlugResolver) {}

    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        // Step 1: parse UntappdRef
        $raw = $ctx->row->get(config('catalog_import.columns.untappd_ref'));
        $untappdRef = $raw !== '' && is_numeric($raw) ? (int) $raw : null;
        $ctx->attributes['untappd_ref'] = $untappdRef;

        // Step 2: Untappd-first style resolution (cache-first)
        $styleName = null;

        if ($untappdRef !== null) {
            $beer = UntappdBeer::query()->where('beer_id', $untappdRef)->first();

            if ($beer === null && ! $ctx->dryRun) {
                $beer = $this->fetchAndStore($untappdRef, $ctx);
            }

            if ($beer !== null) {
                $ctx->attributes['untappd_ref'] = $beer->beer_id;
                $ctx->untappdBeer = $beer;
                $styleName = $beer->style;
            }
        }

        // Step 3: fallback to CSV column
        if (blank($styleName)) {
            $styleName = $ctx->row->get(config('catalog_import.columns.beer_style'));
        }

        // Step 4: resolve BeerStyle model
        if (! blank($styleName)) {
            $normalized = $this->normalize($styleName);

            $ctx->beerStyle = BeerStyle::firstOrCreate(
                ['normalized_name' => $normalized],
                ['name' => $styleName, 'slug' => $this->slugs->resolve(BeerStyle::class, $styleName)],
            );
        }

        return $next($ctx);
    }

    private function fetchAndStore(int $beerId, ImportContext $ctx): ?UntappdBeer
    {
        $result = Untappd::get("beer/info/{$beerId}", ['db' => 1]);
        $beerDto = $result?->response instanceof BeerResponseDTO ? $result->response->beer : null;

        if ($beerDto === null || $result?->meta?->code !== 200) {
            $detail = $result?->meta?->error_detail ?? 'no beer in response';
            $ctx->addWarning(self::class, 'untappd sync failed: '.$detail, (string) $beerId);
            event(new UntappdBeerSyncFailed($beerId, 'UntappdApiError', $detail));

            return null;
        }

        $model = UntappdBeer::updateOrCreate(
            ['beer_id' => $beerDto->bid],
            [
                'name' => $beerDto->beer_name,
                'brewery' => $beerDto->brewery,
                'style' => $beerDto->beer_style,
                'rating_count' => $beerDto->rating_count,
                'rating_score' => $beerDto->rating_score,
                'label' => $beerDto->beer_image,
                'url' => '/b/'.$beerDto->beer_slug.'/'.$beerDto->bid,
            ],
        );

        // Not persisted on untappd_beers — only available here, at the API call itself.
        $ctx->attributes['untappd_description'] = $beerDto->beer_description !== '' ? $beerDto->beer_description : null;

        event(new UntappdBeerSynced(
            beerId: $model->beer_id,
            name: $model->name,
            brewery: $model->brewery,
            ratingCount: $model->rating_count,
            ratingScore: (float) $model->rating_score,
        ));

        return $model;
    }

    private function normalize(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_strtolower($name)));
    }
}
