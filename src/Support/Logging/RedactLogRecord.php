<?php

namespace Support\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

final class RedactLogRecord implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $redactor = new LogRedactor;

        return $record->with(
            message: $redactor->text($record->message),
            context: [...$redactor->value($record->context), 'correlation_id' => LogCorrelation::id()],
            extra: $redactor->value($record->extra),
        );
    }
}
