<?php

declare(strict_types=1);

namespace Services\Vk\DTOs;

/**
 * Один пост со стены VK (ответ wall.get). Разворачивает репосты
 * (copy_history) и вытаскивает URL самых крупных фото из attachments.
 */
final readonly class VkWallPost
{
    public function __construct(
        public int $ownerId,
        public int $postId,
        public string $postType,
        public int $date,
        public string $text,
        /** @var list<string> */
        public array $imageUrls,
        /** @var array<string, mixed> */
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $allowedRepostTypes  какие типы допустимы для контента из copy_history
     */
    public static function fromArray(array $data, array $allowedRepostTypes = ['copy']): self
    {
        $source = $data;
        $postType = 'post';

        $copyHistory = $data['copy_history'][0] ?? null;
        if (is_array($copyHistory) && in_array('copy', $allowedRepostTypes, true)) {
            $source = $copyHistory;
            $postType = 'copy';
        }

        return new self(
            ownerId: (int) $data['owner_id'],
            postId: (int) $data['id'],
            postType: $postType,
            date: (int) $data['date'],
            text: (string) ($source['text'] ?? ''),
            imageUrls: self::extractImageUrls($source),
            raw: $data,
        );
    }

    /**
     * @param  array<string, mixed>  $source
     * @return list<string>
     */
    private static function extractImageUrls(array $source): array
    {
        $urls = [];

        foreach ($source['attachments'] ?? [] as $attachment) {
            if (($attachment['type'] ?? null) !== 'photo') {
                continue;
            }

            $sizes = $attachment['photo']['sizes'] ?? [];
            if ($sizes === []) {
                continue;
            }

            $largest = collect($sizes)->sortByDesc('width')->first();
            if (isset($largest['url'])) {
                $urls[] = (string) $largest['url'];
            }
        }

        return $urls;
    }
}
