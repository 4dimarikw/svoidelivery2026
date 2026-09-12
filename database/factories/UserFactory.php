<?php

namespace Database\Factories;

use Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    // Модель задаётся явно, поскольку User находится в Domain\Auth\Models и не соответствует стандартным путям
    // поиска модели по имени фабрики.
    protected $model = User::class;

    /**
     * Пароль, используемый фабрикой.
     */
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Состояние пользователя с неподтверждённым адресом электронной почты.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Аккаунт, созданный через Telegram Login Widget: ни email, ни пароля —
     * Telegram их не отдаёт (см. TelegramLoginController). Сама привязка
     * (telegraph_chats.user_id) сюда не входит — телеграм-идентичность
     * принадлежит Domain\Telegram, не users; тесты, которым нужен именно
     * связанный чат, заводят Domain\Telegram\Models\TelegramChat отдельно
     * (см. TelegramLoginTest).
     */
    public function telegram(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => null,
            'email_verified_at' => null,
            'password' => null,
        ]);
    }
}
