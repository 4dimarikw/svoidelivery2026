<?php

namespace Support\Logging\Events;

interface LoggableEvent
{
    public function eventType(): string;

    public function level(): string;

    public function message(): string;

    /** @return array<string, mixed> */
    public function context(): array;
}
