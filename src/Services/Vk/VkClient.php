<?php

declare(strict_types=1);

namespace Services\Vk;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Services\Vk\Exceptions\VkApiException;

/**
 * Тонкий клиент VK API (только wall.get, для больших сценариев не нужен
 * SDK). Конвенции — как у Services\Untappd\Repositories\UntappdRepository:
 * baseUrl+timeout+retry на одном PendingRequest, ключ доступа мержится
 * в query каждого вызова, а не в заголовки.
 *
 * VK возвращает ошибки в теле при HTTP 200 ({"error": {...}}), поэтому
 * ->throw() тут бесполезен — тело разбирается вручную в call().
 */
final class VkClient
{
    private const string BASE_URL = 'https://api.vk.com/method/';

    private const int DEFAULT_TIMEOUT = 30;

    private const int MAX_RETRIES = 2;

    private const int RETRY_DELAY = 100;

    private PendingRequest $http;

    public function __construct(
        private readonly ?string $accessToken,
        private readonly string $apiVersion = '5.199',
        private readonly int $timeout = self::DEFAULT_TIMEOUT,
    ) {
        $this->http = Http::baseUrl(self::BASE_URL)
            ->timeout($this->timeout)
            ->retry(self::MAX_RETRIES, self::RETRY_DELAY);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<array<string, mixed>> сырые посты (response.items)
     *
     * @throws VkApiException
     */
    public function wallGet(string $domain, int $count, int $offset = 0): array
    {
        $response = $this->call('wall.get', [
            'domain' => $domain,
            'count' => $count,
            'offset' => $offset,
        ]);

        return $response['items'] ?? [];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed> содержимое ключа "response"
     *
     * @throws VkApiException
     */
    private function call(string $method, array $params): array
    {
        $response = $this->http->get($method, array_merge($params, [
            'access_token' => $this->accessToken,
            'v' => $this->apiVersion,
        ]));

        $body = $response->json();

        if (isset($body['error'])) {
            throw VkApiException::fromErrorPayload($body['error']);
        }

        if (! $response->successful()) {
            throw new VkApiException("VK API HTTP {$response->status()}");
        }

        return $body['response'] ?? [];
    }
}
