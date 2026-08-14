<?php

namespace Tests\Feature\Telegram;

use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Telegraph as TelegraphClient;
use Domain\Auth\Models\User;
use Domain\Profile\Models\Profile;
use Domain\Telegram\Actions\CheckTelegramBotAvailabilityAction;
use Domain\Telegram\Models\TelegramBot;
use Domain\Telegram\Models\TelegramChat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CheckTelegramBotAvailabilityAction шлёт sendChatAction(TYPING) — самый
 * дешёвый запрос, дающий те же коды ошибок доступа, что и sendMessage
 * (см. докблок Action). Telegraph::fake() перехватывает исходящий запрос;
 * ответ по умолчанию для неучтённых в FakesRequests эндпоинтов — ['ok' =>
 * true], поэтому "успех" не требует явного replies-оверрайда, а "отказ"
 * подставляется через Telegraph::fake([...ENDPOINT_SEND_CHAT_ACTION => ...]).
 */
class CheckTelegramBotActivityTest extends TestCase
{
    use RefreshDatabase;

    private TelegramBot $bot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bot = TelegramBot::create([
            'token' => 'test-bot-token',
            'name' => 'Test Bot',
            'username' => 'svoi_test_bot',
        ]);
    }

    private function createLinkedUser(): User
    {
        $user = User::factory()->telegram()->create();

        TelegramChat::create([
            'chat_id' => '1',
            'telegraph_bot_id' => $this->bot->id,
            'user_id' => $user->id,
        ]);

        return $user;
    }

    public function test_a_reachable_chat_is_marked_active(): void
    {
        Telegraph::fake();

        $user = $this->createLinkedUser();

        $result = app(CheckTelegramBotAvailabilityAction::class)($user);

        $this->assertTrue($result);

        $profile = $user->profile()->sole();
        $this->assertTrue($profile->is_bot_active);
        $this->assertNull($profile->last_bot_error);
        $this->assertNotNull($profile->bot_checked_at);
    }

    public function test_a_blocked_bot_is_marked_inactive_with_the_error(): void
    {
        Telegraph::fake([
            TelegraphClient::ENDPOINT_SEND_CHAT_ACTION => [
                'ok' => false,
                'description' => 'Forbidden: bot was blocked by the user',
            ],
        ]);

        $user = $this->createLinkedUser();

        $result = app(CheckTelegramBotAvailabilityAction::class)($user);

        $this->assertFalse($result);

        $profile = $user->profile()->sole();
        $this->assertFalse($profile->is_bot_active);
        $this->assertSame('Forbidden: bot was blocked by the user', $profile->last_bot_error);
        $this->assertNotNull($profile->bot_checked_at);
    }

    public function test_a_user_without_a_telegram_chat_is_marked_inactive(): void
    {
        Telegraph::fake();

        $user = User::factory()->create();

        $result = app(CheckTelegramBotAvailabilityAction::class)($user);

        $this->assertFalse($result);
        $this->assertSame('Telegram не привязан', $user->profile()->sole()->last_bot_error);
    }

    public function test_it_creates_a_profile_row_when_none_exists_yet(): void
    {
        Telegraph::fake();

        $user = $this->createLinkedUser();
        $user->profile()->delete();
        $user->unsetRelation('profile');

        app(CheckTelegramBotAvailabilityAction::class)($user);

        $this->assertInstanceOf(Profile::class, $user->profile()->sole());
    }

    public function test_the_command_only_checks_users_with_a_linked_telegram_chat(): void
    {
        Telegraph::fake();

        $linked = $this->createLinkedUser();
        $unlinked = User::factory()->create();

        $this->artisan('telegram:check-activity', ['--delay' => 0])
            ->assertSuccessful();

        $this->assertNotNull($linked->profile()->sole()->bot_checked_at);
        $this->assertNull($unlinked->profile?->bot_checked_at);
    }
}
