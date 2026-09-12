<?php

namespace Services\CatalogImport\Stages;

use App\Events\UntappdBeerSyncFailed;
use Closure;
use Domain\Catalog\Models\BeerStyle;
use Domain\Untappd\Models\UntappdBeer;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;
use Services\Untappd\DTOs\BeerResponseDTO;
use Services\Untappd\Facades\Untappd;

/**
 * Определяет стиль пива и синхронизирует Untappd по UntappdRef. Запись с заполненным description используется
 * без HTTP; при отсутствии записи или описания выполняется запрос beer/info и сохраняется результат. При ошибке
 * используются доступные данные кеша, затем поле СтильПива из CSV. Непустой стиль сопоставляется через
 * firstOrCreate() по normalized_name; slug генерирует модель. Выполнение продолжается через next().
 */
final class ResolveBeerStyleStage implements ImportStage
{
    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        // Разбор UntappdRef.
        $raw = $ctx->row->get(config('catalog_import.columns.untappd_ref'));
        $untappdRef = $raw !== '' && is_numeric($raw) ? (int) $raw : null;
        $ctx->attributes['untappd_ref'] = $untappdRef;

        // Определение стиля через Untappd с приоритетом кеша.
        $styleName = null;

        if ($untappdRef !== null) {
            $beer = UntappdBeer::query()->where('beer_id', $untappdRef)->first();

            // При отсутствии записи или description запрашиваем API; при ошибке используем найденную запись
            // кеша.
            if (($beer === null || $beer->description === null) && ! $ctx->dryRun) {
                $beer = $this->fetchAndStore($untappdRef, $ctx) ?? $beer;
            }

            if ($beer !== null) {
                $ctx->attributes['untappd_ref'] = $beer->beer_id;
                $ctx->untappdBeer = $beer;
                $styleName = $beer->style;
            }
        }

        // Резервное значение стиля из CSV.
        if (blank($styleName)) {
            $styleName = $ctx->row->get(config('catalog_import.columns.beer_style'));
        }

        // Сопоставление модели BeerStyle.
        if (! blank($styleName)) {
            $normalized = normalize_name($styleName);

            $ctx->beerStyle = BeerStyle::firstOrCreate(
                ['normalized_name' => $normalized],
                ['name' => $styleName],
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
                'description' => $beerDto->beer_description !== '' ? $beerDto->beer_description : null,
                'rating_count' => $beerDto->rating_count,
                'rating_score' => $beerDto->rating_score,
                'label' => $beerDto->beer_image,
                'url' => config('project.untappd_base_url').'/b/'.$beerDto->beer_slug.'/'.$beerDto->bid,
            ],
        );

        // UntappdBeerSynced здесь намеренно не диспатчится — событие на
        // каждый товар с пивом стало бы флудом при полном импорте. Итог
        // прогона агрегирует UntappdBeerSyncBatchCompleted (untappd:sync-beers),
        // точечный синк внутри catalog:import покрывает CatalogImportCompleted.

        return $model;
    }
}
