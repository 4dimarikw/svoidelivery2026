<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Profile;

use App\MoonShine\Resources\Profile\Pages\ProfileFormPage;
use App\MoonShine\Resources\Profile\Pages\ProfileIndexPage;
use Domain\Profile\Models\Profile;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\SkipMenu;
use MoonShine\Support\Attributes\Icon;

/**
 * Не самостоятельный раздел меню (#[SkipMenu]) — существует только как цель
 * HasOne::make(..., 'profile') в UserFormPage: ModelRelationField требует
 * ModelResource для любой relation-field. Таблица — user_profiles (не
 * profiles), связь строго 1:1 (user_profiles.user_id UNIQUE); профиль
 * редактируется только внутри карточки пользователя.
 *
 * @extends ModelResource<Profile, ProfileIndexPage, ProfileFormPage, null>
 */
#[Icon('identification')]
#[SkipMenu]
class ProfileResource extends ModelResource
{
    protected string $model = Profile::class;

    protected string $column = 'phone';

    protected function pages(): array
    {
        return [
            ProfileIndexPage::class,
            ProfileFormPage::class,
        ];
    }
}
