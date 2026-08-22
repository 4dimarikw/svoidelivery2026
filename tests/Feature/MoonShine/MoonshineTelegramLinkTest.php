<?php

declare(strict_types=1);

namespace Tests\Feature\MoonShine;

use App\MoonShine\Support\MoonshineTelegramLink;
use DefStudio\Telegraph\Facades\Telegraph;
use Domain\Telegram\Models\TelegramBot;
use Domain\Telegram\Models\TelegramChat;
use Domain\Vk\Enums\VkPostStatus;
use Domain\Vk\Models\VkPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

/**
 * Привязка Telegram к админу MoonShine — тот же диплинк-механизм
 * (Domain\Telegram\Support\TelegramLinkCode → /start → WebhookHandler),
 * что и у сайтового User (см. tests/Feature/Auth/TelegramLoginTest.php),
 * но с субъектом SUBJECT_MOONSHINE_USER вместо SUBJECT_USER — и с
 * отдельной колонкой moonshine_user_id в telegraph_chats.
 */
class MoonshineTelegramLinkTest extends TestCase
{
    use RefreshDatabase;

    private TelegramBot $bot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bot = TelegramBot::create(['token' => 'test-bot-token', 'name' => 'Test Bot', 'username' => 'svoi_test_bot']);
    }

    private function admin(string $email = 'admin@test.local'): MoonshineUser
    {
        return MoonshineUser::query()->create([
            'email' => $email,
            'password' => bcrypt('password'),
            'name' => 'Admin',
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function startUpdatePayload(string $code, int $telegramId = 555000111): array
    {
        return [
            'update_id' => 1,
            'message' => [
                'message_id' => 1,
                'date' => now()->timestamp,
                'text' => "/start {$code}",
                'chat' => ['id' => (string) $telegramId, 'type' => 'private'],
                'from' => ['id' => $telegramId, 'first_name' => 'Админ', 'username' => 'admin_tg'],
            ],
        ];
    }

    // ---- Диплинк /start (привязка админа MoonShine) --------------------

    public function test_start_with_a_valid_admin_code_links_telegram_to_the_moonshine_user(): void
    {
        Telegraph::fake();

        $admin = $this->admin();
        $code = MoonshineTelegramLink::issueCode($admin->id);

        $this->postJson(route('telegraph.webhook', ['token' => $this->bot->token]), $this->startUpdatePayload($code))
            ->assertNoContent();

        $chat = TelegramChat::query()->where('chat_id', '555000111')->sole();

        $this->assertSame($admin->id, $chat->moonshine_user_id);
        $this->assertNull($chat->user_id);
        Telegraph::assertSent(__('telegram.linked_admin'));
    }

    public function test_admin_code_is_single_use(): void
    {
        Telegraph::fake();

        $first = $this->admin('first@test.local');
        $second = $this->admin('second@test.local');
        $code = MoonshineTelegramLink::issueCode($first->id);

        $this->postJson(route('telegraph.webhook', ['token' => $this->bot->token]), $this->startUpdatePayload($code));
        $this->postJson(route('telegraph.webhook', ['token' => $this->bot->token]), $this->startUpdatePayload($code, telegramId: 777));

        $this->assertSame($first->id, TelegramChat::query()->where('chat_id', '555000111')->sole()->moonshine_user_id);
        $this->assertSame(0, TelegramChat::query()->where('chat_id', '777')->count());
        $this->assertNotSame($second->id, $first->id);
    }

    public function test_relinking_from_a_different_chat_does_not_break_the_unique_index(): void
    {
        Telegraph::fake();

        $admin = $this->admin();

        $firstCode = MoonshineTelegramLink::issueCode($admin->id);
        $this->postJson(route('telegraph.webhook', ['token' => $this->bot->token]), $this->startUpdatePayload($firstCode, telegramId: 1));

        $secondCode = MoonshineTelegramLink::issueCode($admin->id);
        $this->postJson(route('telegraph.webhook', ['token' => $this->bot->token]), $this->startUpdatePayload($secondCode, telegramId: 2))
            ->assertNoContent();

        $this->assertNull(TelegramChat::query()->where('chat_id', '1')->sole()->moonshine_user_id);
        $this->assertSame($admin->id, TelegramChat::query()->where('chat_id', '2')->sole()->moonshine_user_id);
    }

    // ---- Отвязка ---------------------------------------------------------

    public function test_unlink_clears_moonshine_user_id_but_keeps_the_chat_row(): void
    {
        $admin = $this->admin();
        $chat = TelegramChat::create(['chat_id' => '1', 'telegraph_bot_id' => $this->bot->id, 'moonshine_user_id' => $admin->id]);

        $this->actingAs($admin, 'moonshine')
            ->postJson(route('moonshine.method', ['pageUri' => 'profile-page', 'method' => 'unlinkTelegram']))
            ->assertOk();

        $chat->refresh();
        $this->assertNull($chat->moonshine_user_id);
        $this->assertNotNull(TelegramChat::find($chat->id));
    }

    // ---- Страница профиля -------------------------------------------------

    public function test_profile_page_shows_the_link_button_when_not_linked(): void
    {
        $this->actingAs($this->admin(), 'moonshine')
            ->get('/admin/page/profile-page')
            ->assertOk()
            ->assertSee('Привязать Telegram');
    }

    public function test_profile_page_shows_the_unlink_button_when_linked(): void
    {
        $admin = $this->admin();
        TelegramChat::create(['chat_id' => '1', 'telegraph_bot_id' => $this->bot->id, 'moonshine_user_id' => $admin->id]);

        $this->actingAs($admin, 'moonshine')
            ->get('/admin/page/profile-page')
            ->assertOk()
            ->assertSee('Отвязать');
    }

    // ---- «Отправить себе» на VK-постах ------------------------------------

    private function makePost(): VkPost
    {
        return VkPost::query()->create([
            'owner_id' => -1,
            'vk_post_id' => 1,
            'post_type' => 'post',
            'posted_at' => now(),
            'text' => 'Новость',
            'message_text' => 'Новость',
            'images' => [],
            'raw' => [],
            'status' => VkPostStatus::READY,
        ]);
    }

    public function test_send_to_self_uses_the_clicking_admins_own_chat(): void
    {
        Telegraph::fake();

        $admin = $this->admin();
        TelegramChat::create(['chat_id' => '1', 'telegraph_bot_id' => $this->bot->id, 'moonshine_user_id' => $admin->id]);
        $post = $this->makePost();

        $this->actingAs($admin, 'moonshine')
            ->postJson(route('moonshine.method', [
                'pageUri' => 'vk-post-index-page',
                'resourceUri' => 'vk-post-resource',
                'method' => 'sendToSelf',
                'resourceItem' => $post->id,
            ]))
            ->assertOk()
            ->assertJson(['message' => 'Тестовое сообщение отправлено.']);
    }

    public function test_send_to_self_errors_when_the_admin_has_not_linked_telegram(): void
    {
        Telegraph::fake();

        $admin = $this->admin();
        $post = $this->makePost();

        $this->actingAs($admin, 'moonshine')
            ->postJson(route('moonshine.method', [
                'pageUri' => 'vk-post-index-page',
                'resourceUri' => 'vk-post-resource',
                'method' => 'sendToSelf',
                'resourceItem' => $post->id,
            ]))
            ->assertOk()
            ->assertJson(['message' => 'Привяжите Telegram на странице профиля.']);

        Telegraph::assertNothingSent();
    }
}
