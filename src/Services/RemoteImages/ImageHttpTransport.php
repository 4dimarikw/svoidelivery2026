<?php

namespace Services\RemoteImages;

class ImageHttpTransport
{
    /** Получает поток через cURL, не выполняя повторное DNS-разрешение. */
    public function get(string $url, string $host, string $ip, callable $write): void
    {
        if (! extension_loaded('curl') || ! defined('CURLOPT_RESOLVE')) {
            throw new ImageDownloadFailed('Secure image transport requires cURL');
        }
        $curl = curl_init();
        if ($curl === false) {
            throw new ImageDownloadFailed('Cannot initialize image transport');
        }
        try {
            if (! curl_setopt_array($curl, $this->options($url, $host, $ip, $write))) {
                throw new ImageDownloadFailed('Cannot configure secure image transport');
            }
            if (! curl_exec($curl) || curl_getinfo($curl, CURLINFO_RESPONSE_CODE) !== 200) {
                throw new ImageDownloadFailed('Image transport failed (code '.curl_errno($curl).')');
            }
        } finally {
            curl_close($curl);
        }
    }

    /** @return array<int, mixed> */
    public function options(string $url, string $host, string $ip, callable $write): array
    {
        $address = str_contains($ip, ':') ? '['.$ip.']' : $ip;

        return [
            CURLOPT_URL => $url,
            CURLOPT_RESOLVE => ["{$host}:443:{$address}"],
            CURLOPT_PROXY => '',
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => max(1, (int) config('remote-images.connect_timeout', 5)),
            CURLOPT_TIMEOUT => max(1, (int) config('remote-images.timeout', 15)),
            CURLOPT_HTTPHEADER => ['Accept: image/jpeg, image/png, image/webp', 'Accept-Encoding: identity'],
            CURLOPT_HEADERFUNCTION => static function ($curl, string $header): int {
                // Прерываем ответ до тела, включая перенаправления и ошибки.
                if (preg_match('~^HTTP/\S+\s+(\d{3})~i', $header, $match)
                    && (int) $match[1] !== 200 && (int) $match[1] >= 200) {
                    return 0;
                }

                return strlen($header);
            },
            CURLOPT_WRITEFUNCTION => static fn ($curl, string $chunk): int => $write($chunk),
        ];
    }
}
