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
     * Validate and update the given user's profile information.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],

            // У аккаунта, созданного через Telegram, email может
            // отсутствовать — Telegram его не отдаёт. Обязателен только для
            // тех, у кого он уже есть (обычная регистрация) — так нельзя
            // обнулить существующий email пустой отправкой, но и нечего
            // требовать у того, у кого email не было изначально. Проверяем
            // по текущему email, не по отдельному запросу к telegraph_chats —
            // тот же структурный сигнал, что и в User::hasVerifiedEmail().
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
     * Update the given verified user's profile information.
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
