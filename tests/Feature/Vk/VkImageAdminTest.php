<?php

declare(strict_types=1);

namespace Tests\Feature\Vk;

use App\MoonShine\Models\MoonshinePermission;
use App\MoonShine\Pages\VkSyncSettingsPage;
use Domain\Telegram\Models\TelegramBot;
use Domain\Vk\Enums\VkImageSendMode;
use Domain\Vk\Models\VkPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Infrastructure\Jobs\BroadcastVkPostJob;
use Infrastructure\Settings\VKSyncSettings;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakeImageNetwork;
use Tests\TestCase;

class VkImageAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FakeImageNetwork::install();
        Storage::fake('vk-admin-test');
        config(['media-library.disk_name' => 'vk-admin-test', 'filesystems.disks.vk-admin-test.driver' => 'local']);
        $this->actingAs(MoonshineUser::create(['name' => 'Admin', 'email' => 'vk-images@test.local',
            'password' => bcrypt('password'), 'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID]), 'moonshine');
    }

    public function test_settings_default_to_existing_behavior_and_show_new_controls(): void
    {
        $settings = app(VKSyncSettings::class);
        $this->assertFalse($settings->download_images);
        $this->assertSame('original_url', $settings->image_send_mode);
        $response = $this->get(app(VkSyncSettingsPage::class)->getUrl())->assertOk()
            ->assertSee('Загружать картинки постов на сайт')->assertSee('localhost');
        foreach (VkImageSendMode::cases() as $mode) {
            $response->assertSee($mode->label());
        }
    }

    public static function modes(): array
    {
        return [['original_url'], ['site_url'], ['site_file']];
    }

    #[DataProvider('modes')]
    public function test_settings_save_mode_and_download_switch(string $mode): void
    {
        $url = route('moonshine.method', ['pageUri' => 'vk-sync-settings-page', 'method' => 'save']);
        $payload = ['domain' => 'test', 'count' => 5, 'cron' => '0 * * * *', 'post_status' => 'draft', 'image_send_mode' => $mode];
        $this->postJson($url, [...$payload, 'download_images' => '1'])->assertOk();
        $this->assertTrue(app(VKSyncSettings::class)->download_images);
        $this->assertSame($mode, app(VKSyncSettings::class)->image_send_mode);
        $this->postJson($url, $payload)->assertOk();
        $this->assertFalse(app(VKSyncSettings::class)->download_images);
        $this->postJson($url, [...$payload, 'image_send_mode' => 'invalid'])->assertUnprocessable();
    }

    public static function pages(): array
    {
        return [['vk-post-index-page'], ['vk-post-form-page']];
    }

    #[DataProvider('pages')]
    public function test_file_mode_blocks_broadcast_without_queueing(string $page): void
    {
        Queue::fake();
        Http::fake();
        $settings = app(VKSyncSettings::class);
        $settings->image_send_mode = 'site_file';
        $settings->save();
        foreach ([[], ['https://sun9-1.userapi.com/photo.jpg']] as $images) {
            $post = VkPost::factory()->ready()->create(['images' => $images, 'message_text' => 'Новость']);
            $this->postJson(route('moonshine.method', ['pageUri' => $page,
                'resourceUri' => 'vk-post-resource', 'resourceItem' => $post->id, 'method' => 'broadcastAll']))
                ->assertOk()->assertJson(['message' => BroadcastVkPostJob::FILE_MODE_ERROR, 'messageType' => 'error']);
        }
        Queue::assertNothingPushed();
        Http::assertNothingSent();
        $this->assertSame('site_file', $settings->image_send_mode);
    }

    #[DataProvider('pages')]
    public function test_url_modes_can_queue_broadcast(string $page): void
    {
        Queue::fake();
        TelegramBot::create(['token' => 'test-token', 'name' => 'Test']);
        $settings = app(VKSyncSettings::class);
        foreach (['original_url', 'site_url'] as $mode) {
            $post = VkPost::factory()->ready()->create(['message_text' => 'Новость']);
            $settings->image_send_mode = $mode;
            $settings->save();
            $this->postJson(route('moonshine.method', ['pageUri' => $page,
                'resourceUri' => 'vk-post-resource', 'resourceItem' => $post->id, 'method' => 'broadcastAll']))
                ->assertOk()->assertJson(['messageType' => 'success']);
        }
        Queue::assertPushed(BroadcastVkPostJob::class, 2);
    }

    #[DataProvider('pages')]
    public function test_download_button_and_previews(string $page): void
    {
        $image = 'https://sun9-1.userapi.com/photo.jpg';
        $post = VkPost::factory()->create(['images' => [$image]]);
        Http::fake(['sun9-1.userapi.com/*' => Http::response(FakeImageNetwork::jpeg())]);
        $response = $this->postJson(route('moonshine.method', ['pageUri' => $page,
            'resourceUri' => 'vk-post-resource', 'resourceItem' => $post->id, 'method' => 'downloadImages']))
            ->assertOk()->assertJson(['message' => 'Картинки загружены на сайт: 1 / 1']);
        if ($page === 'vk-post-form-page') {
            $this->assertStringContainsString('vk-post-form-page', $response->json('redirect'));
            $this->assertStringContainsString((string) $post->id, $response->json('redirect'));
        }
        $mediaUrl = $post->localImage($image)->getUrl();
        $this->get('/admin/resource/vk-post-resource/vk-post-index-page')->assertOk()->assertSee($mediaUrl, false)->assertSee('1 / 1');
        $this->get('/admin/resource/vk-post-resource/vk-post-form-page/'.$post->id)->assertOk()->assertSee('Локальные копии')->assertSee($mediaUrl, false);
        $this->get('/admin/resource/vk-post-resource/vk-post-detail-page/'.$post->id)->assertOk()->assertSee('Исходные ссылки VK')->assertSee($mediaUrl, false);
    }

    public function test_staff_without_update_permission_cannot_download(): void
    {
        $role = MoonshineUserRole::create(['name' => 'Read only']);
        $staff = MoonshineUser::create(['name' => 'Staff', 'email' => 'staff-vk@test.local', 'password' => bcrypt('password'), 'moonshine_user_role_id' => $role->id]);
        MoonshinePermission::create(['moonshine_user_role_id' => $role->id, 'model' => VkPost::class,
            'permissions' => ['viewAny' => true, 'view' => true, 'update' => false]]);
        $post = VkPost::factory()->create(['images' => ['https://sun9-1.userapi.com/photo.jpg']]);
        Http::fake();
        $this->actingAs($staff, 'moonshine')->postJson(route('moonshine.method', ['pageUri' => 'vk-post-index-page',
            'resourceUri' => 'vk-post-resource', 'resourceItem' => $post->id, 'method' => 'downloadImages']))->assertForbidden();
        Http::assertNothingSent();
        $this->assertCount(0, $post->getMedia(VkPost::IMAGE_COLLECTION));
    }
}
