<?php

namespace App\Telegraph;

use DefStudio\Telegraph\Handlers\WebhookHandler as BaseWebhookHandler;
use Domain\Telegram\Actions\LinkTelegramAccount;

class WebhookHandler extends BaseWebhookHandler
{
    public function start(string $parameter): void
    {
        $sender = $this->message?->from();
        $chat = $this->message?->chat();
        $result = app(LinkTelegramAccount::class)->handle(
            $parameter, $this->bot->id, (string) $chat?->id(), $sender?->id(),
            $chat?->type() ?? '', $sender?->isBot() ?? true,
            trim(($sender?->firstName() ?? '').' '.($sender?->lastName() ?? '')),
        );

        // Reply only after commit; retries can confirm the persisted link.
        $this->reply(__('telegram.'.$result->value));
    }
}
