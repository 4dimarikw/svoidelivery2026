<?php

namespace Services\RemoteImages;

use Throwable;

class RemoteImageDownloader
{
    /** @var array<string, true> */
    private array $temporaryFiles = [];

    public function __construct(private ImageUrlPolicy $policy, private ImageHttpTransport $transport) {}

    public function download(string $url, string $profile): string
    {
        $target = $this->policy->resolve($url, $profile);
        $path = tempnam(sys_get_temp_dir(), 'remote-image-');
        if ($path === false) {
            throw new ImageDownloadFailed('Cannot create image temporary file');
        }
        $file = null;
        try {
            $file = fopen($path, 'wb');
            if ($file === false) {
                throw new ImageDownloadFailed('Cannot open image temporary file');
            }
            $limit = min((int) config('remote-images.max_bytes', 10485760), (int) config('media-library.max_file_size', 10485760));
            $bytes = 0;
            $this->transport->get($url, $target['host'], $target['ip'], static function (string $chunk) use ($file, $limit, &$bytes): int {
                $length = strlen($chunk);
                if ($bytes + $length > $limit) {
                    throw new ImageDownloadFailed('Image exceeds byte limit');
                }
                if (fwrite($file, $chunk) !== $length) {
                    throw new ImageDownloadFailed('Cannot write image temporary file');
                }
                $bytes += $length;

                return $length;
            });
            fclose($file);
            $file = null;
            $this->validateImage($path);
            $this->temporaryFiles[$path] = true;

            return $path;
        } catch (Throwable $exception) {
            if (is_resource($file)) {
                fclose($file);
            }
            @unlink($path);
            if ($exception instanceof ImageDownloadFailed) {
                throw $exception;
            }
            throw new ImageDownloadFailed('Image download or validation failed');
        }
    }

    private function validateImage(string $path): void
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $size = @getimagesize($path);
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)
            || $size === false || $size['mime'] !== $mime
            || $size[0] < 1 || $size[1] < 1
            || max($size[0], $size[1]) > (int) config('remote-images.max_dimension', 8192)
            || (int) config('remote-images.max_pixels', 20000000) < $size[0] * $size[1]) {
            throw new ImageDownloadFailed('Invalid image format or dimensions');
        }
        // Декодируем только после ограничений размера и числа пикселей.
        $image = @imagecreatefromstring(file_get_contents($path));
        if ($image === false) {
            throw new ImageDownloadFailed('Corrupt image');
        }
        imagedestroy($image);
    }

    public function release(string $path): void
    {
        if (isset($this->temporaryFiles[$path])) {
            if (is_file($path)) {
                unlink($path);
            }
            unset($this->temporaryFiles[$path]);
        }
    }

    /** Очищает файлы даже при отказе Media Library после успешного скачивания. */
    public function withCleanup(callable $callback): mixed
    {
        $existing = $this->temporaryFiles;
        try {
            return $callback();
        } finally {
            foreach (array_diff_key($this->temporaryFiles, $existing) as $path => $_) {
                $this->release($path);
            }
        }
    }
}
