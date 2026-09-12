<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Domain\Vk\Enums\VkPostStatus;
use Domain\Vk\Models\VkPost;
use Illuminate\Console\Command;

class PruneVkPostImagesCommand extends Command
{
    protected $signature = 'vk:prune-post-images {--dry-run : Показать количество фотографий без удаления}';

    protected $description = 'Удалить загруженные фотографии постов VK, отправленных более недели назад';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $posts = 0;
        $images = 0;

        VkPost::query()
            ->where('status', VkPostStatus::SENT)
            ->where('broadcast_at', '<', now()->subWeek())
            ->whereHas('media', fn ($query) => $query->where('collection_name', VkPost::IMAGE_COLLECTION))
            ->with('media')
            ->chunkById(100, function ($batch) use ($dryRun, &$posts, &$images): void {
                foreach ($batch as $post) {
                    $images += $post->getMedia(VkPost::IMAGE_COLLECTION)->count();
                    $posts++;

                    if (! $dryRun) {
                        // Media Library удаляет и записи media, и соответствующие файлы.
                        $post->clearMediaCollection(VkPost::IMAGE_COLLECTION);
                    }
                }
            });

        $this->info(($dryRun ? 'Будет удалено' : 'Удалено')." фотографий: {$images}; постов: {$posts}.");

        return self::SUCCESS;
    }
}
