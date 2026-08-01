<?php

// App-owned auth/account copy (login, register, password reset). Not in
// auth.php/passwords.php/validation.php — those are managed by
// laravel-lang/common and get overwritten by `composer post-update-cmd` →
// `artisan lang:update`. Not in ui.php either — that's reserved for
// <x-ui.*> component-internal strings, not page copy.

return [
    'field' => [
        'name' => 'Имя',
        'email' => 'E-mail',
        'password' => 'Пароль',
        'password_confirmation' => 'Повторите пароль',
        'new_password' => 'Новый пароль',
    ],

    'login' => [
        'title' => 'С возвращением',
        'subtitle' => 'Войдите, чтобы заказывать как свои',
        'remember' => 'Запомнить меня',
        'forgot' => 'Забыли пароль?',
        'submit' => 'Войти',
        'no_account' => 'Впервые у нас?',
        'create' => 'Создать аккаунт',
    ],

    'register' => [
        'title' => 'Стать своим',
        'subtitle' => 'Минута — и доставка уже ваша',
        'age_gate' => 'Мне есть 18 лет',
        'age_gate_required' => 'Подтвердите, что вам есть 18 лет.',
        'terms_gate' => 'Принимаю условия сервиса',
        'terms_gate_required' => 'Нужно принять условия сервиса.',
        'password_hint' => 'Минимум 8 символов',
        'submit' => 'Создать аккаунт',
        'have_account' => 'Уже с нами?',
        'login' => 'Войти',
    ],

    'forgot' => [
        'title' => 'Восстановление пароля',
        'subtitle' => 'Пришлём ссылку на почту',
        'submit' => 'Отправить ссылку',
        'back' => 'Вернуться ко входу',
    ],

    'reset' => [
        'title' => 'Новый пароль',
        'subtitle' => 'Придумайте новый пароль для входа',
        'submit' => 'Сохранить пароль',
    ],

    'confirm' => [
        'title' => 'Подтвердите пароль',
        'subtitle' => 'Это защищённая область — подтвердите пароль перед продолжением',
        'submit' => 'Подтвердить',
    ],
];
