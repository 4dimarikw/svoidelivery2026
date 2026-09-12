<?php

declare(strict_types=1);

namespace Tests\Feature\Vk;

use App\Events\VkPostTestSend;
use App\MoonShine\Resources\VkPost\Pages\VkPostIndexPage;
use Domain\Auth\Models\User;
use Domain\Logging\Models\EventLog;
use Domain\Telegram\Models\TelegramBot;
use Domain\Telegram\Models\TelegramChat;
use Domain\Vk\Actions\SendVkPostToChatAction;
use Domain\Vk\Models\VkPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Infrastructure\Settings\EventLoggingSettings;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class VkPostTestSendTest extends TestCase
{
    use RefreshDatabase;

    public static function linkMessages(): array
    {
        return [
            ['[https://vk.ru/topic-1_2|Обсуждение]', false],
            ['<a href="https://vk.ru/topic-1_2">Обсуждение</a>', false],
            ['[https://vk.ru/topic-1_2|Обсуждение]', true],
            ['<a href="https://vk.ru/topic-1_2">Обсуждение</a>', true],
            ["  <b>Новость</b> &amp; [id123|Имя]\n\n", false],
            ["  <b>Новость</b> &amp; [club123|Группа]\n\n", true],
        ];
    }

    #[DataProvider('linkMessages')]
    public function test_telegram_receives_exact_saved_text_in_message_and_photo_caption(string $text, bool $photo): void
    {
        $this->post->update(['message_text' => $text, 'images' => $photo ? ['https://example.test/a.jpg'] : []]);
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
        $this->send();
        $request = Http::recorded()->sole()[0];
        $this->assertSame($text, $request[$photo ? 'caption' : 'text']);
        $this->assertSame('html', strtolower($request['parse_mode']));
        $this->assertResult('sent', 'info');
    }

    private VkPost $post;

    private MoonshineUser $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->admin = MoonshineUser::query()->create([
            'email' => 'vk-test-send@test.local',
            'password' => bcrypt('password'),
            'name' => 'Test Admin',
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
        ]);
        $this->actingAs($this->admin, 'moonshine');
        request()->setUserResolver(fn () => $this->admin);
        $bot = TelegramBot::create(['token' => 'test-bot-token', 'name' => 'Test Bot']);
        TelegramChat::create([
            'chat_id' => '123',
            'telegraph_bot_id' => $bot->id,
            'moonshine_user_id' => $this->admin->id,
        ]);
        $this->post = VkPost::factory()->ready()->create(['message_text' => 'Тестовая новость']);
    }

    private function send(): void
    {
        $before = $this->post->refresh()->getAttributes();
        VkPostIndexPage::sendTestMessage($this->post, app(SendVkPostToChatAction::class));
        $this->assertSame($before, $this->post->refresh()->getAttributes());
        $this->assertSame(0, $this->post->deliveries()->count());
    }

    private function assertResult(string $result, string $level, ?string $error = null): EventLog
    {
        $log = EventLog::query()->where('event_type', 'vk_post.test_send')->sole();
        $this->assertSame($level, $log->level);
        $this->assertSame($result, $log->context['result']);
        $this->assertSame($this->post->id, $log->context['vk_post_id']);
        $this->assertSame($error, $log->context['error']);
        $this->assertNull($log->caused_by_user_id);
        $this->assertSame($this->admin->id, $log->context['admin_id']);
        $this->assertSame('admin', $log->caused_by_type);
        $this->assertArrayHasKey('correlation_id', $log->context);
        $this->assertStringNotContainsString('Тестовая новость', json_encode($log->context, JSON_UNESCAPED_UNICODE));

        return $log;
    }

    public function test_success_is_logged(): void
    {
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
        $this->send();
        $log = $this->assertResult('sent', 'info');
        $this->assertSame("Тестовое сообщение поста VK #{$this->post->id} отправлено.", $log->message);
        Http::assertSentCount(1);
    }

    public function test_telegram_error_is_logged(): void
    {
        Http::fake(['*' => Http::response(['ok' => false, 'description' => 'Chat not found'], 400)]);
        $this->send();
        $this->assertResult('failed', 'error', 'Chat not found');
    }

    public function test_http_error_without_description_is_logged(): void
    {
        Http::fake(['*' => Http::response([], 500)]);
        $this->send();
        $this->assertResult('failed', 'error', 'HTTP 500');
    }

    public function test_exception_is_logged_and_secrets_are_redacted(): void
    {
        Http::fake(fn () => throw new RuntimeException('Connection failed token=private-secret'));
        $this->send();
        $log = EventLog::query()->where('event_type', 'vk_post.test_send')->sole();
        $this->assertSame('error', $log->level);
        $this->assertSame('failed', $log->context['result']);
        $this->assertStringContainsString('Connection failed', $log->message);
        $this->assertStringNotContainsString('private-secret', $log->toJson());
    }

    public function test_empty_text_is_logged_without_sending(): void
    {
        Http::fake();
        $this->post->update(['message_text' => ' ']);
        $this->send();
        $this->assertResult('skipped', 'warning', 'У поста нет текста для отправки.');
        Http::assertNothingSent();
    }

    public function test_missing_telegram_is_logged_without_sending(): void
    {
        Http::fake();
        TelegramChat::query()->delete();
        $this->send();
        $this->assertResult('skipped', 'warning', 'Привяжите Telegram на странице профиля.');
        Http::assertNothingSent();
    }

    public function test_disabled_event_is_not_logged_but_message_is_sent(): void
    {
        $settings = app(EventLoggingSettings::class);
        $settings->disabled_event_types = ['vk_post.test_send'];
        $settings->save();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
        $this->send();
        $this->assertDatabaseMissing('event_logs', ['event_type' => 'vk_post.test_send']);
        Http::assertSentCount(1);
    }

    public function test_site_user_attribution_is_preserved(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');
        event(new VkPostTestSend($this->post->id, 'sent'));
        $log = EventLog::query()->where('event_type', 'vk_post.test_send')->sole();
        $this->assertSame($user->id, $log->caused_by_user_id);
        $this->assertSame('user', $log->caused_by_type);
        $this->assertArrayNotHasKey('admin_id', $log->context);
    }

    public static function mediaResults(): array
    {
        return [
            'photo fails' => [1, false, true, 1, 'failed'],
            'album fails' => [2, false, true, 1, 'failed'],
            'text after photo fails' => [1, true, false, 2, 'failed'],
            'text after album fails' => [2, true, false, 2, 'failed'],
            'photo and text succeed' => [1, true, true, 2, 'sent'],
            'album and text succeed' => [2, true, true, 2, 'sent'],
        ];
    }

    #[DataProvider('mediaResults')]
    public function test_long_posts_require_all_parts_to_succeed(int $images, bool $mediaOk, bool $textOk, int $requests, string $result): void
    {
        $this->post->update([
            'message_text' => str_repeat('Текст ', 200),
            'images' => array_slice(['https://example.test/a.jpg', 'https://example.test/b.jpg'], 0, $images),
        ]);
        Http::fake(['*' => Http::sequence()
            ->push(['ok' => $mediaOk, 'description' => 'Media failed', 'result' => []])
            ->push(['ok' => $textOk, 'description' => 'Text failed', 'result' => []])]);
        $this->send();
        $this->assertResult($result, $result === 'sent' ? 'info' : 'error',
            ! $mediaOk ? 'Media failed' : (! $textOk ? 'Text failed' : null));
        $this->assertSame($this->post->images, EventLog::where('event_type', 'vk_post.test_send')->sole()->context['image_urls']);
        Http::assertSentCount($requests);
    }
}
