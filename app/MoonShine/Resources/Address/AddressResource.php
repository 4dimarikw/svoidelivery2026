<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Address;

use App\MoonShine\Resources\Address\Pages\AddressFormPage;
use App\MoonShine\Resources\Address\Pages\AddressIndexPage;
use Domain\Profile\Models\Address;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\SkipMenu;
use MoonShine\Support\Attributes\Icon;

/**
 * Не самостоятельный раздел меню (#[SkipMenu]) — существует только как цель
 * HasMany::make(..., 'addresses') в UserFormPage: ModelRelationField требует
 * ModelResource для любой relation-field. Адреса редактируются только
 * внутри карточки пользователя.
 *
 * @extends ModelResource<Address, AddressIndexPage, AddressFormPage, null>
 */
#[Icon('map-pin')]
#[SkipMenu]
class AddressResource extends ModelResource
{
    protected string $model = Address::class;

    protected bool $withPolicy = true;

    protected string $column = 'city';

    protected bool $createInModal = true;

    protected bool $editInModal = true;

    protected function pages(): array
    {
        return [
            AddressIndexPage::class,
            AddressFormPage::class,
        ];
    }
}
