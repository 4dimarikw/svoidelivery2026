<?php

namespace Tests\Feature\CatalogImport;

use Domain\Catalog\Models\Product;
use Domain\Untappd\Models\UntappdBeer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Services\CatalogImport\Dto\ImportContext;
use Services\CatalogImport\Dto\RawRow;
use Services\CatalogImport\Stages\PersistProductImageStage;
use Tests\Support\FakeImageNetwork;
use Tests\TestCase;

class PersistProductImageStageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FakeImageNetwork::install();
        Storage::fake('public');
        config(['media-library.disk_name' => 'public']);
    }

    private function context(): ImportContext
    {
        $context = new ImportContext(new RawRow([], 1));
        $context->product = Product::factory()->create();
        $context->untappdBeer = new UntappdBeer(['label' => 'https://untappd.com/new.jpg']);

        return $context;
    }

    public function test_rejected_image_warns_and_continues_without_removing_old_image(): void
    {
        Http::fake(['*' => Http::response('not an image', 200, ['Content-Type' => 'image/jpeg'])]);
        $context = $this->context();
        $old = $context->product->addMedia(UploadedFile::fake()->image('old.jpg'))
            ->withCustomProperties(['source' => 'untappd', 'source_url' => 'https://untappd.com/old.jpg'])
            ->toMediaCollection('main');
        $called = false;
        $before = glob(sys_get_temp_dir().'/remote-image-*');
        (new PersistProductImageStage)($context, function ($context) use (&$called) {
            $called = true;

            return $context;
        });
        $this->assertTrue($called);
        $this->assertCount(1, $context->warnings);
        $this->assertFalse($context->imageAttachedThisRow);
        $this->assertSame($old->id, $context->product->refresh()->getFirstMedia('main')->id);
        Storage::disk('public')->assertExists($old->getPathRelativeToRoot());
        $this->assertSame($before, glob(sys_get_temp_dir().'/remote-image-*'));
    }

    public function test_valid_image_is_attached_and_repeat_import_does_not_download_again(): void
    {
        Http::fake(['*' => Http::response(FakeImageNetwork::jpeg())]);
        $context = $this->context();
        $before = glob(sys_get_temp_dir().'/remote-image-*');
        (new PersistProductImageStage)($context, fn ($context) => $context);
        $this->assertTrue($context->imageAttachedThisRow);
        $context->product->refresh();
        $this->assertSame('untappd', $context->product->getFirstMedia('main')->getCustomProperty('source'));
        (new PersistProductImageStage)($context, fn ($context) => $context);
        Http::assertSentCount(1);
        $this->assertSame($before, glob(sys_get_temp_dir().'/remote-image-*'));
    }
}
