<?php

namespace Infrastructure\Jobs;

use App\Events\CatalogProductMediaRefreshFailed;
use Domain\Catalog\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Services\RemoteImages\RemoteImageDownloader;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileCannotBeAdded;
use Throwable;

class RefreshProductMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public Product $product,
        public string $imageUrl,
        public string $collectionName = 'main'
    ) {}

    /**
     * Коллекция main настроена как singleFile: Media Library удаляет предыдущее изображение после проверки и
     * сохранения нового файла и его конверсий. Это сохраняет изображение при отказе проверки MIME, размера или
     * записи на диск.
     *
     * @throws FileCannotBeAdded
     */
    public function handle(): void
    {
        app(RemoteImageDownloader::class)->withCleanup(fn () => $this->product
            ->addMediaFromUrl($this->imageUrl)
            ->toMediaCollection($this->collectionName));
    }

    public function failed(?Throwable $exception): void
    {
        event(new CatalogProductMediaRefreshFailed(
            productId: $this->product->getKey(),
            imageUrl: $this->imageUrl,
            exceptionClass: $exception !== null ? $exception::class : 'unknown',
            errorMessage: $exception?->getMessage() ?? 'unknown',
        ));
    }
}
