<?php

namespace Domain\Content\Contracts;

use Domain\Content\Data\ContentItemGroup;
use MoonShine\Contracts\UI\FieldContract;

interface ContentBlockType
{
    public function key(): string;

    public function label(): string;

    /** @return array<int, FieldContract> */
    public function blockFields(): array;

    /** @return array<string, mixed> */
    public function blockRules(): array;

    /** @return array<string, ContentItemGroup> */
    public function itemGroups(): array;

    /** @return list<string> */
    public function mediaCollections(): array;
}
