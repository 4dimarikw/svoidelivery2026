<?php

namespace Tests\Feature\Telegram;

use App\Telegraph\WebhookHandler;
use DefStudio\Telegraph\DTO\Message;
use Domain\Auth\Models\User;
use Domain\Telegram\Actions\FindOrRegisterTelegramUser;
use Domain\Telegram\Actions\LinkTelegramAccount;
use Domain\Telegram\Models\TelegramBot;
use Domain\Telegram\Models\TelegramChat;
use Domain\Telegram\Support\TelegramLinkCode;
use Domain\Telegram\Support\TelegramLinkResult;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PDOException;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class TelegramLinkConcurrencyTest extends TestCase
{
    private TelegramBot $bot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('svoidelivery2026_test', config('database.connections.mysql.database'));
        $this->assertEmpty(config('database.connections.mysql.url'));
        $this->assertSame('svoidelivery2026_test', DB::selectOne('select database() as db')->db);
        Artisan::call('migrate:fresh', ['--force' => true]);
        Http::preventStrayRequests();
        Mail::fake();
        $this->bot = TelegramBot::create(['token' => 'test-only', 'name' => 'Test', 'username' => 'test_bot']);
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    private function operation(User $user, int $chatId = 100): array
    {
        return ['operation' => 'link', 'bot_id' => $this->bot->id, 'chat_id' => $chatId,
            'code' => TelegramLinkCode::issue($user, $this->bot)];
    }

    private function race(TelegramBot $bot, array $operations): array
    {
        $connection = config('database.connections.mysql');
        $processes = [];
        DB::beginTransaction();
        try {
            TelegramBot::whereKey($bot->id)->lockForUpdate()->firstOrFail();
            foreach ($operations as $operation) {
                $process = new Process([PHP_BINARY, '-d', 'sys_temp_dir='.sys_get_temp_dir(), base_path('tests/Support/telegram-link-worker.php')], base_path(), [
                    'APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => base_path('storage/framework/testing/no-checkout-config.php'),
                    'DB_CONNECTION' => 'mysql', 'DB_HOST' => $connection['host'],
                    'DB_PORT' => (string) $connection['port'], 'DB_DATABASE' => $connection['database'],
                    'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'], 'DB_URL' => '',
                    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array',
                    'QUEUE_CONNECTION' => 'null',
                ], json_encode($operation, JSON_THROW_ON_ERROR), 20);
                $process->start();
                $processes[] = $process;
            }
            foreach ($processes as $process) {
                // На Windows READY мог быть прочитан при start/isRunning; проверяем весь буфер.
                $deadline = microtime(true) + 10;
                while (! str_contains($process->getOutput(), 'READY') && $process->isRunning() && microtime(true) < $deadline) {
                    usleep(10000);
                }
                $this->assertStringContainsString('READY', $process->getOutput(), $process->getErrorOutput());
                $this->assertTrue($process->isRunning());
            }
            DB::commit();
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
                $lines = explode("\n", trim($process->getOutput()));
                $results[] = json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
        }
    }

    public function test_same_code_concurrently_has_one_mutation(): void
    {
        foreach ([100, 101] as $secondChat) {
            TelegramChat::query()->delete();
            $op = $this->operation(User::factory()->create());
            $other = array_replace($op, ['chat_id' => $secondChat]);
            $results = array_column($this->race($this->bot, [$op, $other]), 'result');
            sort($results);
            $expected = $secondChat === 100 ? ['already_linked', 'linked'] : ['link_code_invalid', 'linked'];
            $this->assertSame($expected, $results);
            $this->assertSame(1, TelegramChat::whereNotNull('user_id')->count());
        }
    }

    public function test_different_accounts_cannot_claim_same_chat(): void
    {
        $results = array_column($this->race($this->bot, [
            $this->operation(User::factory()->create()), $this->operation(User::factory()->create()),
        ]), 'result');
        sort($results);
        $this->assertSame(['chat_occupied', 'linked'], $results);
        $this->assertSame(1, DB::table('telegram_link_codes')->whereNotNull('consumed_at')->count());
    }

    public function test_login_and_link_are_serialized(): void
    {
        $user = User::factory()->create();
        $results = $this->race($this->bot, [
            $this->operation($user), ['operation' => 'login', 'bot_id' => $this->bot->id, 'chat_id' => 100],
        ]);
        $chat = TelegramChat::sole();
        $this->assertSame($results[1]['user_id'], $chat->user_id);
        $this->assertSame($chat->user_id === $user->id ? 'linked' : 'chat_occupied', $results[0]['result']);
        $this->assertSame($chat->user_id === $user->id ? 1 : 2, User::count());
    }

    public function test_unlink_and_relink_do_not_leave_partial_links(): void
    {
        $user = User::factory()->create();
        TelegramChat::create(['chat_id' => '99', 'telegraph_bot_id' => $this->bot->id, 'user_id' => $user->id]);
        $results = $this->race($this->bot, [
            $this->operation($user), ['operation' => 'unlink', 'user_id' => $user->id],
        ]);
        $this->assertSame(['linked', 'unlinked'], array_column($results, 'result'));
        $this->assertNull(TelegramChat::where('chat_id', '99')->sole()->user_id);
        $this->assertLessThanOrEqual(1, TelegramChat::whereNotNull('user_id')->count());
        $this->assertNotNull(DB::table('telegram_link_codes')->sole()->consumed_at);
    }

    public function test_two_relinks_of_one_account_keep_one_current_chat(): void
    {
        $user = User::factory()->create();
        $results = $this->race($this->bot, [$this->operation($user), $this->operation($user, 101)]);
        $this->assertSame(['linked', 'linked'], array_column($results, 'result'));
        $this->assertSame(1, TelegramChat::where('user_id', $user->id)->count());
        $this->assertSame(2, DB::table('telegram_link_codes')->whereNotNull('consumed_at')->count());
    }

    public function test_deadlock_retries_use_fresh_models_and_are_bounded(): void
    {
        $op = $this->operation(User::factory()->create());
        $attempts = 0;
        $alwaysFail = false;
        Event::listen('eloquent.saving: '.TelegramChat::class, function () use (&$attempts, &$alwaysFail) {
            if (++$attempts === 1 || $alwaysFail) {
                throw new PDOException('Deadlock found when trying to get lock', 40001);
            }
        });
        $action = app(LinkTelegramAccount::class);
        $this->assertSame(TelegramLinkResult::Linked, $action->handle($op['code'], $this->bot->id, '100', 100, 'private', false, 'Test'));
        $this->assertSame(2, $attempts);
        $second = $this->operation(User::factory()->create(), 101);
        $alwaysFail = true;
        $attempts = 0;
        try {
            $action->handle($second['code'], $this->bot->id, '101', 101, 'private', false, 'Test');
            $this->fail('Expected deadlock');
        } catch (PDOException $exception) {
            $this->assertSame(40001, $exception->getCode());
        }
        $this->assertSame(3, $attempts);
        $this->assertSame(1, TelegramChat::count());
        $this->assertNull(DB::table('telegram_link_codes')->where('code_hash', hash('sha256', $second['code']))->value('consumed_at'));
    }

    public function test_reply_failure_happens_after_commit_and_replay_confirms_link(): void
    {
        $op = $this->operation(User::factory()->create());
        $handler = new class extends WebhookHandler
        {
            public int $replyLevel = -1;

            public function prepare(TelegramBot $bot): void
            {
                $this->bot = $bot;
                $this->message = Message::fromArray([
                    'message_id' => 1, 'date' => time(),
                    'chat' => ['id' => '100', 'type' => 'private'],
                    'from' => ['id' => 100, 'first_name' => 'Test'],
                ]);
            }

            protected function reply(string $message, bool $showAlert = false): void
            {
                $this->replyLevel = DB::transactionLevel();
                throw new RuntimeException('Injected Telegram outage');
            }
        };
        $handler->prepare($this->bot);
        try {
            $handler->start($op['code']);
            $this->fail('Expected reply failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected Telegram outage', $exception->getMessage());
        }
        $this->assertSame(0, $handler->replyLevel);
        $this->assertNotNull(DB::table('telegram_link_codes')->sole()->consumed_at);
        $this->assertSame(TelegramLinkResult::AlreadyLinked, app(LinkTelegramAccount::class)->handle(
            $op['code'], $this->bot->id, '100', 100, 'private', false, 'Test',
        ));
    }

    public function test_registration_events_run_after_commit_with_one_profile(): void
    {
        $attempts = 0;
        Event::listen('eloquent.saving: '.TelegramChat::class, function () use (&$attempts) {
            if (++$attempts === 1) {
                throw new PDOException('Deadlock found when trying to get lock', 40001);
            }
        });
        $levels = [];
        Event::listen(Registered::class, function () use (&$levels) {
            $levels[] = DB::transactionLevel();
        });
        $action = app(FindOrRegisterTelegramUser::class);
        $user = $action->handle($this->bot->id, 100, 'Test', 'test_user');
        $this->assertSame($user->id, $action->handle($this->bot->id, 100, 'Test', null)->id);
        $this->assertSame([0], $levels);
        $this->assertSame(2, $attempts);
        $this->assertSame(1, User::count());
        $this->assertSame(1, $user->profile()->count());
        $this->assertSame('https://t.me/test_user', $user->profile->social_links['telegram']);
    }

    public function test_parallel_first_logins_create_one_user(): void
    {
        $op = ['operation' => 'login', 'bot_id' => $this->bot->id, 'chat_id' => 100];
        $results = $this->race($this->bot, [$op, $op]);
        $this->assertSame($results[0]['user_id'], $results[1]['user_id']);
        $this->assertSame(1, User::count());
        $this->assertSame(1, User::sole()->profile()->count());
    }
}
