<?php

namespace Domain\Vk\Enums;

enum VkPostStatus: string
{
    case DRAFT = 'draft';
    case READY = 'ready';
    case SENT = 'sent';

    public function toString(): ?string
    {
        return match ($this) {
            self::DRAFT => 'Черновик',
            self::READY => 'Готов к рассылке',
            self::SENT => 'Отправлен',
        };
    }

    public static function exists(string $value): bool
    {
        return self::tryFrom($value) !== null;
    }
}
