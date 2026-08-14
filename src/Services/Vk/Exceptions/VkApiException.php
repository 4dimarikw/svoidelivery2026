<?php

declare(strict_types=1);

namespace Services\Vk\Exceptions;

use RuntimeException;

/**
 * VK API возвращает ошибки телом {"error":{"error_code":..,"error_msg":..}}
 * при HTTP 200 — их не ловит Http::throw(), поэтому VkClient разбирает
 * тело ответа сам и кидает это исключение.
 */
final class VkApiException extends RuntimeException
{
    public static function fromErrorPayload(array $error): self
    {
        $code = $error['error_code'] ?? 0;
        $message = $error['error_msg'] ?? 'Неизвестная ошибка VK API';

        return new self("VK API error {$code}: {$message}");
    }
}
