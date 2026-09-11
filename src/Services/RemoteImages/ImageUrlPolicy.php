<?php

namespace Services\RemoteImages;

use Symfony\Component\HttpFoundation\IpUtils;

class ImageUrlPolicy
{
    public function __construct(private ImageDnsResolver $dns) {}

    /** @return array{host: string, ip: string} */
    public function resolve(string $url, string $profile): array
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! filter_var($url, FILTER_VALIDATE_URL)
            || preg_match('/[\x00-\x20\x7f\\\\]/', $url)
            || ($parts['scheme'] ?? '') !== 'https'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || ($parts['port'] ?? 443) !== 443) {
            throw new ImageDownloadFailed('Invalid image URL');
        }
        $host = strtolower($parts['host'] ?? '');
        $hosts = match ($profile) {
            'untappd' => config('remote-images.untappd_hosts', []),
            'vk' => config('services.vk.image_hosts', []),
            default => [],
        };
        $allowed = false;
        foreach ($hosts as $candidate) {
            $candidate = strtolower($candidate);
            if ($candidate !== '' && ($host === $candidate || ($profile === 'vk' && str_ends_with($host, '.'.$candidate)))) {
                $allowed = true;
            }
        }
        if (! $allowed || ! preg_match('/\A[a-z0-9]+(?:[a-z0-9.-]*[a-z0-9])?\z/D', $host)
            || filter_var($host, FILTER_VALIDATE_IP)) {
            throw new ImageDownloadFailed('Image host is not allowed');
        }
        $addresses = $this->dns->resolve($host);
        if ($addresses === []) {
            throw new ImageDownloadFailed('Image DNS returned no addresses');
        }
        foreach ($addresses as $address) {
            if (! $this->isPublic($address)) {
                throw new ImageDownloadFailed('Image DNS returned a non-public address');
            }
        }

        return ['host' => $host, 'ip' => $addresses[0]];
    }

    private function isPublic(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        // Исключаем служебные, переходные и документационные диапазоны.
        if (str_contains($ip, ':')) {
            return IpUtils::checkIp($ip, '2000::/3')
                && ! IpUtils::checkIp($ip, ['2001::/23', '2001:db8::/32', '2002::/16', '3fff::/20']);
        }

        return ! IpUtils::checkIp($ip, ['0.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4']);
    }
}
