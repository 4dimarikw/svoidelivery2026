<?php

declare(strict_types=1);

namespace Services\Untappd\Exceptions;

use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

final class UntappdException extends Exception
{
    private array $context;

    public function __construct(string $message = '', array $context = [], int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        // Log::channel('database') не существует в config/logging.php — бросало
        // InvalidArgumentException прямо из конструктора исключения.
        Log::error($message, $context);

        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public static function requestFailedData(RequestException $e, ?string $endpoint = null): array
    {
        $context = [];
        $message = $e->getMessage();

        if ($e->response instanceof Response) {
            $context = [
                'code' => $e->response->status(),
                'endpoint' => $endpoint,
                'response' => $e->response->getBody(),
                'headers' => $e->response->headers(),
            ];
            $message = $e->getMessage();
        }

        return ['message' => $message, 'context' => $context];
    }

    public static function failed(): self
    {
        return new self(
            'Beer not found',
            ['meta' => ['code' => 404, 'error_detail' => 'Beer not found']],
            code: 404
        );
    }

    public static function rateLimitExceeded($limit, $remaining): self
    {
        return new self(
            'Слишком много запросов',
            ['meta' => ['code' => 429, 'limit' => $limit, 'remaining' => $remaining]],
            code: 429
        );
    }
}
