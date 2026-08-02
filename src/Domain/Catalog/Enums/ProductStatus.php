<?php

namespace Domain\Catalog\Enums;

enum ProductStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';


    public function toString(): ?string
    {
        return match ($this) {
            self::DRAFT => 'Черновик',
            self::PUBLISHED => 'Опубликован',
            self::ARCHIVED => 'В архиве',
        };
    }

    public static function exists(string $value): bool
    {
        return self::tryFrom($value) !== null;
    }
}

