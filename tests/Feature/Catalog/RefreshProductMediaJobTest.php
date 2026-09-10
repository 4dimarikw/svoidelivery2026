<?php

namespace Tests\Feature\Catalog;

use App\Events\CatalogProductMediaRefreshFailed;
use Domain\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File as TestingFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Infrastructure\Jobs\RefreshProductMediaJob;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\MediaLibrary\Downloaders\HttpFacadeDownloader;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileCannotBeAdded;
use Tests\TestCase;
use Throwable;

/**
 * Регрессия F09: ошибки скачивания, проверки и записи новой картинки
 * не должны удалять прежнюю картинку и её конверсии.
 * config('media-library.media_downloader') переключён на HttpFacadeDownloader
 * (vendor), который качает через Http-фасад — единственный способ подставить
 * Http::fake() под addMediaFromUrl() (DefaultDownloader его не видит).
 */
class RefreshProductMediaJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'media-library.media_downloader' => HttpFacadeDownloader::class,
            'media-library.disk_name' => 'public',
            'media-library.conversions_disk_name' => 'public',
        ]);
        Storage::fake('public');
        Http::preventStrayRequests();
    }

    public function test_failed_download_leaves_the_existing_image_untouched(): void
    {
        Http::fake(['broken-image.test/*' => Http::response('', 404)]);

        $product = Product::factory()->create();
        $product->addMedia(UploadedFile::fake()->image('old.jpg'))
            ->preservingOriginal()
            ->toMediaCollection('main');
        $originalMediaId = $product->getFirstMedia('main')?->id;
        $this->assertNotNull($originalMediaId);

        $job = new RefreshProductMediaJob($product, 'https://broken-image.test/new.jpg');

        $failed = false;
        try {
            $job->handle();
        } catch (FileCannotBeAdded) {
            $failed = true;
        }
        $this->assertTrue($failed, 'Ожидалось исключение при неудачной загрузке.');

        $product->refresh();
        $this->assertSame($originalMediaId, $product->getFirstMedia('main')?->id);
    }

    public function test_successful_download_replaces_the_old_image(): void
    {
        $fakeFile = TestingFile::image('new.jpg', 10, 10);
        Http::fake([
            'ok-image.test/*' => Http::response(
                file_get_contents($fakeFile->getPathname()),
                200,
                ['Content-Type' => 'image/jpeg'],
            ),
        ]);

        $product = Product::factory()->create();
        $product->addMedia(UploadedFile::fake()->image('old.jpg'))
            ->preservingOriginal()
            ->toMediaCollection('main');

        $oldMedia = $product->getFirstMedia('main');
        $oldPath = $oldMedia->getPathRelativeToRoot();
        (new RefreshProductMediaJob($product, 'https://ok-image.test/new.jpg'))->handle();

        $product->refresh();
        $media = $product->getFirstMedia('main');
        $this->assertNotNull($media);
        $this->assertStringContainsString('new', $media->file_name);
        $this->assertCount(1, $product->getMedia('main'));
        Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
        Storage::disk('public')->assertMissing($oldPath);
        $this->assertDatabaseMissing('media', ['id' => $oldMedia->id]);
    }

    public static function rejectedReplacements(): array
    {
        return [['mime'], ['size'], ['write']];
    }

    #[DataProvider('rejectedReplacements')]
    public function test_rejected_replacement_preserves_original_file_and_conversions(string $failure): void
    {
        $product = Product::factory()->create();
        $original = $product->addMedia(UploadedFile::fake()->image('old.jpg'))
            ->preservingOriginal()->toMediaCollection('main');
        $disk = Storage::disk('public');
        $paths = array_map(fn ($conversion) => $original->getPathRelativeToRoot($conversion), ['', 'thumb', 'label']);
        $contents = array_map(fn ($path) => $disk->get($path), $paths);
        $url = $original->getUrl();
        $replacement = TestingFile::image('new.jpg', 10, 10);
        Http::fake(['image.test/*' => Http::response(
            $failure === 'mime' ? 'This is not an image' : file_get_contents($replacement->getPathname()),
            200, ['Content-Type' => 'image/jpeg'],
        )]);
        if ($failure === 'size') {
            config(['media-library.max_file_size' => 1]);
        }
        if ($failure === 'write') {
            $failingDisk = Mockery::mock($disk);
            $failingDisk->shouldReceive('put')->once()->andReturn(false);
            app('filesystem')->set('public', $failingDisk);
        }
        $exception = null;
        try {
            (new RefreshProductMediaJob($product, 'https://image.test/new.jpg'))->handle();
        } catch (Throwable $caught) {
            $exception = $caught;
        } finally {
            app('filesystem')->set('public', $disk);
        }
        $this->assertInstanceOf(FileCannotBeAdded::class, $exception);
        $product->refresh();
        $this->assertCount(1, $product->getMedia('main'));
        $this->assertSame($original->id, $product->getFirstMedia('main')->id);
        $this->assertSame($url, $product->getFirstMedia('main')->getUrl());
        foreach ($paths as $index => $path) {
            $disk->assertExists($path);
            $this->assertSame($contents[$index], $disk->get($path));
        }
    }

    public function test_failed_hook_dispatches_an_event(): void
    {
        Event::fake([CatalogProductMediaRefreshFailed::class]);

        $product = Product::factory()->create();
        $job = new RefreshProductMediaJob($product, 'https://broken-image.test/new.jpg');

        $job->failed(new RuntimeException('download failed'));

        Event::assertDispatched(CatalogProductMediaRefreshFailed::class, function (CatalogProductMediaRefreshFailed $event) use ($product): bool {
            $this->assertSame($product->id, $event->productId);
            $this->assertSame('download failed', $event->errorMessage);

            return true;
        });
    }
}
