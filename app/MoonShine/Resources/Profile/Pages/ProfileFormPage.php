<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Profile\Pages;

use App\MoonShine\Resources\Profile\ProfileResource;
use Domain\Profile\Models\Profile;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * @extends FormPage<ProfileResource, Profile>
 */
final class ProfileFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Box::make([
                ID::make(),

                Text::make(__('moonshine.profile.fields.last_name'), 'last_name'),
                Text::make(__('moonshine.profile.fields.first_name'), 'first_name'),
                Text::make(__('moonshine.profile.fields.patronymic'), 'patronymic'),
                Text::make(__('moonshine.profile.fields.phone'), 'phone'),
                Text::make(__('moonshine.profile.fields.vk_url'), 'vk_url'),
                Text::make(__('moonshine.profile.fields.telegram_url'), 'telegram_url'),
                Textarea::make(__('moonshine.profile.fields.default_order_comment'), 'default_order_comment'),
            ]),
        ];
    }

    // Rules mirror App\Http\Controllers\Account\ProfileController::update()
    // — same fields, same constraints, both entry points into the same table.
    protected function rules(DataWrapperContract $item): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'patronymic' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'vk_url' => ['nullable', 'url', 'max:255'],
            'telegram_url' => ['nullable', 'url', 'max:255'],
            'default_order_comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
