<?php

declare(strict_types=1);

namespace Tests\Feature\Vk;

use Domain\Vk\Enums\VkPostStatus;
use Domain\Vk\Models\VkPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class PruneVkPostImagesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('vk-prune-test');
        config([
            'media-library.disk_name' => 'vk-prune-test',
            'filesystems.disks.vk-prune-test.url' => 'https://site.test/media',
        ]);
        $this->travelTo(now()->startOfSecond());
    }

    public function test_only_images_of_posts_sent_more_than_a_week_ago_are_deleted(): void
    {
        $old = VkPost::factory()->sent()->create([
            'broadcast_at' => now()->subWeek()->subSecond(),
            'images' => ['https://example.com/photo.jpg'],
        ]);
        $image = $this->addImage($old);
        $path = $image->getPathRelativeToRoot();
        $other = $this->addImage($old, 'other');

        $retained = [];
        foreach ([
            ['status' => VkPostStatus::SENT, 'broadcast_at' => now()->subWeek()],
            ['status' => VkPostStatus::SENT, 'broadcast_at' => now()->subDay(), 'posted_at' => now()->subMonth()],
            ['status' => VkPostStatus::SENT, 'broadcast_at' => null],
            ['status' => VkPostStatus::DRAFT, 'broadcast_at' => now()->subMonth()],
            ['status' => VkPostStatus::READY, 'broadcast_at' => now()->subMonth()],
        ] as $attributes) {
            $retained[] = $this->addImage(VkPost::factory()->create($attributes));
        }

        $this->artisan('vk:prune-post-images')
            ->expectsOutput('Удалено фотографий: 1; постов: 1.')
            ->assertSuccessful();

        Storage::disk('vk-prune-test')->assertMissing($path);
        $this->assertModelMissing($image);
        $this->assertSame(['https://example.com/photo.jpg'], $old->fresh()->images);
        $this->assertSame(VkPostStatus::SENT, $old->fresh()->status);
        foreach ([$other, ...$retained] as $media) {
            $this->assertModelExists($media);
            Storage::disk('vk-prune-test')->assertExists($media->getPathRelativeToRoot());
        }

        $this->artisan('vk:prune-post-images')
            ->expectsOutput('Удалено фотографий: 0; постов: 0.')
            ->assertSuccessful();
    }

    public function test_dry_run_preserves_files_and_media_records(): void
    {
        $post = VkPost::factory()->sent()->create(['broadcast_at' => now()->subDays(8)]);
        $media = $this->addImage($post);

        $this->artisan('vk:prune-post-images --dry-run')
            ->expectsOutput('Будет удалено фотографий: 1; постов: 1.')
            ->assertSuccessful();

        $this->assertModelExists($media);
        Storage::disk('vk-prune-test')->assertExists($media->getPathRelativeToRoot());
    }

    private function addImage(VkPost $post, string $collection = VkPost::IMAGE_COLLECTION): Media
    {
        return $post->addMedia(UploadedFile::fake()->image('photo.jpg'))->toMediaCollection($collection);
    }
}
