<?php

namespace App\Actions\Fortify;

use Domain\Auth\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Infrastructure\Rules\HoneypotRule;
use Infrastructure\Rules\SmartCaptchaRule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Проверяет данные и создаёт зарегистрированного пользователя.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
                // Honeypot вешается на email (реальное видимое поле), не на
                // website_url/form_loaded_at — тем некуда рендерить ошибку.
                // См. Infrastructure\Rules\HoneypotRule.
                new HoneypotRule,
            ],
            'password' => $this->passwordRules(),

            // Возраст и принятие условий подтверждаются отдельными флажками. Согласия проверяются при
            // регистрации и не сохраняются в модели пользователя.
            'age_confirmed' => ['accepted'],
            'terms_accepted' => ['accepted'],

            // 'smart-token' — фиксированное имя поля, которое сам виджет
            // Яндекс.SmartCaptcha кладёт в скрытый input внутри
            // <x-ui.smart-captcha>, переименовать нельзя. Пусто/нет на
            // форме, пока config('security.smart_captcha.enabled') выключен —
            // SmartCaptchaRule сам no-op'ает в этом случае.
            'smart-token' => [new SmartCaptchaRule],
        ], [
            // Сообщения приложения находятся в lang/{ru,en}/account.php. Файл validation.php управляется
            // laravel-lang и перезаписывается командой lang:update.
            'age_confirmed.accepted' => __('account.register.age_gate_required'),
            'terms_accepted.accepted' => __('account.register.terms_gate_required'),
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);
    }
}
