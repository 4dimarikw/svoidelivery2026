<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\UntappdBeer\Pages;

use App\MoonShine\Resources\UntappdBeer\UntappdBeerResource;
use Domain\Untappd\Models\UntappdBeer;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Crud\Components\Fragment;
use MoonShine\Crud\JsonResponse;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\Support\Enums\ToastType;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\When;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;
use Services\Untappd\Facades\Untappd;

/**
 * @extends FormPage<UntappdBeerResource, UntappdBeer>
 */
final class UntappdBeerFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Box::make([
                When::make(
                    fn () => (bool) $this->getItem()?->beer_id,
                    fn () => [
                        ActionButton::make(__('moonshine.untappd_beer.actions.resync'))
                            ->primary()
                            ->method(
                                'resync',
                                params: ['itemId' => $this->getItem()?->getKey()],
                                events: [AlpineJs::event(JsEvent::FRAGMENT_UPDATED, 'untappd-beer')]
                            ),
                    ],
                ),

                Fragment::make([
                    ID::make(),

                    Number::make(__('moonshine.untappd_beer.fields.beer_id'), 'beer_id')
                        ->required()
                        ->hint('Ключ Untappd, уникален. Меняется вручную только для перепривязки к другому пиву на Untappd.'),

                    Text::make(__('moonshine.untappd_beer.fields.name'), 'name'),
                    Text::make(__('moonshine.untappd_beer.fields.brewery'), 'brewery'),
                    Text::make(__('moonshine.untappd_beer.fields.style'), 'style'),
                    Textarea::make(__('moonshine.untappd_beer.fields.description'), 'description'),
                    Number::make(__('moonshine.untappd_beer.fields.rating_count'), 'rating_count'),
                    Number::make(__('moonshine.untappd_beer.fields.rating_score'), 'rating_score')->step(0.01),
                    Text::make(__('moonshine.untappd_beer.fields.label'), 'label')
                        ->hint('URL картинки с Untappd'),
                    Text::make(__('moonshine.untappd_beer.fields.url'), 'url'),
                    Date::make(__('moonshine.untappd_beer.fields.synced_at'), 'synced_at')->withTime()->readonly(),
                ])->name('untappd-beer'),
            ]),
        ];
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [
            'beer_id' => [
                'required', 'integer',
                Rule::unique('untappd_beers', 'beer_id')->ignore($item->getKey()),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'brewery' => ['nullable', 'string', 'max:255'],
            'style' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'rating_count' => ['required', 'integer', 'min:0'],
            // column is decimal(3,2) -> max 9.99, Untappd's rating is 0-5.
            'rating_score' => ['nullable', 'numeric', 'between:0,9.99'],
            'label' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:2048'],
        ];
    }

    #[AsyncMethod]
    public function resync(CrudRequestContract $request)
    {
        $beer = UntappdBeer::findOrFail(request()->input('itemId'));

        $apiResponse = Untappd::get("beer/info/{$beer->beer_id}");

        if (blank($apiResponse) || $apiResponse->meta->code !== 200 || blank($apiResponse->response->beer)) {
            return JsonResponse::make()->toast(__('moonshine.untappd_beer.toasts.resync_failed'), ToastType::WARNING);
        }

        $beerData = $apiResponse->response->beer;

        $beer->name = $beerData->beer_name;
        $beer->brewery = $beerData->brewery;
        $beer->style = $beerData->beer_style;
        $beer->description = $beerData->beer_description;
        $beer->rating_count = $beerData->rating_count;
        $beer->rating_score = $beerData->rating_score;
        $beer->label = $beerData->beer_image ?: $beerData->beer_label;
        $beer->synced_at = now();

        $beer->save();

        return JsonResponse::make()->toast(__('moonshine.untappd_beer.toasts.resync_success'), ToastType::SUCCESS);
    }
}
