<?php

namespace Support\Logging;

/** Безопасный Throwable для потребителей события MessageLogged. */
final class RedactedException extends \RuntimeException
{
    public function __construct(array $details)
    {
        parent::__construct($details['class'].': '.$details['message'], 0,
            isset($details['previous']) ? new self($details['previous']) : null);
        $this->file = $details['file'];
        $this->line = $details['line'];
        // Исключаем исходные аргументы даже при zend.exception_ignore_args=0.
        (new \ReflectionProperty(\Exception::class, 'trace'))->setValue($this, $details['trace']);
    }
}
