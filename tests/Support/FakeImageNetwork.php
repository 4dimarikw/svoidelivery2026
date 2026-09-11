<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Http;
use Services\RemoteImages\ImageDnsResolver;
use Services\RemoteImages\ImageDownloadFailed;
use Services\RemoteImages\ImageHttpTransport;

class FakeImageNetwork extends ImageHttpTransport
{
    public static function install(): void
    {
        app()->instance(ImageDnsResolver::class, new class extends ImageDnsResolver
        {
            public function resolve(string $host): array
            {
                return ['93.184.216.34'];
            }
        });
        app()->instance(ImageHttpTransport::class, new self);
        Http::preventStrayRequests();
    }

    public function get(string $url, string $host, string $ip, callable $write): void
    {
        $response = Http::withoutRedirecting()->get($url);
        if ($response->status() !== 200) {
            throw new ImageDownloadFailed('Fake image transport failure');
        }
        foreach (str_split($response->body(), 64) as $chunk) {
            $write($chunk);
        }
    }

    public static function jpeg(): string
    {
        $image = imagecreatetruecolor(2, 2);
        ob_start();
        imagejpeg($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
