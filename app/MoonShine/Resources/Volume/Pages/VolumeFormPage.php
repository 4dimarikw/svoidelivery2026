<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Volume\Pages;

use App\MoonShine\Resources\Volume\VolumeResource;
use Domain\Catalog\Models\Volume;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;

/**
 * @extends FormPage<VolumeResource, Volume>
 */
final class VolumeFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Box::make([
                ID::make(),

                Number::make(__('moonshine.volume.fields.milliliters'), 'milliliters')
                    ->min(1)
                    ->step(1)
                    ->required()
                    ->hint('Целое число миллилитров, уникально: 330, 500, 20000'),

                Text::make(__('moonshine.volume.fields.label'), 'label')
                    ->required()
                    ->hint('Как показывать покупателю: «0.33 л», «20 л»'),
            ]),
        ];
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [
            'milliliters' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('volumes', 'milliliters')->ignore($item->getKey()),
            ],
            'label' => ['required', 'string', 'max:32'],
        ];
    }
}
