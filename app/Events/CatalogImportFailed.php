<?php

namespace App\Events;

final readonly class CatalogImportFailed implements LoggableEvent
{
    public function __construct(
        public string $path,
        public string $exceptionClass,
        public string $errorMessage,
    )
    {
    }

    public function eventType(): string
    {
        return 'catalog_import.failed';
    }

    public function level(): string
    {
        return 'error';
    }

    public function message(): string
    {
        return "Импорт не удался: {$this->errorMessage}";
    }

    public function context(): array
    {
        return [
            'path' => $this->path,
            'exception_class' => $this->exceptionClass,
            'error_message' => $this->errorMessage,
        ];
    }
}
