<?php

declare(strict_types=1);

namespace Domain\Vk\Actions;

use App\Events\VkPostImageDownloadFailed;
use Domain\Vk\Models\VkPost;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Services\RemoteImages\RemoteImageDownloader;
use Throwable;

class DownloadVkPostImages
{
    public function __construct(private RemoteImageDownloader $downloader) {}

    /** @param list<string>|null $urls */
    public function __invoke(VkPost $post, ?array $urls = null): void
    {
        $urls ??= $post->images ?? [];
        if ($urls === []) {
            return;
        }

        // Срок покрывает таймаут каждой загрузки, а также запись в Media Library.
        try {
            Cache::lock('vk-post-images:'.$post->id, max(120, count($urls) * 60))->block(5, function () use ($post, $urls): void {
                $post->unsetRelation('media');
                $errors = [];
                foreach (array_unique($urls) as $url) {
                    try {
                        if ($post->localImage($url) !== null) {
                            continue;
                        }
                        $hash = hash('sha256', $url);
                        $stale = $post->getMedia(VkPost::IMAGE_COLLECTION)->filter(fn ($media) => $media->getCustomProperty('source_hash') === $hash);
                        $this->downloader->withCleanup(function () use ($post, $url, $hash): void {
                            $path = $this->downloader->download($url, 'vk');
                            $this->convertToWebp($path);
                            $post->addMedia($path)->usingFileName($hash.'.webp')
                                ->withCustomProperties(['source_url' => $url, 'source_hash' => $hash])
                                ->toMediaCollection(VkPost::IMAGE_COLLECTION);
                        });
                        foreach ($stale as $media) {
                            $media->delete();
                        }
                    } catch (Throwable $e) {
                        event(new VkPostImageDownloadFailed($post->id, $url, $e->getMessage()));
                        $errors[] = $e->getMessage();
                    } finally {
                        $post->unsetRelation('media');
                    }
                }
                if ($errors !== []) {
                    throw new RuntimeException('Не удалось загрузить картинки поста VK #'.$post->id.' ('.count($errors).'): '.$errors[0]);
                }
            });
        } catch (LockTimeoutException $e) {
            $error = 'Картинки этого поста уже загружаются. Повторите попытку после завершения загрузки.';
            event(new VkPostImageDownloadFailed($post->id, $urls[0], $error));
            throw new RuntimeException($error, previous: $e);
        }
    }

    private function convertToWebp(string $path): void
    {
        if ((new \finfo(FILEINFO_MIME_TYPE))->file($path) === 'image/webp') {
            return;
        }
        if (! function_exists('imagewebp')) {
            throw new RuntimeException('Для сохранения картинок VK требуется поддержка WebP в PHP GD.');
        }
        // Файл уже проверен RemoteImageDownloader, включая ограничения размеров.
        $image = imagecreatefromstring(file_get_contents($path));
        if ($image === false) {
            throw new RuntimeException('Не удалось декодировать картинку VK для преобразования в WebP.');
        }
        try {
            imagepalettetotruecolor($image);
            imagealphablending($image, false);
            imagesavealpha($image, true);
            if (! imagewebp($image, $path, 80)) {
                throw new RuntimeException('Не удалось сохранить картинку VK в WebP.');
            }
            clearstatcache(true, $path);
            if (filesize($path) === 0 || (new \finfo(FILEINFO_MIME_TYPE))->file($path) !== 'image/webp') {
                throw new RuntimeException('Преобразование картинки VK в WebP завершилось ошибкой.');
            }
        } finally {
            imagedestroy($image);
        }
    }
}
