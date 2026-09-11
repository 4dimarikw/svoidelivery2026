<?php

namespace Services\RemoteImages;

class ImageDnsResolver
{
    /** @return list<string> */
    public function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if ($records === false) {
            throw new ImageDownloadFailed('Image DNS resolution failed');
        }

        return array_values(array_unique(array_filter(array_map(
            fn (array $record) => $record['ip'] ?? $record['ipv6'] ?? null,
            $records,
        ))));
    }
}
