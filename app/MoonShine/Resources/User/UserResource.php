<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\User;

use App\MoonShine\Resources\User\Pages\UserFormPage;
use App\MoonShine\Resources\User\Pages\UserIndexPage;
use Domain\Auth\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;

/**
 * Delete/MassDelete отключены: user_profiles.user_id и addresses.user_id
 * оба cascadeOnDelete, ручное удаление пользователя из админки молча снесло
 * бы его профиль и все адреса без предупреждения.
 *
 * @extends ModelResource<User, UserIndexPage, UserFormPage, null>
 */
#[Icon('user-group')]
#[Group('moonshine.group.users', 'users', translatable: true)]
#[Order(0)]
class UserResource extends ModelResource
{
    protected string $model = User::class;

    protected bool $withPolicy = true;

    protected string $column = 'name';

    protected array $with = ['profile', 'addresses'];

    public function getTitle(): string
    {
        return __('moonshine.user.title');
    }

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->except(Action::DELETE, Action::MASS_DELETE);
    }

    protected function pages(): array
    {
        return [
            UserIndexPage::class,
            UserFormPage::class,
        ];
    }

    protected function search(): array
    {
        return ['id', 'name', 'email'];
    }

    protected function modifyQueryBuilder(Builder $builder): Builder
    {
        return $builder->withCount(['addresses', 'favorites']);
    }
}
