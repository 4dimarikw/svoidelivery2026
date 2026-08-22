<?php

namespace Tests\Feature\Catalog;

use App\Events\CatalogProductMediaRefreshFailed;
use Domain\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File as TestingFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Infrastructure\Jobs\RefreshProductMediaJob;
use RuntimeException;
use Spatie\MediaLibrary\Downloaders\HttpFacadeDownloader;
use Tests\TestCase;
use Throwable;

/**
 * Регрессия: handle() раньше чистил старую коллекцию ДО скачивания новой
 * картинки (clear → add) — падение addMediaFromUrl() (сеть, битый URL)
 * оставляло товар вообще без изображения до следующего успешного запуска.
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

        config(['media-library.media_downloader' => HttpFacadeDownloader::class]);
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

        try {
            $job->handle();
            $this->fail('Ожидалось исключение при неудачной загрузке.');
        } catch (Throwable) {
            // ожидаемо — см. докблок handle().
        }

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

        (new RefreshProductMediaJob($product, 'https://ok-image.test/new.jpg'))->handle();

        $product->refresh();
        $media = $product->getFirstMedia('main');
        $this->assertNotNull($media);
        $this->assertStringContainsString('new', $media->file_name);
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
