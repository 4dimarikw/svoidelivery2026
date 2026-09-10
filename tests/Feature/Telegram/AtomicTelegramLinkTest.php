<?php

namespace Tests\Feature\Telegram;

use DefStudio\Telegraph\Facades\Telegraph;
use Domain\Auth\Models\User;
use Domain\Telegram\Actions\LinkTelegramAccount;
use Domain\Telegram\Actions\UnlinkTelegramAccount;
use Domain\Telegram\Models\TelegramBot;
use Domain\Telegram\Models\TelegramChat;
use Domain\Telegram\Support\TelegramLinkCode;
use Domain\Telegram\Support\TelegramLinkResult as Result;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use MoonShine\Laravel\Models\MoonshineUser;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class AtomicTelegramLinkTest extends TestCase
{
    use RefreshDatabase;

    private TelegramBot $bot;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Mail::fake();
        Telegraph::fake();
        $this->bot = TelegramBot::create(['token' => 'test-only', 'name' => 'Test', 'username' => 'test_bot']);
    }

    private function link(string $code, int $chat = 100): Result
    {
        return app(LinkTelegramAccount::class)->handle($code, $this->bot->id, (string) $chat, $chat, 'private', false, 'Test');
    }

    public function test_hash_expiry_replay_and_unlink(): void
    {
        $user = User::factory()->create();
        $code = TelegramLinkCode::issue($user, $this->bot);
        $row = DB::table('telegram_link_codes')->sole();
        $this->assertSame(32, strlen($code));
        $this->assertSame(hash('sha256', $code), $row->code_hash);
        $this->assertNull(Cache::get('telegram-link:'.$code));
        $this->assertSame(Result::Linked, $this->link($code));
        $before = (array) DB::table('telegram_link_codes')->sole();
        $this->assertSame(Result::AlreadyLinked, $this->link($code));
        $this->assertSame($before, (array) DB::table('telegram_link_codes')->sole());
        $this->assertSame(Result::InvalidCode, $this->link($code, 101));
        app(UnlinkTelegramAccount::class)->handle('user', $user->id);
        $this->assertSame(Result::InvalidCode, $this->link($code));
        $this->assertNull(TelegramChat::sole()->user_id);
        $newCode = TelegramLinkCode::issue($user, $this->bot);
        $this->travel(10)->minutes();
        $this->assertSame(Result::InvalidCode, $this->link($newCode));
        $this->assertSame(Result::InvalidCode, $this->link($code));
    }

    public function test_occupied_chat_does_not_spend_code_or_replace_owner(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->link(TelegramLinkCode::issue($first, $this->bot));
        $code = TelegramLinkCode::issue($second, $this->bot);
        $this->assertSame(Result::Occupied, $this->link($code));
        $this->assertSame($first->id, TelegramChat::sole()->user_id);
        $this->assertNull(DB::table('telegram_link_codes')->where('code_hash', hash('sha256', $code))->value('consumed_at'));
        app(UnlinkTelegramAccount::class)->handle('user', $first->id);
        $this->assertSame(Result::Linked, $this->link($code));
    }

    public function test_wrong_bot_deleted_owner_and_legacy_cache_codes_are_invalid(): void
    {
        $user = User::factory()->create();
        $code = TelegramLinkCode::issue($user, $this->bot);
        $other = TelegramBot::create(['token' => 'other-test', 'name' => 'Other', 'username' => 'other_bot']);
        $this->assertSame(Result::InvalidCode, app(LinkTelegramAccount::class)->handle($code, $other->id, '100', 100, 'private', false, 'Test'));
        $user->delete();
        $this->assertSame(Result::InvalidCode, $this->link($code));
        Cache::put('telegram-link:'.str_repeat('a', 32), ['subject' => 'user', 'id' => 1], 600);
        $this->assertSame(Result::InvalidCode, $this->link(str_repeat('a', 32)));
        $this->assertSame(0, TelegramChat::count());
    }

    public static function invalidSenders(): array
    {
        return [['group', 100, false], ['private', null, false], ['private', 101, false], ['private', 100, true]];
    }

    #[DataProvider('invalidSenders')]
    public function test_invalid_sender_does_not_spend_code(string $type, ?int $sender, bool $isBot): void
    {
        $code = TelegramLinkCode::issue(User::factory()->create(), $this->bot);
        $result = app(LinkTelegramAccount::class)->handle($code, $this->bot->id, '100', $sender, $type, $isBot, 'Test');
        $this->assertSame(Result::InvalidSender, $result);
        $this->assertNull(DB::table('telegram_link_codes')->sole()->consumed_at);
        $this->assertSame(0, TelegramChat::count());
        $this->assertSame(Result::Linked, $this->link($code));
    }

    public function test_admin_relink_rolls_back_and_keeps_customer_column(): void
    {
        $admin = MoonshineUser::create(['name' => 'Admin', 'email' => 's02@test.local', 'password' => bcrypt('test'), 'moonshine_user_role_id' => 1]);
        $user = User::factory()->create();
        $this->link(TelegramLinkCode::issue($user, $this->bot), 101);
        $this->link(TelegramLinkCode::issueFor('moonshine_user', $admin->id, $this->bot));
        $code = TelegramLinkCode::issueFor('moonshine_user', $admin->id, $this->bot);
        $fail = true;
        Event::listen('eloquent.saving: '.TelegramChat::class, function ($chat) use (&$fail) {
            if ($fail && (string) $chat->chat_id === '101' && $chat->moonshine_user_id !== null) {
                throw new RuntimeException('Injected save failure');
            }
        });
        try {
            $this->link($code, 101);
            $this->fail('Expected failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected save failure', $exception->getMessage());
        }
        $this->assertSame($admin->id, TelegramChat::where('chat_id', '100')->sole()->moonshine_user_id);
        $this->assertNull(TelegramChat::where('chat_id', '101')->sole()->moonshine_user_id);
        $this->assertNull(DB::table('telegram_link_codes')->where('code_hash', hash('sha256', $code))->value('consumed_at'));
        $fail = false;
        $this->assertSame(Result::LinkedAdmin, $this->link($code, 101));
        $this->assertSame($user->id, TelegramChat::where('chat_id', '101')->sole()->user_id);
        $this->assertNull(TelegramChat::where('chat_id', '100')->sole()->moonshine_user_id);
    }

    public function test_prune_removes_only_codes_expired_over_a_day_ago(): void
    {
        $user = User::factory()->create();
        foreach (['old' => now()->subDays(2), 'recent' => now()->subMinute(), 'active' => now()->addMinutes(10)] as $expiry) {
            $code = TelegramLinkCode::issue($user, $this->bot);
            DB::table('telegram_link_codes')->where('code_hash', hash('sha256', $code))->update(['expires_at' => $expiry]);
        }
        $this->artisan('telegram:prune-link-codes')->assertSuccessful();
        $this->assertSame(2, DB::table('telegram_link_codes')->count());
    }

    public function test_admin_cannot_take_another_admins_chat(): void
    {
        $first = MoonshineUser::create(['name' => 'First', 'email' => 'first-s02@test.local', 'password' => bcrypt('test'), 'moonshine_user_role_id' => 1]);
        $second = MoonshineUser::create(['name' => 'Second', 'email' => 'second-s02@test.local', 'password' => bcrypt('test'), 'moonshine_user_role_id' => 1]);
        $this->link(TelegramLinkCode::issueFor('moonshine_user', $first->id, $this->bot));
        $code = TelegramLinkCode::issueFor('moonshine_user', $second->id, $this->bot);
        $this->assertSame(Result::Occupied, $this->link($code));
        $this->assertSame($first->id, TelegramChat::sole()->moonshine_user_id);
        $this->assertNull(DB::table('telegram_link_codes')->where('code_hash', hash('sha256', $code))->value('consumed_at'));
    }

    public function test_webhook_rejects_mismatching_sender_without_spending_code(): void
    {
        $code = TelegramLinkCode::issue(User::factory()->create(), $this->bot);
        $this->postJson(route('telegraph.webhook', ['token' => $this->bot->token]), [
            'update_id' => 1,
            'message' => ['message_id' => 1, 'date' => now()->timestamp, 'text' => '/start '.$code,
                'chat' => ['id' => '100', 'type' => 'private'],
                'from' => ['id' => 101, 'first_name' => 'Test']],
        ])->assertNoContent();
        Telegraph::assertSent(__('telegram.invalid_sender'));
        $this->assertNull(DB::table('telegram_link_codes')->sole()->consumed_at);
        $this->assertSame(0, TelegramChat::count());
    }
}
