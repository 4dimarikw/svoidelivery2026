<?php

namespace App\Telegraph;

use DefStudio\Telegraph\Handlers\WebhookHandler as BaseWebhookHandler;
use Domain\Telegram\Support\TelegramLinkCode;

/**
 * Интеграционная точка Telegraph (config('telegraph.webhook.handler')) —
 * живёт в app/, а не в Domain\, по тому же принципу, что и
 * app/Actions/Fortify/, app/MoonShine/: framework/vendor-glue, не доменная
 * логика (см. CLAUDE.md, "Architecture: PSR-4 layout beyond app/").
 *
 * Единственное переопределение — команда /start, обрабатывающая привязку
 * Telegram к аккаунту (см. Domain\Telegram\Support\TelegramLinkCode и
 * "не залогиненного гостя логиним через Login Widget, залогиненного
 * привязываем через бота" в /account/profile). Всё остальное (неизвестные
 * команды, обычные сообщения) отдаётся поведению базового класса — тому же,
 * что и у штатного EmptyWebhookHandler.
 */
class WebhookHandler extends BaseWebhookHandler
{
    public function start(string $parameter): void
    {
        $user = TelegramLinkCode::resolve($parameter);

        if ($user === null) {
            $this->reply(__('telegram.link_code_invalid'));

            return;
        }

        $telegramUser = $this->message?->from();

        $this->chat->fill([
            'user_id' => $user->id,
            'name' => $this->chat->name ?: trim(($telegramUser?->firstName() ?? '').' '.($telegramUser?->lastName() ?? '')),
        ])->save();

        $this->reply(__('telegram.linked'));
    }
}
