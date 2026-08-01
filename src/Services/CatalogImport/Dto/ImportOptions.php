<?php

namespace Services\CatalogImport\Dto;

final readonly class ImportOptions
{
    public function __construct(
        public bool $dryRun = false,
        /** Максимальное число warnings, сохраняемых в памяти/JSON. null → из конфига. */
        public ?int $warningLimit = null,
        /** Режим транзакций: 'row' | 'chunk' | 'none'. null → из конфига. */
        public ?string $transactionMode = null,
        /** Размер чанка для transactionMode='chunk'. null → из конфига. */
        public ?int $chunkSize = null,
        /**
         * Белый список slug категорий для импорта. Строки прочих категорий пропускаются.
         * Пустой массив = фильтр выключен, импортируются все категории.
         *
         * @var list<string>
         */
        public array $categoryFilter = [],
    ) {}
}
