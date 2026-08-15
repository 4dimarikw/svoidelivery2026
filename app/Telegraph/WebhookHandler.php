<?php

namespace App\Telegraph;

use DefStudio\Telegraph\Handlers\WebhookHandler as BaseWebhookHandler;
use Domain\Telegram\Models\TelegramChat;
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
 * привязываем через бота" в /account/profile). Тот же код обслуживает и
 * привязку админа MoonShine (страница профиля в админке, нужна для кнопки
 * «Отправить себе» на VK-постах) — субъект различается по payload кода.
 * Всё остальное (неизвестные команды, обычные сообщения) отдаётся поведению
 * базового класса — тому же, что и у штатного EmptyWebhookHandler.
 */
class WebhookHandler extends BaseWebhookHandler
{
    public function start(string $parameter): void
    {
        $payload = TelegramLinkCode::resolveSubject($parameter);

        if ($payload === null) {
            $this->reply(__('telegram.link_code_invalid'));

            return;
        }

        $telegramUser = $this->message?->from();
        $name = $this->chat->name ?: trim(($telegramUser?->firstName() ?? '').' '.($telegramUser?->lastName() ?? ''));

        if ($payload['subject'] === TelegramLinkCode::SUBJECT_MOONSHINE_USER) {
            // Перепривязка с другого Telegram-аккаунта: снять moonshine_user_id
            // со старого чата этого админа у того же бота, иначе save() ниже
            // упадёт на unique(['moonshine_user_id', 'telegraph_bot_id']).
            TelegramChat::query()
                ->forMoonshineUser($payload['id'])
                ->where('telegraph_bot_id', $this->chat->telegraph_bot_id)
                ->update(['moonshine_user_id' => null]);

            $this->chat->fill([
                'moonshine_user_id' => $payload['id'],
                'name' => $name,
            ])->save();

            $this->reply(__('telegram.linked_admin'));

            return;
        }

        $this->chat->fill([
            'user_id' => $payload['id'],
            'name' => $name,
        ])->save();

        $this->reply(__('telegram.linked'));
    }
}
