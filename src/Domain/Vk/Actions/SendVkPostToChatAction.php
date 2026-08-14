<?php

declare(strict_types=1);

namespace Domain\Vk\Actions;

use DefStudio\Telegraph\Client\TelegraphResponse;
use Domain\Telegram\Models\TelegramChat;
use Domain\Vk\Models\VkPost;

/**
 * Собирает и отправляет один VkPost в один Telegram-чат. Единственная
 * точка формирования сообщения — используется и «Отправить себе»
 * (синхронно, из MoonShine), и BroadcastVkPostJob (массовая рассылка).
 *
 * Лимиты Bot API: sendMessage — 4096 символов, caption (подпись к фото/
 * альбому) — 1024. Поэтому при длинном тексте с картинками подпись не
 * ставится вовсе, а текст уходит отдельным сообщением следом.
 */
final class SendVkPostToChatAction
{
    private const int CAPTION_LIMIT = 1024;

    private const int MAX_ALBUM_SIZE = 10;

    public function __invoke(VkPost $post, TelegramChat $chat): TelegraphResponse
    {
        $text = $this->buildText($post);
        $images = array_slice($post->images ?? [], 0, self::MAX_ALBUM_SIZE);

        if ($images === []) {
            return $chat->html($text)->send();
        }

        if (count($images) === 1) {
            $caption = mb_strlen($text) <= self::CAPTION_LIMIT ? $text : null;
            $response = $chat->photo($images[0])->html($caption ?? '')->send();

            if ($caption === null) {
                return $chat->html($text)->send();
            }

            return $response;
        }

        $fitsCaption = mb_strlen($text) <= self::CAPTION_LIMIT;

        $media = [];
        foreach ($images as $index => $url) {
            $item = ['type' => 'photo', 'media' => $url];

            if ($index === 0 && $fitsCaption) {
                $item['caption'] = $text;
                $item['parse_mode'] = 'HTML';
            }

            $media[] = $item;
        }

        $response = $chat->mediaGroup($media)->send();

        if (! $fitsCaption) {
            return $chat->html($text)->send();
        }

        return $response;
    }

    /**
     * Текст поста + ссылка на оригинал. HTML-экранирование обязательно —
     * Telegram принимает узкое подмножество HTML (parse_mode=HTML), и
     * необработанные "<"/"&" из VK-текста ломают разбор сообщения.
     * Внутренние ссылки VK вида [id123|Имя] / [club1|Название] разворачиваются
     * в обычный текст — Telegram такой синтаксис не понимает.
     */
    private function buildText(VkPost $post): string
    {
        $raw = preg_replace('/\[(?:id|club)\d+\|([^\]]*)\]/u', '$1', $post->broadcastText) ?? $post->broadcastText;

        $escaped = htmlspecialchars(trim($raw), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return $escaped."\n\n".$post->vkUrl;
    }
}
