<?php

namespace App\Actions\Fortify;

use Domain\Auth\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Проверяет и обновляет данные профиля пользователя.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],

            // Telegram не предоставляет email. Поле обязательно, если у пользователя уже указан адрес: пустая
            // отправка не должна его обнулять. Текущий email служит тем же признаком, что и в
            // User::hasVerifiedEmail().
            'email' => [
                Rule::requiredIf(fn () => $user->email !== null),
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
        ])->validateWithBag('updateProfileInformation');

        $email = $input['email'] ?? null;

        // Пустой ввод означает «не трогать поле», а НЕ «записать null».
        // Иначе верифицированный пользователь мог бы обнулить себе email и
        // получить вечный обход middleware('verified') —
        // см. User::hasVerifiedEmail().
        if ($email === null || $email === '') {
            $user->forceFill(['name' => $input['name']])->save();

            return;
        }

        if ($email !== $user->email && $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser($user, ['name' => $input['name'], 'email' => $email]);
        } else {
            $user->forceFill([
                'name' => $input['name'],
                'email' => $email,
            ])->save();
        }
    }

    /**
     * Обновляет данные пользователя с подтверждённым адресом электронной почты.
     *
     * @param  array<string, string>  $input
     */
    protected function updateVerifiedUser(User $user, array $input): void
    {
        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();
    }
}
