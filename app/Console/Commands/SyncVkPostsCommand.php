<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Events\VKSyncFailed;
use Domain\Vk\Enums\VkPostStatus;
use Domain\Vk\Models\VkPost;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Infrastructure\Settings\VKSyncSettings;
use Services\Vk\DTOs\VkWallPost;
use Services\Vk\Support\VkImageUrlValidator;
use Services\Vk\VkClient;
use Throwable;

class SyncVkPostsCommand extends Command
{
    protected $signature = 'vk:sync-posts
        {--domain= : Короткое имя группы VK, переопределяет настройку}
        {--count= : Сколько постов забрать, переопределяет настройку}
        {--force : Игнорировать last_update и флаг active}
        {--dry-run : Ничего не сохранять}';

    protected $description = 'Синхронизировать посты со стены VK-группы для последующей рассылки в Telegram';

    public function handle(VKSyncSettings $settings, VkImageUrlValidator $validateImage): int
    {
        $accessToken = config('services.vk.access_token');

        if (blank($accessToken)) {
            $this->error('Не задан VK_ACCESS_TOKEN. Синхронизация невозможна.');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');

        if (! $settings->active && ! $force) {
            $this->warn('Синхронизация VK выключена в настройках.');

            return self::SUCCESS;
        }

        $domain = (string) ($this->option('domain') ?: $settings->domain);

        if ($domain === '') {
            $this->error('Не задан домен группы VK (--domain или настройка).');

            return self::FAILURE;
        }

        $count = (int) ($this->option('count') ?: $settings->count);

        if ($count <= 0) {
            $this->error('--count должен быть положительным числом.');

            return self::FAILURE;
        }

        $isDryRun = (bool) $this->option('dry-run');

        if ($isDryRun) {
            $this->info('[ТЕСТОВЫЙ РЕЖИМ] Изменения не будут сохранены.');
        }

        $client = new VkClient($accessToken, (string) config('services.vk.api_version', '5.199'));

        try {
            $items = $client->wallGet($domain, $count);
        } catch (Throwable $e) {
            $this->error("Синхронизация VK не удалась: {$e->getMessage()}");
            event(new VKSyncFailed($e::class, $e->getMessage()));

            return self::FAILURE;
        }

        $allowedTypes = $settings->post_types ?? ['post'];
        $lastUpdate = is_numeric($settings->last_update) ? (int) $settings->last_update : 0;

        $processed = 0;
        $created = 0;
        $updated = 0;
        $skippedType = 0;
        $skippedOld = 0;
        $rejectedImages = [];
        $maxDate = $lastUpdate;

        DB::beginTransaction();

        try {
            foreach ($items as $item) {
                $post = VkWallPost::fromArray($item, $allowedTypes);

                if (! in_array($post->postType, $allowedTypes, true)) {
                    $skippedType++;

                    continue;
                }

                if (! $force && $post->date <= $lastUpdate) {
                    $skippedOld++;

                    continue;
                }

                $processed++;
                $maxDate = max($maxDate, $post->date);

                $images = [];
                $rejected = [];

                foreach ($post->imageUrls as $url) {
                    if ($validateImage($url)) {
                        $images[] = $url;
                    } else {
                        $rejected[] = $url;
                        $rejectedImages[] = $url;
                    }
                }

                $existing = VkPost::query()
                    ->where('owner_id', $post->ownerId)
                    ->where('vk_post_id', $post->postId)
                    ->first();

                // Пост, уже прошедший ручную проверку (status != draft), не
                // перезаписываем — иначе синхронизация затрёт правки редактора.
                if ($existing !== null && $existing->status !== VkPostStatus::DRAFT) {
                    continue;
                }

                // status и message_text — поля ручного воркфлоу, синхронизация
                // их не трогает даже у существующего draft-поста: status
                // выставляется только при создании, message_text вообще
                // только редактором (см. ветку create ниже).
                $attributes = [
                    'post_type' => $post->postType,
                    'posted_at' => $post->date,
                    'text' => $post->text,
                    'images' => $images,
                    'rejected_images' => $rejected === [] ? null : $rejected,
                    'raw' => $post->raw,
                ];

                if ($existing !== null) {
                    $existing->update($attributes);
                    $updated++;
                } else {
                    VkPost::query()->create([
                        'owner_id' => $post->ownerId,
                        'vk_post_id' => $post->postId,
                        'status' => $settings->post_status ?? VkPostStatus::DRAFT->value,
                        'message_text' => $post->text,
                        ...$attributes,
                    ]);
                    $created++;
                }
            }

            if ($maxDate > $lastUpdate) {
                $settings->last_update = $maxDate;
                $settings->save();
            }
        } catch (Throwable $e) {
            DB::rollBack();
            $this->error("Синхронизация VK не удалась: {$e->getMessage()}");
            event(new VKSyncFailed($e::class, $e->getMessage()));

            return self::FAILURE;
        }

        if ($isDryRun) {
            DB::rollBack();
            $this->info('[ТЕСТОВЫЙ РЕЖИМ] Транзакция откачена.');
        } else {
            DB::commit();
        }

        $this->table(['Метрика', 'Количество'], [
            ['Получено от VK', count($items)],
            ['Обработано (новых+обновлённых)', $processed],
            ['Создано', $created],
            ['Обновлено', $updated],
            ['Пропущено (тип поста)', $skippedType],
            ['Пропущено (уже видели)', $skippedOld],
            ['Отклонено картинок', count($rejectedImages)],
        ]);

        if ($rejectedImages !== []) {
            $this->newLine();
            $this->warn('Отклонённые ссылки на картинки:');
            foreach (array_slice($rejectedImages, 0, 50) as $url) {
                $this->line("  {$url}");
            }
            if (count($rejectedImages) > 50) {
                $this->line('  ... и ещё '.(count($rejectedImages) - 50));
            }
        }

        return self::SUCCESS;
    }
}
