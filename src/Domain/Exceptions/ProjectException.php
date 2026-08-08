<?php

declare(strict_types=1);

namespace Domain\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProjectException extends Exception
{
    protected array $context {
        get {
            return $this->context;
        }
    }

    public function __construct(string $message = '', array $context = [], int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->context = $context;

        // Log::error(), не Log::channel('database') — такого канала нет ни
        // в config/logging.php, ни где-либо ещё в проекте (стандартный
        // stack используется везде).
        Log::error($message, $this->context);
    }
}
