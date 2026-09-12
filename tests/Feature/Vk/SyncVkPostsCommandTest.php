<?php

namespace Tests\Feature\Vk;

use Domain\Vk\Actions\DownloadVkPostImages;
use Domain\Vk\Enums\VkPostStatus;
use Domain\Vk\Models\VkPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Infrastructure\Settings\VKSyncSettings;
use RuntimeException;
use Tests\Support\FakeImageNetwork;
use Tests\TestCase;

class SyncVkPostsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_converts_links_for_posts_and_reposts_and_preserves_edits(): void
    {
        $text = 'Подробности [https://vk.ru/topic-194587991_46740865|в этом обсуждении]';
        $html = 'Подробности <a href="https://vk.ru/topic-194587991_46740865">в этом обсуждении</a>';
        $postPayload = $this->vkPostPayload(text: $text);
        $repost = $this->vkPostPayload(id: 2, text: 'Комментарий');
        $repost['copy_history'] = [$postPayload];
        $settings = app(VKSyncSettings::class);
        $settings->post_types = ['post', 'copy'];
        $settings->save();
        $this->fakeWallGet([$postPayload, $repost]);
        $this->artisan('vk:sync-posts', ['--domain' => 'testgroup', '--force' => true])->assertExitCode(0);
        $posts = VkPost::orderBy('vk_post_id')->get();
        $this->assertCount(2, $posts);
        foreach ($posts as $post) {
            $this->assertSame($text, $post->text);
            $this->assertSame($html, $post->message_text);
            $post->update(['message_text' => 'Ручная правка']);
        }
        $this->assertEquals($postPayload, $posts[0]->raw);
        $this->assertEquals($repost, $posts[1]->raw);
        $this->artisan('vk:sync-posts', ['--domain' => 'testgroup', '--force' => true])->assertExitCode(0);
        foreach ($posts as $post) {
            $this->assertSame('Ручная правка', $post->refresh()->message_text);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        FakeImageNetwork::install();

        config(['services.vk.access_token' => 'test-token']);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function fakeWallGet(array $items): void
    {
        Http::fake([
            'api.vk.com/method/wall.get*' => Http::response([
                'response' => ['count' => count($items), 'items' => $items],
            ]),
            'sun9-1.userapi.com/*' => Http::response(FakeImageNetwork::jpeg(), 200, ['Content-Type' => 'image/jpeg']),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function vkPostPayload(int $id = 1, int $date = 1_700_000_000, string $text = 'Привет из VK'): array
    {
        return [
            'id' => $id,
            'owner_id' => -12345,
            'date' => $date,
            'text' => $text,
            'post_type' => 'post',
            'attachments' => [
                [
                    'type' => 'photo',
                    'photo' => [
                        'sizes' => [
                            ['type' => 'm', 'width' => 130, 'url' => 'https://sun9-1.userapi.com/small.jpg'],
                            ['type' => 'x', 'width' => 604, 'url' => 'https://sun9-1.userapi.com/photo.jpg'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_download_option_saves_copies_and_dry_run_does_not(): void
    {
        Storage::fake('vk-sync-test');
        config(['media-library.disk_name' => 'vk-sync-test', 'filesystems.disks.vk-sync-test.driver' => 'local']);
        $settings = app(VKSyncSettings::class);
        $settings->download_images = true;
        $settings->save();
        $this->fakeWallGet([$this->vkPostPayload()]);
        $this->artisan('vk:sync-posts', ['--domain' => 'testgroup', '--force' => true, '--dry-run' => true])->assertExitCode(0);
        $this->assertSame([], Storage::disk('vk-sync-test')->allFiles());
        $this->assertDatabaseCount('vk_posts', 0);
        $this->assertDatabaseCount('media', 0);
        $this->artisan('vk:sync-posts', ['--domain' => 'testgroup', '--force' => true])->assertExitCode(0);
        $post = VkPost::sole();
        $this->assertNotNull($post->localImage($post->images[0]));
    }

    public function test_old_ready_posts_get_copies_without_editorial_changes(): void
    {
        Storage::fake('vk-sync-test');
        config(['media-library.disk_name' => 'vk-sync-test', 'filesystems.disks.vk-sync-test.driver' => 'local']);
        $post = VkPost::factory()->ready()->create(['owner_id' => -12345, 'vk_post_id' => 1,
            'message_text' => 'Правки редактора', 'images' => ['https://sun9-1.userapi.com/edited.jpg']]);
        $before = $post->refresh()->getAttributes();
        $settings = app(VKSyncSettings::class);
        $settings->active = true;
        $settings->last_update = 1_700_000_000;
        $settings->download_images = true;
        $settings->save();
        $this->fakeWallGet([$this->vkPostPayload()]);
        $this->artisan('vk:sync-posts', ['--domain' => 'testgroup'])->assertExitCode(0);
        $this->assertSame($before, $post->refresh()->getAttributes());
        $this->assertNotNull($post->localImage($post->images[0]));
    }

    public function test_copy_failure_does_not_roll_back_saved_post(): void
    {
        $settings = app(VKSyncSettings::class);
        $settings->download_images = true;
        $settings->save();
        $this->fakeWallGet([$this->vkPostPayload()]);
        $this->mock(DownloadVkPostImages::class)->shouldReceive('__invoke')->once()->andThrow(new RuntimeException('Disk unavailable'));
        $this->artisan('vk:sync-posts', ['--domain' => 'testgroup', '--force' => true])->assertExitCode(1);
        $this->assertSame('Привет из VK', VkPost::sole()->message_text);
    }

    public function test_fails_without_access_token(): void
    {
        config(['services.vk.access_token' => null]);

        $this->artisan('vk:sync-posts', ['--domain' => 'testgroup', '--force' => true])
            ->assertExitCode(1);
    }

    public function test_creates_draft_post_with_validated_images(): void
    {
        $this->fakeWallGet([$this->vkPostPayload()]);

        $this->artisan('vk:sync-posts', ['--domain' => 'testgroup', '--force' => true])
            ->assertExitCode(0);

        $post = VkPost::query()->sole();

        $this->assertSame(-12345, $post->owner_id);
        $this->assertSame(1, $post->vk_post_id);
        $this->assertSame(VkPostStatus::DRAFT, $post->status);
        // Наибольший по width размер — тот, что попадает в images.
        $this->assertSame(['https://sun9-1.userapi.com/photo.jpg'], $post->images);
        $this->assertCount(0, $post->getMedia(VkPost::IMAGE_COLLECTION));
    }

    public function test_rejected_image_is_dropped_but_post_is_still_saved(): void
    {
        // Отклонённая картинка не мешает сохранению поста.
        $payload = $this->vkPostPayload();
        $payload['attachments'][0]['photo']['sizes'][1]['url'] = 'https://sun9-1.userapi.com/rejected.jpg';

        Http::fake([
            'api.vk.com/method/wall.get*' => Http::response([
                'response' => ['count' => 1, 'items' => [$payload]],
            ]),
            'sun9-1.userapi.com/*' => Http::response('', 404),
        ]);

        $this->artisan('vk:sync-posts', ['--domain' => 'testgroup', '--force' => true])
            ->assertExitCode(0);

        $post = VkPost::query()->sole();

        $this->assertSame([], $post->images);
        $this->assertSame(['https://sun9-1.userapi.com/rejected.jpg'], $post->rejected_images);
    }

    public function test_new_post_seeds_message_text_from_vk_text(): void
    {
        $this->fakeWallGet([$this->vkPostPayload(text: 'Привет из VK')]);

        $this->artisan('vk:sync-posts', ['--domain' => 'testgroup', '--force' => true])
            ->assertExitCode(0);

        $post = VkPost::query()->sole();

        $this->assertSame('Привет из VK', $post->message_text);
    }

    public function test_status_of_existing_draft_is_not_overwritten(): void
    {
        // Http::fake() вызванный повторно с тем же URL-паттерном не
        // переопределяет более ранний стаб (побеждает первый совпавший) —
        // нужен именно Http::sequence(), чтобы два прогона sync реально
        // получили два разных ответа wall.get.
        Http::fake([
            'api.vk.com/method/wall.get*' => Http::sequence()
                ->push(['response' => ['count' => 1, 'items' => [$this->vkPostPayload(text: 'Исходный текст')]]])
                ->push(['response' => ['count' => 1, 'items' => [$this->vkPostPayload(text: 'Текст изменился в VK')]]]),
            'sun9-1.userapi.com/*' => Http::response(FakeImageNetwork::jpeg(), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $this->artisan('vk:sync-posts', ['--domain' => 'testgroup', '--force' => true])->assertExitCode(0);

        $settings = app(VKSyncSettings::class);
        $settings->post_status = VkPostStatus::READY->value;
        $settings->save();

        // Повторная синхронизация того же (всё ещё draft) поста с новым
        // текстом от VK и настройкой post_status = ready.
        $this->artisan('vk:sync-posts', ['--domain' => 'testgroup', '--force' => true])->assertExitCode(0);

        $post = VkPost::query()->sole();

        $this->assertSame(VkPostStatus::DRAFT, $post->status);
        $this->assertSame('Исходный текст', $post->message_text);
        $this->assertSame('Текст изменился в VK', $post->text);
    }

    public function test_does_not_overwrite_a_post_already_reviewed_in_admin(): void
    {
        $this->fakeWallGet([$this->vkPostPayload(text: 'Исходный текст')]);

        $this->artisan('vk:sync-posts', ['--domain' => 'testgroup', '--force' => true])->assertExitCode(0);

        $post = VkPost::query()->sole();
        $post->update(['status' => VkPostStatus::READY, 'message_text' => 'Отредактировано вручную']);

        // Повторная синхронизация того же поста с новым текстом от VK.
        $this->fakeWallGet([$this->vkPostPayload(text: 'Текст изменился в VK')]);
        $this->artisan('vk:sync-posts', ['--domain' => 'testgroup', '--force' => true])->assertExitCode(0);

        $post->refresh();
        $this->assertSame('Исходный текст', $post->text);
        $this->assertSame('Отредактировано вручную', $post->message_text);
    }

    public function test_dry_run_does_not_persist_anything(): void
    {
        $this->fakeWallGet([$this->vkPostPayload()]);

        $this->artisan('vk:sync-posts', ['--domain' => 'testgroup', '--force' => true, '--dry-run' => true])
            ->assertExitCode(0);

        $this->assertSame(0, VkPost::query()->count());
    }

    public function test_last_update_advances_to_the_newest_post_date(): void
    {
        $this->fakeWallGet([
            $this->vkPostPayload(id: 1, date: 1_700_000_000),
            $this->vkPostPayload(id: 2, date: 1_700_000_500),
        ]);

        $this->artisan('vk:sync-posts', ['--domain' => 'testgroup', '--force' => true])->assertExitCode(0);

        $this->assertSame(1_700_000_500, (int) app(VKSyncSettings::class)->last_update);
    }

    public function test_disabled_sync_is_skipped_without_force(): void
    {
        $settings = app(VKSyncSettings::class);
        $settings->active = false;
        $settings->save();

        $this->fakeWallGet([$this->vkPostPayload()]);

        $this->artisan('vk:sync-posts', ['--domain' => 'testgroup'])->assertExitCode(0);

        $this->assertSame(0, VkPost::query()->count());
    }
}
