<?php

namespace App\Events;

final readonly class VKSyncFailed implements LoggableEvent
{
    public function __construct(
        public string $exceptionClass,
        public string $errorMessage,
    )
    {
    }

    public function eventType(): string
    {
        return 'GetPostFromVk.sync_failed';
    }

    public function level(): string
    {
        return 'error';
    }

    public function message(): string
    {
        return "Синхронизация VK не удалась: {$this->errorMessage}";
    }

    public function context(): array
    {
        return [
            'exception_class' => $this->exceptionClass,
            'error_message' => $this->errorMessage,
        ];
    }
}
