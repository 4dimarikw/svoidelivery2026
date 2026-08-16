<?php

declare(strict_types=1);

namespace Tests\Feature\Telegram;

use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Telegraph as TelegraphClient;
use Domain\Telegram\Actions\SendTelegramMessage;
use Domain\Telegram\Models\TelegramBot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Domain\Telegram\Actions\SendTelegramMessage — универсальная отправка
 * произвольного HTML-сообщения в любой чат (голый chat_id, без строки в
 * telegraph_chats), опционально в тему форума. По образцу
 * tests\Feature\Telegram\CheckTelegramBotActivityTest — Telegraph::fake()
 * перехватывает исходящий запрос.
 */
class SendTelegramMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_html_message_to_bare_chat_id_with_thread_id(): void
    {
        Telegraph::fake();

        TelegramBot::create([
            'token' => 'test-bot-token',
            'name' => 'Test Bot',
            'username' => 'svoi_test_bot',
        ]);

        app(SendTelegramMessage::class)('-100123456789', '<b>Привет</b>', 42);

        Telegraph::assertSentData(TelegraphClient::ENDPOINT_MESSAGE, [
            'chat_id' => '-100123456789',
            'message_thread_id' => 42,
            'text' => '<b>Привет</b>',
        ], false);
    }

    public function test_it_sends_without_thread_id_when_none_given(): void
    {
        Telegraph::fake();

        TelegramBot::create([
            'token' => 'test-bot-token',
            'name' => 'Test Bot',
            'username' => 'svoi_test_bot',
        ]);

        app(SendTelegramMessage::class)('-100123456789', 'plain');

        Telegraph::assertSentData(TelegraphClient::ENDPOINT_MESSAGE, [
            'chat_id' => '-100123456789',
            'text' => 'plain',
        ], false);
    }

    public function test_it_throws_when_no_bot_is_configured(): void
    {
        Telegraph::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Telegram-бот не настроен.');

        app(SendTelegramMessage::class)('-100123456789', 'text');
    }

    public function test_it_throws_with_telegram_description_on_api_error(): void
    {
        Telegraph::fake([
            TelegraphClient::ENDPOINT_MESSAGE => [
                'ok' => false,
                'description' => 'Bad Request: chat not found',
            ],
        ]);

        TelegramBot::create([
            'token' => 'test-bot-token',
            'name' => 'Test Bot',
            'username' => 'svoi_test_bot',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Bad Request: chat not found');

        app(SendTelegramMessage::class)('-100123456789', 'text');
    }
}
