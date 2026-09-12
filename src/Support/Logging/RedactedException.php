<?php

namespace Support\Logging;

/** Безопасный Throwable для потребителей события MessageLogged. */
final class RedactedException extends \RuntimeException
{
    public function __construct(array $details)
    {
        parent::__construct($details['class'].': '.$details['message'], 0,
            is_array($details['previous'] ?? null) ? new self($details['previous']) : null);
        $this->file = $details['file'];
        $this->line = $details['line'];
        // Исключаем исходные аргументы даже при zend.exception_ignore_args=0.
        // На границе глубины LogRedactor заменяет previous/trace строкой-маркером.
        $trace = is_array($details['trace']) ? array_values(array_filter($details['trace'], 'is_array')) : [];
        $trace = array_map(static fn (array $frame) => array_filter(
            array_intersect_key($frame, array_flip(['file', 'line', 'class', 'type', 'function'])),
            static fn ($value, $key) => $key === 'line' ? is_int($value) : is_string($value),
            ARRAY_FILTER_USE_BOTH,
        ), $trace);
        (new \ReflectionProperty(\Exception::class, 'trace'))->setValue($this, $trace);
    }
}
