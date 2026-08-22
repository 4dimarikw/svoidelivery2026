<?php

namespace Domain\Telegram\Models;

use App\Events\TelegramBotUsernameFetchFailed;
use DefStudio\Telegraph\Models\TelegraphBot;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Наше расширение TelegraphBot (см. config/telegraph.php: models.bot).
 *
 * У пакета нет колонки username — только token/name. Реальный @username
 * достаётся только живым вызовом Telegram API (info()['username']), поэтому
 * заполняется он не руками, а автоматически при создании/смене токена — так
 * штатная интерактивная artisan-команда telegraph:new-bot (спрашивает только
 * токен и name) остаётся единственным способом завести бота, без своей
 * команды-обёртки.
 */
class TelegramBot extends TelegraphBot
{
    // Имя класса ("TelegramBot") не совпадает с именем таблицы пакета
    // ("telegraph_bots") — конвенция Eloquent угадала бы "telegram_bots".
    protected $table = 'telegraph_bots';

    protected $fillable = [
        'token',
        'name',
        'username',
    ];

    private const CACHE_KEY = 'telegram.active_bot';

    public static function booted(): void
    {
        parent::booted(); // безымянному боту пакет сам подставляет "Bot #id"

        static::saved(function (self $bot) {
            self::flushCache();

            // В тестах — никаких живых обращений к Telegram; и не дёргаем
            // API, если токен не менялся и username уже известен.
            if (app()->runningUnitTests()) {
                return;
            }

            if (! $bot->wasChanged('token') && $bot->username !== null) {
                return;
            }

            try {
                $username = $bot->info()['username'] ?? null;
            } catch (Throwable $e) {
                // Невалидный токен или Telegram недоступен — не блокируем
                // создание/сохранение бота из-за этого, но бот сохранится с
                // username = null, что тихо ломает виджет входа и deep-link
                // привязки — событие в event_logs делает это заметным.
                event(new TelegramBotUsernameFetchFailed($bot->id, $e::class, $e->getMessage()));

                return;
            }

            if ($username) {
                $bot->forceFill(['username' => $username])->saveQuietly();
            }
        });

        static::deleted(fn () => self::flushCache());
    }

    /**
     * Единственный активный бот проекта. Тот же паттерн, что у
     * Domain\Catalog\Filters\FilterOptionsRegistry / CategoryRegistry —
     * Cache::rememberForever + явный flush на запись/удаление, а не голая
     * static-переменная.
     */
    public static function current(): ?self
    {
        return Cache::rememberForever(self::CACHE_KEY, static fn () => self::query()->latest()->first());
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
