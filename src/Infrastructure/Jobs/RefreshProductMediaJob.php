<?php

namespace Infrastructure\Jobs;

use App\Events\CatalogProductMediaRefreshFailed;
use Domain\Catalog\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileCannotBeAdded;
use Throwable;

class RefreshProductMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Product $product,
        public string $imageUrl,
        public string $collectionName = 'main'
    ) {}

    /**
     * Execute the job.
     *
     * addMediaFromUrl() скачивает файл во временный путь синхронно, до
     * возврата FileAdder (см. InteractsWithMedia::addMediaFromUrl) — реальное
     * добавление в коллекцию происходит только на toMediaCollection(). Старая
     * коллекция теперь чистится между этими двумя шагами: если скачивание
     * упадёт (сеть, битый URL), FileCannotBeAdded бросается раньше, чем
     * clearMediaCollection() успевает выполниться, и товар не остаётся без
     * картинки. Раньше порядок был обратный (clear → add) — именно это
     * оставляло товар без изображения при сбое загрузки.
     *
     * @throws FileCannotBeAdded
     */
    public function handle(): void
    {
        $fileAdder = $this->product->addMediaFromUrl($this->imageUrl)->preservingOriginal();

        $this->product->clearMediaCollection($this->collectionName);

        $fileAdder->toMediaCollection($this->collectionName);
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
