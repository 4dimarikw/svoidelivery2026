<?php

namespace Domain\Telegram\Models;

use DefStudio\Telegraph\Models\TelegraphChat;
use Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Наше расширение TelegraphChat (см. config/telegraph.php: models.chat).
 * Добавляет единственное, чего нет в пакете: связь с users.
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
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
