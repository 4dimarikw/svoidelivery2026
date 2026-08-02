<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\User\Pages;

use App\MoonShine\Resources\Address\AddressResource;
use App\MoonShine\Resources\Profile\ProfileResource;
use App\MoonShine\Resources\User\UserResource;
use Domain\Auth\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use MoonShine\Laravel\Fields\Relationships\HasOne;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Collapse;
use MoonShine\UI\Components\Tabs;
use MoonShine\UI\Components\Tabs\Tab;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Email;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Password;
use MoonShine\UI\Fields\PasswordRepeat;
use MoonShine\UI\Fields\Text;

/**
 * @extends FormPage<UserResource, User>
 */
final class UserFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make(),

            Text::make(__('moonshine.user.fields.name'), 'name')->required(),

            Email::make(__('moonshine.user.fields.email'), 'email')->required(),

            Date::make(__('moonshine.user.fields.email_verified_at'), 'email_verified_at')
                ->withTime()
                ->nullable()
                ->hint('Подтверждение почты в проекте отключено (MAIL_MAILER=log) — это ручная отметка, а не результат письма-подтверждения.'),

            Collapse::make(__('moonshine.user.fields.change_password'), [
                Password::make(__('moonshine.user.fields.password'), 'password')
                    ->customAttributes(['autocomplete' => 'new-password'])
                    ->eye(),

                PasswordRepeat::make(__('moonshine.user.fields.password_confirmation'), 'password_confirmation')
                    ->customAttributes(['autocomplete' => 'confirm-password'])
                    ->eye(),
            ]),
        ];
    }

    private function getProfileField(): HasOne
    {
        return HasOne::make(__('moonshine.user.fields.profile'), 'profile', resource: ProfileResource::class)
            ->fillData($this->getResource()->getItem())
            ->async();
    }

    private function getAddressesField(): HasMany
    {
        return HasMany::make(__('moonshine.user.fields.addresses'), 'addresses', resource: AddressResource::class)
            ->fillData($this->getResource()->getItem())
            ->async()
            ->creatable();
    }

    protected function mainLayer(): array
    {
        return [
            Tabs::make([
                Tab::make(__('moonshine.user.tabs.main'), parent::mainLayer())->icon('user-circle'),
                Tab::make(__('moonshine.user.tabs.profile'), [
                    $this->getResource()->getItem() ? $this->getProfileField() : 'У пользователя отсутствует профиль',
                ])->icon('user-circle'),
                Tab::make('Comment', [
                    $this->getResource()->getItem() ? $this->getAddressesField() : 'To add comments, save the article',
                ])->icon('map-pin'),
            ]),
        ];
    }


    protected function rules(DataWrapperContract $item): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'bail',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($item->getKey()),
            ],
            'email_verified_at' => ['nullable', 'date'],
            // password: 'hashed' cast on the model hashes it automatically —
            // never call Hash::make() here.
            'password' => [
                ...$item->getKey() !== null ? ['sometimes', 'nullable'] : ['required'],
                PasswordRule::defaults(),
                'confirmed',
            ],
        ];
    }
}
