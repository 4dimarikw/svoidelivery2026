<?php

namespace Support\Logging;

use Illuminate\Log\Logger;

final class RedactingLogger extends Logger
{
    protected function writeLog($level, $message, $context): void
    {
        $redactor = new LogRedactor;
        $original = array_merge($this->context, $context);
        $safe = $redactor->value($original);
        if (($original['exception'] ?? null) instanceof \Throwable) {
            $safe['exception'] = new RedactedException($safe['exception']);
        }
        // Родитель повторно объединяет shared context: в нём тоже не должно остаться секретов.
        $this->context = $redactor->value($this->context);
        parent::writeLog($level, $redactor->value($message), [
            ...$safe,
            'correlation_id' => LogCorrelation::id(),
        ]);
    }
}
