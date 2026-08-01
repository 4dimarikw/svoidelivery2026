<?php

namespace Services\CatalogImport\Dto;

readonly class RawRow
{
    /**
     * @param  array<string, string>  $data
     */
    public function __construct(
        public array $data,
        public int $lineNumber,
    ) {}

    public function get(string $column, string $default = ''): string
    {
        return trim((string) ($this->data[$column] ?? $default));
    }
}
