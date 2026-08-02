<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Profile\Pages;

use App\MoonShine\Resources\Profile\ProfileResource;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;

/**
 * Отрисовывается как таблица HasOne-виджета внутри карточки пользователя
 * (см. ProfileResource) — самостоятельного списка/URL у ресурса нет.
 *
 * @extends IndexPage<ProfileResource>
 */
final class ProfileIndexPage extends IndexPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make(),
            Text::make(__('moonshine.profile.fields.full_name'), 'full_name'),
            Text::make(__('moonshine.profile.fields.phone'), 'phone'),
            Text::make(__('moonshine.profile.fields.telegram_url'), 'telegram_url'),
            Text::make(__('moonshine.profile.fields.vk_url'), 'vk_url'),
        ];
    }
}
