<?php

declare(strict_types=1);

namespace Tests\Feature\Vk;

use App\MoonShine\Resources\VkPost\Pages\VkPostIndexPage;
use Domain\Auth\Models\User;
use Domain\Logging\Models\EventLog;
use Domain\Telegram\Models\TelegramBot;
use Domain\Telegram\Models\TelegramChat;
use Domain\Vk\Actions\DownloadVkPostImages;
use Domain\Vk\Actions\SendVkPostToChatAction;
use Domain\Vk\Enums\VkPostStatus;
use Domain\Vk\Models\VkPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Infrastructure\Jobs\BroadcastVkPostJob;
use Infrastructure\Settings\VKSyncSettings;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\FakeImageNetwork;
use Tests\TestCase;

class VkPostImagesTest extends TestCase
{
    use RefreshDatabase;

    private const string IMAGE = 'https://sun9-1.userapi.com/photo.jpg';

    private VkPost $post;

    private TelegramChat $chat;

    protected function setUp(): void
    {
        parent::setUp();
        FakeImageNetwork::install();
        Storage::fake('vk-test');
        config(['media-library.disk_name' => 'vk-test', 'filesystems.disks.vk-test.url' => 'https://site.test/media']);
        $this->post = VkPost::factory()->ready()->create(['images' => [self::IMAGE], 'message_text' => 'Новость']);
        $bot = TelegramBot::create(['token' => 'test-token', 'name' => 'Test']);
        $this->chat = TelegramChat::create(['chat_id' => '123', 'telegraph_bot_id' => $bot->id]);
    }

    private function fakeImages(): void
    {
        Http::fake([
            'sun9-1.userapi.com/*' => Http::response(FakeImageNetwork::jpeg()),
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
        ]);
    }

    private function mode(string $mode): void
    {
        $settings = app(VKSyncSettings::class);
        $settings->image_send_mode = $mode;
        $settings->save();
    }

    public function test_copies_are_reused_and_missing_files_restored(): void
    {
        $this->fakeImages();
        $download = app(DownloadVkPostImages::class);
        $download($this->post);
        $media = $this->post->localImage(self::IMAGE);
        $this->assertNotNull($media);
        $this->assertSame('image/webp', $media->mime_type);
        $this->assertStringEndsWith('.webp', $media->file_name);
        $this->assertSame('image/webp', getimagesize($media->getPath())['mime']);
        $this->assertSame(self::IMAGE, $media->getCustomProperty('source_url'));
        Storage::disk('vk-test')->assertExists($media->getPathRelativeToRoot());
        $download($this->post);
        Http::assertSentCount(1);
        $this->assertSame($media->id, $this->post->localImage(self::IMAGE)->id);
        Storage::disk('vk-test')->delete($media->getPathRelativeToRoot());
        $download($this->post);
        Http::assertSentCount(2);
        $this->assertCount(1, $this->post->getMedia(VkPost::IMAGE_COLLECTION));
        Storage::disk('vk-test')->assertExists($this->post->localImage(self::IMAGE)->getPathRelativeToRoot());
    }

    public function test_partial_failure_keeps_good_copy_and_logs_bad_image(): void
    {
        $bad = 'https://sun9-1.userapi.com/bad.jpg?signature=private';
        $this->post->update(['images' => [self::IMAGE, $bad]]);
        Http::fake([
            self::IMAGE => Http::response(FakeImageNetwork::jpeg()),
            $bad => Http::sequence()->push('', 404)->push(FakeImageNetwork::jpeg()),
        ]);
        $before = glob(sys_get_temp_dir().'/remote-image-*');
        try {
            app(DownloadVkPostImages::class)($this->post);
            $this->fail('Expected download failure');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Не удалось загрузить', $e->getMessage());
        }
        $this->assertNotNull($this->post->localImage(self::IMAGE));
        $this->assertNull($this->post->localImage($bad));
        $log = EventLog::where('event_type', 'vk_post.image_download_failed')->sole();
        $this->assertSame($this->post->id, $log->context['vk_post_id']);
        $this->assertStringNotContainsString('private', $log->toJson());
        $this->assertSame($before, glob(sys_get_temp_dir().'/remote-image-*'));
        app(DownloadVkPostImages::class)($this->post);
        $this->assertCount(2, $this->post->getMedia(VkPost::IMAGE_COLLECTION));
        Http::assertSentCount(3);
    }

    public function test_png_transparency_and_dimensions_survive_webp_conversion(): void
    {
        $image = imagecreatetruecolor(20, 12);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 20, 40, 60, 127));
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);
        Http::fake([self::IMAGE => Http::response($png)]);
        app(DownloadVkPostImages::class)($this->post);
        $path = $this->post->localImage(self::IMAGE)->getPath();
        $webp = imagecreatefromwebp($path);
        try {
            $this->assertSame(20, imagesx($webp));
            $this->assertSame(12, imagesy($webp));
            $this->assertSame(127, imagecolorsforindex($webp, imagecolorat($webp, 0, 0))['alpha']);
        } finally {
            imagedestroy($webp);
        }
    }

    public function test_existing_webp_source_is_not_recompressed(): void
    {
        $image = imagecreatetruecolor(20, 12);
        ob_start();
        imagewebp($image, null, 90);
        $webp = ob_get_clean();
        imagedestroy($image);
        Http::fake([self::IMAGE => Http::response($webp)]);
        app(DownloadVkPostImages::class)($this->post);
        $this->assertSame($webp, file_get_contents($this->post->localImage(self::IMAGE)->getPath()));
    }

    public function test_unselected_copies_are_retained_until_post_is_deleted(): void
    {
        $this->fakeImages();
        app(DownloadVkPostImages::class)($this->post);
        $path = $this->post->localImage(self::IMAGE)->getPathRelativeToRoot();
        $this->post->update(['images' => []]);
        $this->assertSame([], $this->post->localImageUrls());
        $this->assertSame('0 / 0', $this->post->imageDownloadStatus());
        Storage::disk('vk-test')->assertExists($path);
        $this->post->delete();
        Storage::disk('vk-test')->assertMissing($path);
    }

    public static function modes(): array
    {
        return [
            ['original_url', 1], ['original_url', 2],
            ['site_url', 1], ['site_url', 2],
            ['site_file', 1], ['site_file', 2],
        ];
    }

    #[DataProvider('modes')]
    public function test_sends_selected_mode_in_order_with_caption(string $mode, int $count): void
    {
        $this->mode($mode);
        $this->fakeImages();
        $urls = array_slice([self::IMAGE, 'https://sun9-1.userapi.com/second.jpg'], 0, $count);
        $this->post->update(['images' => $urls]);
        $admin = MoonshineUser::create(['name' => 'Admin', 'email' => 'image-log@test.local',
            'password' => bcrypt('password'), 'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID]);
        $this->actingAs($admin, 'moonshine');
        request()->setUserResolver(fn () => $admin);
        $this->chat->update(['moonshine_user_id' => $admin->id]);
        $response = VkPostIndexPage::sendTestMessage($this->post, app(SendVkPostToChatAction::class));
        $this->assertSame('Тестовое сообщение отправлено.', json_decode($response->getContent(), true)['message']);
        $log = EventLog::where('event_type', 'vk_post.test_send')->sole();
        $this->assertSame($mode, $log->context['image_send_mode']);
        $this->assertSame($mode === 'original_url' ? $urls : $this->post->fresh()->localImageUrls(), $log->context['image_urls']);
        $this->post->unsetRelation('media');
        $this->assertCount($mode === 'original_url' ? 0 : $count, $this->post->getMedia(VkPost::IMAGE_COLLECTION));
        $requests = Http::recorded(fn (Request $request) => str_contains($request->url(), 'api.telegram.org'));
        $this->assertCount(1, $requests);
        $request = $requests->first()[0];
        $this->assertStringContainsString($count === 1 ? 'sendPhoto' : 'sendMediaGroup', $request->url());
        if ($mode === 'site_file') {
            $this->assertTrue($request->hasHeader('Content-Type', 'multipart/form-data') || str_contains($request->header('Content-Type')[0] ?? '', 'multipart/form-data'));
            foreach ($urls as $index => $url) {
                $this->assertStringContainsString(hash('sha256', $url).'.webp', $request->body());
                if ($count > 1) {
                    $this->assertStringContainsString(json_encode('attach://photo_'.$index), $request->body());
                }
            }
            $this->assertStringNotContainsString('https://sun9-1', $request->body());
            $this->assertStringContainsString($count === 1 ? 'Новость' : trim(json_encode('Новость'), '"'), $request->body());
        } else {
            $expected = $mode === 'original_url' ? $urls : $this->post->localImageUrls();
            if ($count === 1) {
                $this->assertSame($expected[0], $request['photo']);
                $this->assertStringContainsString('Новость', $request['caption']);
            } else {
                $this->assertSame($expected, array_column($request['media'], 'media'));
                $this->assertStringContainsString('Новость', $request['media'][0]['caption']);
            }
        }
    }

    public function test_preparation_failure_never_contacts_telegram(): void
    {
        $this->mode('site_file');
        Http::fake(['sun9-1.userapi.com/*' => Http::response('', 404)]);
        try {
            app(SendVkPostToChatAction::class)($this->post, $this->chat);
            $this->fail('Expected preparation failure');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Не удалось загрузить', $e->getMessage());
        }
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'api.telegram.org'));
    }

    public function test_broadcast_prepares_once_and_records_preparation_failure_without_marking_sent(): void
    {
        $this->mode('site_url');
        Http::fake(['sun9-1.userapi.com/*' => Http::response('', 404)]);
        $user = User::factory()->telegram()->create();
        $this->chat->update(['user_id' => $user->id]);
        app()->call([new BroadcastVkPostJob($this->post->id), 'handle']);
        $this->assertSame(VkPostStatus::READY, $this->post->refresh()->status);
        $this->assertNull($this->post->broadcast_at);
        $this->assertSame(0, $this->post->deliveries()->count());
        $this->assertDatabaseHas('event_logs', ['event_type' => 'vk_post.broadcast_preparation_failed']);
        Http::assertSentCount(1);
    }

    public function test_broadcast_downloads_once_for_multiple_recipients(): void
    {
        $this->mode('site_url');
        $this->fakeImages();
        $download = app(DownloadVkPostImages::class);
        $this->mock(DownloadVkPostImages::class)->shouldReceive('__invoke')->once()
            ->andReturnUsing(fn (VkPost $post, array $urls) => $download($post, $urls));
        $this->chat->update(['user_id' => User::factory()->telegram()->create()->id]);
        TelegramChat::create(['chat_id' => '456', 'telegraph_bot_id' => $this->chat->telegraph_bot_id,
            'user_id' => User::factory()->telegram()->create()->id]);
        app()->call([new BroadcastVkPostJob($this->post->id), 'handle']);
        $this->assertSame(2, $this->post->deliveries()->where('status', 'sent')->count());
        $log = EventLog::where('event_type', 'vk_post.broadcast')->sole();
        $this->assertSame('site_url', $log->context['image_send_mode']);
        $this->assertSame($this->post->fresh()->localImageUrls(), $log->context['image_urls']);
        Http::assertSentCount(3);
    }

    public function test_queued_broadcast_rechecks_file_mode_before_preparing_images(): void
    {
        Http::fake();
        $this->mock(DownloadVkPostImages::class)->shouldNotReceive('__invoke');
        foreach ([[], [self::IMAGE]] as $images) {
            $this->mode('original_url');
            $post = VkPost::factory()->ready()->create(['images' => $images, 'message_text' => 'Новость']);
            $job = (new BroadcastVkPostJob($post->id))->withFakeQueueInteractions();
            $before = $post->refresh()->getAttributes();
            $this->mode('site_file');
            app()->call([$job, 'handle']);
            $job->assertFailedWith(RuntimeException::class);
            $this->assertSame($before, $post->refresh()->getAttributes());
            $this->assertSame(0, $post->deliveries()->count());
        }
        Http::assertNothingSent();
        $logs = EventLog::where('event_type', 'vk_post.broadcast_preparation_failed')->get();
        $this->assertCount(2, $logs);
        $this->assertSame(BroadcastVkPostJob::FILE_MODE_ERROR, $logs->first()->context['error']);
    }

    public static function longPostResults(): array
    {
        $cases = [];
        foreach (['site_url', 'site_file'] as $mode) {
            foreach ([1, 2] as $count) {
                foreach ([[false, true, 1], [true, false, 2], [true, true, 2]] as [$mediaOk, $textOk, $requests]) {
                    $cases[] = [$mode, $count, $mediaOk, $textOk, $requests];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('longPostResults')]
    public function test_long_text_respects_media_and_text_results(string $mode, int $count, bool $mediaOk, bool $textOk, int $requests): void
    {
        $this->mode($mode);
        $this->post->update(['message_text' => str_repeat('Новость ', 160),
            'images' => array_slice([self::IMAGE, 'https://sun9-1.userapi.com/second.jpg'], 0, $count)]);
        Http::fake([
            'sun9-1.userapi.com/*' => Http::response(FakeImageNetwork::jpeg()),
            'api.telegram.org/*' => Http::sequence()
                ->push(['ok' => $mediaOk, 'description' => 'Media error'])
                ->push(['ok' => $textOk, 'description' => 'Text error']),
        ]);
        $response = app(SendVkPostToChatAction::class)($this->post, $this->chat);
        $this->assertSame($mediaOk && $textOk, $response->telegraphOk());
        $telegramRequests = Http::recorded(fn (Request $request) => str_contains($request->url(), 'api.telegram.org'))->values();
        $this->assertCount($requests, $telegramRequests);
        $this->assertStringNotContainsString('Новость', $telegramRequests[0][0]->body());
        if ($requests === 2) {
            $this->assertStringContainsString('sendMessage', $telegramRequests[1][0]->url());
            $this->assertStringContainsString('Новость', $telegramRequests[1][0]['text']);
        }
    }

    public function test_prepared_mode_and_order_are_stable_when_settings_change(): void
    {
        $this->mode('site_file');
        $this->fakeImages();
        $this->post->update(['images' => array_map(fn ($i) => 'https://sun9-1.userapi.com/'.$i.'.jpg', range(1, 11))]);
        $send = app(SendVkPostToChatAction::class);
        $prepared = $send->prepare($this->post);
        $this->assertCount(10, $prepared->images);
        $this->assertStringContainsString(hash('sha256', $this->post->images[0]), $prepared->images[0]);
        $this->mode('original_url');
        $this->assertTrue($send($this->post, $this->chat, $prepared)->telegraphOk());
        $request = Http::recorded(fn (Request $request) => str_contains($request->url(), 'api.telegram.org'))->first()[0];
        $this->assertStringContainsString(json_encode('attach://photo_9'), $request->body());
        $this->assertStringNotContainsString('photo_10', $request->body());
    }
}
