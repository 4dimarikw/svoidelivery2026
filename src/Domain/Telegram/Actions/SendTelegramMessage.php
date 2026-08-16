<?php

declare(strict_types=1);

namespace Domain\Telegram\Actions;

use DefStudio\Telegraph\Client\TelegraphResponse;
use DefStudio\Telegraph\Facades\Telegraph;
use Domain\Telegram\Models\TelegramBot;
use RuntimeException;

/**
 * Универсальная отправка произвольного HTML-сообщения в любой чат
 * (включая группу), опционально в тему форума (message_thread_id).
 * $chatId — голый chat_id, строка в telegraph_chats не нужна: пакетный
 * DefStudio\Telegraph\Concerns\HasBotsAndChats::chat() принимает
 * TelegraphChat|string, а getChatId() при строке возвращает её как есть.
 * $html должен быть уже экранирован вызывающим кодом (тот же принцип, что
 * у Domain\Vk\Actions\SendVkPostToChatAction::buildText()) — метод сам
 * ничего не эскейпит.
 */
final class SendTelegramMessage
{
    public function __invoke(string $chatId, string $html, ?int $threadId = null): TelegraphResponse
    {
        $bot = TelegramBot::current() ?? throw new RuntimeException('Telegram-бот не настроен.');

        $telegraph = Telegraph::bot($bot)->chat($chatId);

        if ($threadId !== null) {
            $telegraph = $telegraph->inThread($threadId);
        }

        $response = $telegraph->html($html)->send();

        // В отличие от SendVkPostToChatAction (там TelegraphResponse
        // отдаётся вызывающему как есть — тот сам разбирает статус по
        // каждому получателю рассылки), у универсального метода
        // единственный разумный контракт — «либо получилось, либо
        // исключение», чтобы вызывающему хватало одного try/catch(Throwable).
        if (! $response->telegraphOk()) {
            throw new RuntimeException($response->json('description') ?? ('HTTP '.$response->status()));
        }

        return $response;
    }
}
