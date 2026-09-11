<?php

namespace Services\RemoteImages;

use Spatie\MediaLibrary\Downloaders\Downloader;

final class SpatieImageDownloader implements Downloader
{
    public function getTempFile(string $url): string
    {
        return app(RemoteImageDownloader::class)->download($url, 'untappd');
    }
}
