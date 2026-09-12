<?php

declare(strict_types=1);

namespace Services\Telegram;

use DefStudio\Telegraph\Telegraph;
use RuntimeException;

final class LocalMediaTelegraph extends Telegraph
{
    /** @param list<string> $paths */
    public function validateLocalPhotos(array $paths): void
    {
        foreach ($paths as $path) {
            if (! is_file($path) || ! is_readable($path)) {
                throw new RuntimeException('Локальный файл картинки недоступен для чтения.');
            }
            $client = clone $this;
            $this->attachPhoto($client, $path, basename($path));
            $client->files->get('photo')->contents();
        }
    }

    /** @param list<array<string, mixed>> $media */
    public function localMediaGroup(array $media): self
    {
        $client = $this->mediaGroup($media);
        foreach ($media as $index => $item) {
            if (! is_file($item['media'])) {
                throw new RuntimeException('Локальный файл картинки больше не доступен.');
            }
            // Используем штатную валидацию фотографии и Attachment Telegraph.
            $this->attachPhoto($client, $item['media'], basename($item['media']));
            $key = 'photo_'.$index;
            $client->files->put($key, $client->files->pull('photo'));
            $client->data['media'][$index]['media'] = 'attach://'.$key;
        }

        return $client;
    }
}
