<?php

declare(strict_types=1);

namespace Domain\Vk\Enums;

enum VkImageSendMode: string
{
    case OriginalUrl = 'original_url';
    case SiteUrl = 'site_url';
    case SiteFile = 'site_file';

    public function label(): string
    {
        return match ($this) {
            self::OriginalUrl => 'Оригинальными ссылками VK',
            self::SiteUrl => 'Ссылками с сайта',
            self::SiteFile => 'Файлами с сайта',
        };
    }
}
