<?php

declare(strict_types=1);

namespace Services\Vk\Support;

use Services\RemoteImages\RemoteImageDownloader;
use Throwable;

final class VkImageUrlValidator
{
    /** @var array<string, bool> */
    private array $cache = [];

    public function reset(): void
    {
        $this->cache = [];
    }

    public function __invoke(string $url): bool
    {
        if (array_key_exists($url, $this->cache)) {
            return $this->cache[$url];
        }
        $downloader = app(RemoteImageDownloader::class);
        try {
            $path = $downloader->download($url, 'vk');
            $downloader->release($path);

            return $this->cache[$url] = true;
        } catch (Throwable) {
            return $this->cache[$url] = false;
        }
    }
}
