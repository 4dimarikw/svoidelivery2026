<?php

namespace Infrastructure\Jobs;

use Domain\Catalog\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileCannotBeAdded;

class RefreshProductMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
     * @throws FileCannotBeAdded
     */
    public function handle(): void
    {
        $this->product->clearMediaCollection($this->collectionName);

        $this->product->addMediaFromUrl($this->imageUrl)
            ->preservingOriginal()
            ->toMediaCollection($this->collectionName);

    }
}
