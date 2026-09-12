<?php

declare(strict_types=1);

namespace Domain\Vk\Actions;

use DefStudio\Telegraph\Client\TelegraphResponse;
use Domain\Telegram\Models\TelegramChat;
use Domain\Vk\DTOs\PreparedVkPostImages;
use Domain\Vk\Enums\VkImageSendMode;
use Domain\Vk\Models\VkPost;
use Infrastructure\Settings\VKSyncSettings;
use RuntimeException;
use Services\Telegram\LocalMediaTelegraph;

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

    public function prepare(VkPost $post): PreparedVkPostImages
    {
        $mode = VkImageSendMode::from(app(VKSyncSettings::class)->image_send_mode);
        $urls = array_slice($post->images ?? [], 0, self::MAX_ALBUM_SIZE);
        if ($mode === VkImageSendMode::OriginalUrl || $urls === []) {
            return new PreparedVkPostImages($mode, $urls, $urls);
        }
        app(DownloadVkPostImages::class)($post, $urls);
        $images = [];
        $imageUrls = [];
        foreach ($urls as $url) {
            $media = $post->localImage($url);
            if ($media === null) {
                throw new RuntimeException('Локальная копия картинки поста VK #'.$post->id.' отсутствует.');
            }
            $imageUrls[] = $media->getUrl();
            if ($mode === VkImageSendMode::SiteUrl) {
                $images[] = $media->getUrl();
            } else {
                $path = $media->getPath();
                if (! is_file($path) || ! is_readable($path)) {
                    throw new RuntimeException('Файл картинки поста VK #'.$post->id.' недоступен для чтения.');
                }
                $images[] = $path;
            }
        }

        if ($mode === VkImageSendMode::SiteFile) {
            app(LocalMediaTelegraph::class)->validateLocalPhotos($images);
        }

        return new PreparedVkPostImages($mode, $images, $imageUrls);
    }

    public function __invoke(VkPost $post, TelegramChat $chat, ?PreparedVkPostImages $prepared = null): TelegraphResponse
    {
        $text = $post->broadcastText;
        $prepared ??= $this->prepare($post);
        $images = $prepared->images;

        if ($images === []) {
            return $chat->html($text)->send();
        }

        if (count($images) === 1) {
            $caption = mb_strlen($text) <= self::CAPTION_LIMIT ? $text : null;
            $response = $chat->photo($images[0])->html($caption ?? '')->send();

            if (! $response->telegraphOk()) {
                return $response;
            }

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

        $response = $prepared->mode === VkImageSendMode::SiteFile
            ? app(LocalMediaTelegraph::class)->chat($chat)->localMediaGroup($media)->send()
            : $chat->mediaGroup($media)->send();

        if (! $response->telegraphOk()) {
            return $response;
        }

        if (! $fitsCaption) {
            return $chat->html($text)->send();
        }

        return $response;
    }

}
