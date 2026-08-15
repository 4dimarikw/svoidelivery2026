<?php

namespace Domain\Telegram\Models;

use DefStudio\Telegraph\Models\TelegraphChat;
use Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Наше расширение TelegraphChat (см. config/telegraph.php: models.chat).
 * Добавляет единственное, чего нет в пакете: связь с users.
 *
 * moonshine_user_id — зеркало user_id для привязки того же чата к админу
 * MoonShine (см. app/MoonShine/Support/MoonshineTelegramLink.php). Namespace
 * Domain\ не должен знать про вендорный MoonshineUser, поэтому здесь только
 * скоуп по колонке, без belongsTo — связь строится на стороне app/MoonShine.
 */
class TelegramChat extends TelegraphChat
{
    // Та же оговорка, что у TelegramBot: имя класса не совпадает с именем
    // таблицы пакета ("telegraph_chats").
    protected $table = 'telegraph_chats';

    protected $fillable = [
        'chat_id',
        'telegraph_bot_id',
        'name',
        'user_id',
        'moonshine_user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForMoonshineUser(Builder $query, int $moonshineUserId): Builder
    {
        return $query->where('moonshine_user_id', $moonshineUserId);
    }
}
