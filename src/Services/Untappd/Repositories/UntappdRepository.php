<?php

declare(strict_types=1);

namespace Services\Untappd\Repositories;

use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Services\Untappd\DTOs\UntappdApiResponse;

final class UntappdRepository implements UntappdInterface
{
    private const string BASE_URL = 'https://api.bcraftfest.ru/api/v1/';

    private const int DEFAULT_TIMEOUT = 30;

    private const int MAX_RETRIES = 2;

    private const int RETRY_DELAY = 100;

    private PendingRequest $http;

    public function __construct(
        private readonly ?string $access_token = '',
        private readonly ?string $user_agent = null,
        private readonly int $timeout = self::DEFAULT_TIMEOUT
    ) {
        $this->initializeHttp();
    }

    private function initializeHttp(): void
    {
        $this->http = Http::baseUrl(self::BASE_URL)
            ->timeout($this->timeout)
            ->retry(self::MAX_RETRIES, self::RETRY_DELAY)
            ->withHeaders([
                'User-Agent' => $this->user_agent ?? 'Untappd Client',
                'Accept' => 'application/json',
            ])->throw();
    }

    public function get(string $endpoint, ?array $options = []): ?UntappdApiResponse
    {
        $options = $this->prepareOptions($options);

        try {
            $response = $this->http->get($endpoint, $options);

            return UntappdApiResponse::fromResponse($response);
        } catch (RequestException $e) {
            // Log::channel('database') не существует в config/logging.php — раньше
            // это бросало InvalidArgumentException прямо изнутри этого catch,
            // подменяя исходную ошибку. report() уходит в default-стек логов.
            report($e);

            return match ($e->response->getStatusCode()) {
                500, 429 => UntappdApiResponse::fromRequestException($e),
                default => UntappdApiResponse::fromResponse($e->response)
            };
        } catch (Exception $e) {
            report($e);
        }

        return null;
    }

    private function prepareOptions(array $options): array
    {
        return array_merge(
            [
                'access_token' => $this->access_token,
                'compact' => true,
            ],
            $options
        );
    }
}
