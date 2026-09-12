<?php

use App\Http\Middleware\ThrottleAuthForms;
use Laravel\Fortify\Features;

return [

    /*
     * Механизм аутентификации Fortify из config/auth.php.
     */

    'guard' => 'web',

    /*
     * Обработчик сброса пароля Fortify из config/auth.php.
     */

    'passwords' => 'users',

    /*
     * Атрибут имени пользователя и название поля электронной почты для сброса пароля.
     */

    'username' => 'email',

    'email' => 'email',

    /*
     * Приведение имени пользователя к нижнему регистру перед сохранением.
     */

    'lowercase_usernames' => true,

    /*
     * Путь перенаправления авторизованного пользователя после успешного входа.
     */

    'home' => '/account/profile',

    /*
     * Префикс и домен маршрутов Fortify.
     */

    'prefix' => '',

    'domain' => null,

    /*
     * Промежуточные обработчики маршрутов Fortify.
     */

    // ThrottleAuthForms троттлит register.store/password.email/password.update
    // именованными лимитерами — Fortify не даёт троттлить эти три маршрута
    // штатно (см. FortifyServiceProvider::boot()). Подключено здесь, а не в
    // bootstrap/app.php: это единственное место, где Fortify реально читает
    // этот ключ (vendor/laravel/fortify/routes/routes.php:27) — middleware
    // отрабатывает ровно на маршрутах Fortify, ни на одной публичной
    // странице каталога/корзины оно не висит.
    'middleware' => ['web', ThrottleAuthForms::class],

    /*
     * Именованные ограничители частоты запросов Fortify.
     */

    'limiters' => [
        'login' => 'login',
        'two-factor' => 'two-factor',
        'passkeys' => 'passkeys',
        // Единственный из наших четырёх лимитеров, который Fortify реально
        // подключает штатно (routes.php:40,98-100) — register/password-reset
        // идут через ThrottleAuthForms выше, этот ключ Fortify не даёт.
        'verification' => 'verification',
    ],

    /*
     * Регистрация маршрутов, возвращающих представления Fortify.
     */

    'views' => true,

    /*
     * Включённые функции Fortify. Двухфакторная аутентификация и passkeys отключены; для двухфакторной
     * аутентификации потребуются соответствующие миграции, а WebAuthn ограничен целевыми браузерами проекта.
     */

    'features' => [
        Features::registration(),
        Features::resetPasswords(),
        Features::updateProfileInformation(),
        Features::updatePasswords(),
        Features::emailVerification(),
    ],

];
