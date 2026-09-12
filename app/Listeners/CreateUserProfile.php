<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Registered;

class CreateUserProfile
{
    /**
     * Создаёт пустой профиль после регистрации. Все поля профиля допускают null и заполняются пользователем.
     * Обработчик определяет создание профиля независимо от CreateNewUser.
     */
    public function handle(Registered $event): void
    {
        $event->user->profile()->firstOrCreate([]);
    }
}
