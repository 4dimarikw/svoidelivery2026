<?php

namespace Domain\Content\Data;

use InvalidArgumentException;
use MoonShine\Contracts\UI\FieldContract;

final readonly class ContentItemGroup
{
    /**
     * @param  array<int, FieldContract>  $fields
     * @param  array<string, mixed>  $rules
     * @param  list<string>  $mediaCollections
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $fields = [],
        public array $rules = [],
        public array $mediaCollections = [],
    ) {
        if ($key === '') {
            throw new InvalidArgumentException('Content item group key cannot be empty.');
        }
    }
}
