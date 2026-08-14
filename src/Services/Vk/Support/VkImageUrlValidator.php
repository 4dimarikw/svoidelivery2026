<?php

declare(strict_types=1);

namespace Services\Vk\Support;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Проверяет ссылку на картинку из VK перед сохранением поста: сперва
 * формат/схему/хост (дёшево, без сети), затем реальную доступность через
 * HTTP HEAD. Невалидная ссылка не должна ронять весь импорт поста —
 * вызывающий код (SyncVkPostsCommand) отбрасывает такую картинку в
 * rejected_images и сохраняет пост без неё.
 *
 * Результат HEAD-проверки мемоизируется на время процесса — одна и та же
 * ссылка может встретиться повторно в рамках одного запуска команды.
 */
final class VkImageUrlValidator
{
    /** @var array<string, bool> */
    private static array $cache = [];

    public function __invoke(string $url): bool
    {
        if (! $this->hasValidFormat($url)) {
            return false;
        }

        if (array_key_exists($url, self::$cache)) {
            return self::$cache[$url];
        }

        return self::$cache[$url] = $this->isReachableImage($url);
    }

    private function hasValidFormat(string $url): bool
    {
        $url = trim($url);

        if ($url === '' || preg_match('/[\x00-\x20\x7f]/u', $url)) {
            return false;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || empty($parts['host'])) {
            return false;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $host = strtolower($parts['host']);
        $allowedHosts = config('services.vk.image_hosts', []);

        foreach ($allowedHosts as $allowedHost) {
            if ($host === $allowedHost || str_ends_with($host, '.'.$allowedHost)) {
                return true;
            }
        }

        return false;
    }

    private function isReachableImage(string $url): bool
    {
        try {
            $response = Http::timeout(5)->head($url);
        } catch (Throwable) {
            return false;
        }

        if (! $response->successful()) {
            return false;
        }

        $contentType = (string) $response->header('Content-Type');

        return str_starts_with($contentType, 'image/');
    }
}
