<?php

namespace Domain\Profile\Models;

use Database\Factories\Profile\ProfileFactory;
use Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    use HasFactory;

    protected $table = 'user_profiles';

    protected $fillable = [
        // user_id доступен массовому присваиванию для фабрик и тестов. Контроллер не принимает его из запроса:
        // связь profile()->updateOrCreate() задаёт владельца сама.
        'user_id',
        'first_name',
        'last_name',
        'patronymic',
        'phone',
        // Ссылки на социальные сети и мессенджеры хранятся в social_links в формате JSON. См. config/social.php
        // и методы socialLink()/socialLinks().
        'social_links',
        'default_order_comment',
        // Пишутся только Domain\Telegram\Actions\CheckTelegramBotAvailabilityAction,
        // не через пользовательские формы (ProfileController/ProfileFormPage их
        // не валидируют и не показывают редактируемыми).
        'is_bot_active',
        'last_bot_error',
        'bot_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'social_links' => 'array',
            'is_bot_active' => 'boolean',
            'bot_checked_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return ProfileFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Полное имя в порядке «Фамилия Имя Отчество»; null, если все поля пусты.
     */
    public function getFullNameAttribute(): ?string
    {
        $parts = array_filter([$this->last_name, $this->first_name, $this->patronymic]);

        return $parts ? implode(' ', $parts) : null;
    }

    /**
     * Ссылка на одну сеть из social_links, или null, если не заполнена.
     */
    public function socialLink(string $slug): ?string
    {
        $url = $this->social_links[$slug] ?? null;

        return $url !== '' ? $url : null;
    }

    /**
     * Все заполненные ссылки, в порядке config('social.networks'):
     * slug => ['label' => ..., 'url' => ...]. Пустые сети не включаются —
     * используется чекаутом (селект «откуда подставить») и не должно
     * рисовать пункты без ссылки.
     */
    public function socialLinks(): array
    {
        $links = [];

        foreach (config('social.networks', []) as $slug => $network) {
            $url = $this->socialLink($slug);

            if ($url !== null) {
                $links[$slug] = ['label' => $network['label'], 'url' => $url];
            }
        }

        return $links;
    }
}
