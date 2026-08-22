<?php

declare(strict_types=1);

namespace Domain\Untappd\Actions;

use App\Events\UntappdBeerSyncFailed;
use Domain\Untappd\Models\UntappdBeer;
use Services\Untappd\DTOs\BeerResponseDTO;
use Services\Untappd\Facades\Untappd;

/**
 * Дозапрашивает Untappd по beer_id уже существующей записи и перезаписывает её поля —
 * единая точка fetch+persist для одного пива. До появления этого класса та же логика была
 * продублирована дважды: Services\CatalogImport\Stages\ResolveBeerStyleStage::fetchAndStore()
 * (первичный синк при импорте 1С по UntappdRef из CSV) и
 * App\MoonShine\Resources\UntappdBeer\Pages\UntappdBeerFormPage::resync() (кнопка в
 * MoonShine). Оба места сознательно не переведены на этот класс — стабильные существующие
 * пути, трогать их не входило в задачу.
 */
final class SyncUntappdBeerAction
{
    public function __invoke(UntappdBeer $beer): bool
    {
        $result = Untappd::get("beer/info/{$beer->beer_id}", ['db' => 1]);
        $beerDto = $result?->response instanceof BeerResponseDTO ? $result->response->beer : null;

        if ($beerDto === null || $result?->meta?->code !== 200) {
            $detail = $result?->meta?->error_detail ?? 'no beer in response';
            event(new UntappdBeerSyncFailed($beer->beer_id, 'UntappdApiError', $detail));

            return false;
        }

        $beer->fill([
            'name' => $beerDto->beer_name,
            'brewery' => $beerDto->brewery,
            'style' => $beerDto->beer_style,
            'description' => $beerDto->beer_description !== '' ? $beerDto->beer_description : null,
            'rating_count' => $beerDto->rating_count,
            'rating_score' => $beerDto->rating_score,
            // Некоторые записи Untappd не отдают beer_image — тогда падаем на beer_label
            // (см. UntappdBeerFormPage::resync).
            'label' => $beerDto->beer_image ?: $beerDto->beer_label,
            'url' => config('project.untappd_base_url') . '/b/' . $beerDto->beer_slug . '/' . $beerDto->bid,
            'synced_at' => now(),
        ])->save();

//        event(new UntappdBeerSynced(
//            beerId: $beer->beer_id,
//            name: $beer->name,
//            brewery: $beer->brewery,
//            ratingCount: $beer->rating_count,
//            ratingScore: (float) $beer->rating_score,
//        ));

        return true;
    }
}
