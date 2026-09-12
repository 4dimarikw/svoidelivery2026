<?php

namespace Tests\Feature\Security;

use App\MoonShine\Models\MoonshinePermission;
use App\MoonShine\Pages\SiteSettingsPage;
use App\Notifications\QueuedResetPassword;
use App\Support\NotificationUrls;
use Database\Seeders\MoonshinePermissionSeeder;
use Domain\Auth\Models\User;
use Domain\Catalog\Models\Product;
use Domain\Order\Models\Order;
use Domain\Telegram\Models\TelegramBot;
use Domain\Telegram\Models\TelegramChat;
use Domain\Untappd\Models\UntappdBeer;
use Domain\Vk\Models\VkPost;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function staff(bool $super = false): MoonshineUser
    {
        $this->seed(MoonshinePermissionSeeder::class);

        return MoonshineUser::create([
            'name' => 'Audit', 'email' => 'audit-'.($super ? 'admin' : 'manager').'@example.invalid',
            'password' => bcrypt('test-password'), 'moonshine_user_role_id' => $super
                ? MoonshineUserRole::DEFAULT_ROLE_ID : MoonshineUserRole::where('name', 'Manager')->value('id'),
        ]);
    }

    public function test_production_rejects_unknown_hosts_and_subdomains(): void
    {
        $this->app->instance('env', 'production');
        config(['app.debug' => false, 'app.url' => 'http://localhost:8000', 'security.trusted_hosts' => ['admin.example.test']]);
        foreach (['attacker.invalid', 'sub.localhost', 'localhost.attacker.invalid'] as $host) {
            $this->getJson('http://'.$host.'/login')->assertStatus(400)->assertJsonMissingPath('trace');
        }
        $this->get('http://localhost:8000/login')->assertOk();
        $this->get('http://admin.example.test/login')->assertOk();
        $this->postJson('http://attacker.invalid/register', ['email' => 'audit@example.invalid'])->assertStatus(400);
    }

    public function test_only_explicit_proxies_can_supply_forwarded_headers(): void
    {
        $this->app->instance('env', 'production');
        config(['app.url' => 'http://localhost:8000']);
        $this->withHeader('X-Forwarded-Host', 'attacker.invalid')->get('/login')->assertOk();
        config(['security.trusted_proxies' => ['127.0.0.1']]);
        $this->get('/login')->assertStatus(400);
    }

    public function test_canonical_notifications_preserve_verification_signature_and_global_url_origin(): void
    {
        config(['app.url' => 'https://shop.example.test']);
        $user = User::factory()->unverified()->create();
        $this->get('http://admin.example.test/login')->assertOk();
        $url = NotificationUrls::verify($user);
        $this->assertSame('shop.example.test', parse_url($url, PHP_URL_HOST));
        $this->assertSame('https', parse_url($url, PHP_URL_SCHEME));
        $this->assertStringStartsWith('http://admin.example.test/', URL::route('login'));
        $this->assertTrue(URL::hasValidSignature(Request::create($url)));
        $this->assertFalse(URL::hasValidSignature(Request::create(str_replace('shop.example.test', 'evil.test', $url))));
        $this->travel(61)->minutes();
        $this->assertFalse(URL::hasValidSignature(Request::create($url)));
    }

    public function test_headers_are_present_on_html_and_errors_without_breaking_http(): void
    {
        foreach (['/login', '/missing-security-page'] as $path) {
            $this->get($path)->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeader('Content-Security-Policy', "base-uri 'self'; object-src 'none'")
                ->assertHeaderMissing('Strict-Transport-Security');
        }
        $this->get(route('moonshine.login'))->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy', "base-uri 'self'; object-src 'none'; frame-ancestors 'none'");
    }

    public function test_hsts_only_on_production_https(): void
    {
        $this->app->instance('env', 'production');
        config(['app.url' => 'https://shop.example.test']);
        $this->get('https://shop.example.test/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
        $this->get('http://shop.example.test/login')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_ip_limiter_counts_different_and_malformed_identities(): void
    {
        config(['security.login_throttle.per_ip' => 3]);
        foreach ([['array'], str_repeat('a', 256), 'different@example.invalid'] as $email) {
            $this->postJson('/login', ['email' => $email, 'password' => 'invalid'])->assertStatus(422);
        }
        $this->postJson('/login', ['email' => 'fourth@example.invalid', 'password' => 'invalid'])
            ->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_registration_malformed_identity_is_counted_and_secrets_are_not_flashed(): void
    {
        config(['security.register_throttle.per_ip' => 1]);
        $this->postJson('/register', ['email' => ['array']])->assertStatus(422);
        $this->from('/register')->post('/register', ['email' => 'test@example.invalid',
            'password' => 'do-not-flash', 'password_confirmation' => 'do-not-flash',
            'smart-token' => 'do-not-flash', 'token' => 'do-not-flash', 'init_data' => 'do-not-flash'])
            ->assertRedirect();
        $this->assertSame(['email' => 'test@example.invalid'], session()->getOldInput());
    }

    public function test_reset_responses_match_for_unknown_known_and_broker_throttled_accounts(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $first = $this->postJson('/forgot-password', ['email' => $user->email])->assertOk()->json();
        $this->postJson('/forgot-password', ['email' => $user->email])->assertOk()->assertExactJson($first);
        $this->postJson('/forgot-password', ['email' => 'absent@example.invalid'])->assertOk()->assertExactJson($first);
        $this->postJson('/forgot-password', ['email' => 'invalid'])->assertStatus(422);
        $known = $this->post('/forgot-password', ['email' => $user->email])->assertRedirect(route('password.request'));
        $message = session('status');
        $this->post('/forgot-password', ['email' => 'html-absent@example.invalid'])->assertRedirect(route('password.request'));
        $this->assertSame($message, session('status'));
        Notification::assertSentToTimes($user, QueuedResetPassword::class, 1);
    }

    public function test_reset_notification_payload_is_encrypted_and_worker_can_deliver_it(): void
    {
        config(['queue.default' => 'database']);
        $user = User::factory()->create();
        $token = 'audit-token-never-plaintext';
        $channel = $this->mock(MailChannel::class);
        $channel->shouldReceive('send')->once()->withArgs(function ($recipient, $notification) use ($user, $token) {
            return $recipient->id === $user->id && $notification->token === $token
                && parse_url($notification->toMail($recipient)->actionUrl, PHP_URL_HOST) === 'localhost';
        });
        $user->notify((new QueuedResetPassword($token))->onQueue('security-audit'));
        $payload = DB::table('jobs')->where('queue', 'security-audit')->value('payload');
        $this->assertNotNull($payload);
        $this->assertStringNotContainsString($token, $payload);
        $jobData = json_decode($payload, true);
        $decrypted = Crypt::decryptString($jobData['data']['command']);
        $this->assertStringContainsString($token, $decrypted);
        $job = Queue::connection('database')->pop('security-audit');
        $job->fire();
        $job->delete();
        $this->assertSame(0, DB::table('jobs')->where('queue', 'security-audit')->count());
        $this->assertSame([30, 120], (new QueuedResetPassword($token))->backoff());
        $this->assertSame(3, (new QueuedResetPassword($token))->tries);
    }

    public function test_reset_http_request_queues_mail_without_executing_the_mail_channel(): void
    {
        config(['queue.default' => 'database']);
        $this->mock(MailChannel::class)->shouldNotReceive('send');
        $user = User::factory()->create();
        $known = $this->postJson('/forgot-password', ['email' => $user->email])->assertOk()->json();
        $this->assertSame(1, DB::table('jobs')->count());
        $payload = json_decode(DB::table('jobs')->value('payload'), true);
        // Queue encryption also serializes the command string; never print its token on failure.
        $this->assertTrue(is_string(Crypt::decrypt($payload['data']['command'])));
        $this->postJson('/forgot-password', ['email' => $user->email])->assertOk()->assertExactJson($known);
        $this->postJson('/forgot-password', ['email' => 'unknown-queued@example.invalid'])->assertOk()->assertExactJson($known);
        $this->assertSame(1, DB::table('jobs')->count());
    }

    public function test_async_page_cannot_be_combined_with_another_resource_to_bypass_its_policy(): void
    {
        Queue::fake();
        $this->actingAs($this->staff(), 'moonshine');
        foreach (['product-resource', null] as $resource) {
            $response = $this->postJson(route('moonshine.method', ['pageUri' => 'untappd-beer-form-page', 'resourceUri' => $resource]),
                ['method' => 'resync', 'itemId' => 1]);
            $response->assertNotFound();
        }
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_confirmation_button_contains_a_post_form_with_csrf(): void
    {
        $this->actingAs($this->staff(true), 'moonshine');
        $html = $this->get('/admin/resource/event-log-resource/event-log-index-page')->assertOk()->getContent();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($document);
        $forms = $xpath->query('//form[contains(@action, "method=clearTable")]');
        $this->assertGreaterThan(0, $forms->length);
        foreach ($forms as $form) {
            $this->assertSame('post', strtolower($form->getAttribute('method')));
            $this->assertSame(1, $xpath->query('.//input[@name="_token"]', $form)->length);
        }
    }

    public function test_admin_unlink_can_only_modify_the_current_administrator(): void
    {
        $manager = $this->staff();
        $other = $this->staff(true);
        $bot = TelegramBot::create(['token' => 'isolated-test-token', 'name' => 'Test']);
        $ownChat = TelegramChat::create(['chat_id' => '101', 'name' => 'Own',
            'telegraph_bot_id' => $bot->id, 'moonshine_user_id' => $manager->id]);
        $otherChat = TelegramChat::create(['chat_id' => '102', 'name' => 'Other',
            'telegraph_bot_id' => $bot->id, 'moonshine_user_id' => $other->id]);
        $this->actingAs($manager, 'moonshine');
        $url = route('moonshine.method', ['pageUri' => 'profile-page', 'method' => 'unlinkTelegram']);
        $this->postJson($url, ['user_id' => $other->id])->assertStatus(422);
        $this->assertSame($manager->id, $ownChat->fresh()->moonshine_user_id);
        $this->assertSame($other->id, $otherChat->fresh()->moonshine_user_id);
        $this->postJson($url)->assertOk();
        $this->assertNull($ownChat->fresh()->moonshine_user_id);
        $this->assertSame($other->id, $otherChat->fresh()->moonshine_user_id);
        Http::assertNothingSent();
    }

    public static function adminPages(): array
    {
        return array_map(fn ($page) => [$page], ['event-logging-settings-page', 'catalog-import-settings-page', 'vk-sync-settings-page', 'scheduling-page']);
    }

    public function test_database_worker_retries_failed_mail_without_plaintext_token_in_payload(): void
    {
        config(['queue.default' => 'database']);
        $token = 'retry-token-never-plaintext';
        $user = User::factory()->create();
        $channel = $this->mock(MailChannel::class);
        $channel->shouldReceive('send')->once()->ordered()->andThrow(new \RuntimeException('Synthetic transport failure'));
        $channel->shouldReceive('send')->once()->ordered()->andReturnNull();
        $user->notify((new QueuedResetPassword($token))->onQueue('security-retry'));
        $queue = Queue::connection('database');
        $worker = app('queue.worker');
        $options = new WorkerOptions;
        try {
            $worker->process('database', $queue->pop('security-retry'), $options);
            $this->fail('Expected a simulated transport failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic transport failure', $e->getMessage());
        }
        $pending = DB::table('jobs')->where('queue', 'security-retry')->sole();
        $this->assertSame(1, $pending->attempts);
        $this->assertNull($pending->reserved_at);
        $this->assertStringNotContainsString($token, $pending->payload);
        $this->assertNull($queue->pop('security-retry'));
        $this->travel(31)->seconds();
        $worker->process('database', $queue->pop('security-retry'), $options);
        $this->assertSame(0, DB::table('jobs')->where('queue', 'security-retry')->count());
    }

    #[DataProvider('adminPages')]
    public function test_manager_cannot_open_system_pages(string $page): void
    {
        $this->actingAs($this->staff(), 'moonshine')->get('/admin/page/'.$page)->assertForbidden();
    }

    public function test_manager_retains_access_to_general_site_settings(): void
    {
        $this->actingAs($this->staff(), 'moonshine')->get(app(SiteSettingsPage::class)->getUrl())->assertOk();
    }

    public static function unsafeMethods(): array
    {
        return [['GET'], ['HEAD'], ['PUT'], ['DELETE']];
    }

    #[DataProvider('unsafeMethods')]
    public function test_wrong_verbs_do_not_modify_settings_even_for_superuser(string $verb): void
    {
        $this->actingAs($this->staff(true), 'moonshine');
        $before = DB::table('settings')->get()->toJson();
        $this->call($verb, route('moonshine.method', ['pageUri' => 'event-logging-settings-page', 'method' => 'save']))
            ->assertStatus(405);
        $this->assertSame($before, DB::table('settings')->get()->toJson());
    }

    public function test_manager_cannot_invoke_scheduler_or_create_test_order_or_clear_log(): void
    {
        Queue::fake();
        $this->actingAs($this->staff(), 'moonshine');
        $this->postJson(route('moonshine.scheduling.run'), ['id' => 0])->assertForbidden();
        foreach ([['order-index-page', 'order-resource', 'createTestOrder'], ['event-log-index-page', 'event-log-resource', 'clearTable']] as [$page, $resource, $method]) {
            $this->postJson(route('moonshine.method', ['pageUri' => $page, 'resourceUri' => $resource]), ['method' => $method])->assertForbidden();
        }
        Queue::assertNothingPushed();
    }

    public function test_resource_policy_denies_untappd_sync_and_vk_send_before_io(): void
    {
        Queue::fake();
        $this->actingAs($this->staff(), 'moonshine');
        $beer = UntappdBeer::factory()->create();
        $post = VkPost::factory()->create();
        $this->postJson(route('moonshine.method', ['pageUri' => 'untappd-beer-form-page', 'resourceUri' => 'untappd-beer-resource']),
            ['method' => 'resync', 'itemId' => $beer->id])->assertForbidden();
        foreach (['downloadImages', 'sendToSelf', 'broadcastAll'] as $method) {
            $this->postJson(route('moonshine.method', ['pageUri' => 'vk-post-index-page', 'resourceUri' => 'vk-post-resource']),
                ['method' => $method, 'resourceItem' => $post->id])->assertForbidden();
        }
        Queue::assertNothingPushed();
    }

    public function test_conflicting_target_ids_are_rejected_before_io(): void
    {
        $this->actingAs($this->staff(true), 'moonshine');
        $product = Product::factory()->create();
        $this->postJson(route('moonshine.method', ['pageUri' => 'product-form-page', 'resourceUri' => 'product-resource']),
            ['method' => 'updateImageFromUntappd', 'itemId' => $product->id, 'resourceItem' => $product->id + 1])->assertStatus(422);
    }

    public static function roleAndReadVerbs(): array
    {
        return [['guest', 'GET'], ['guest', 'HEAD'], ['manager', 'GET'], ['manager', 'HEAD'], ['super', 'GET'], ['super', 'HEAD']];
    }

    #[DataProvider('roleAndReadVerbs')]
    public function test_every_mutating_method_rejects_read_verbs_without_side_effects(string $role, string $verb): void
    {
        Queue::fake();
        if ($role !== 'guest') {
            $this->actingAs($this->staff($role === 'super'), 'moonshine');
        }
        $tables = ['settings', 'orders', 'event_logs', 'media', 'moonshine_users'];
        $snapshot = fn () => array_map(fn ($table) => DB::table($table)->get()->toJson(), $tables);
        $before = $snapshot();
        $actions = [
            ['event-logging-settings-page', null, 'save'], ['catalog-import-settings-page', null, 'store'],
            ['vk-sync-settings-page', null, 'save'], ['site-settings-page', null, 'save'], ['profile-page', null, 'unlinkTelegram'],
            ['order-index-page', 'order-resource', 'createTestOrder'], ['event-log-index-page', 'event-log-resource', 'clearTable'],
            ['order-form-page', 'order-resource', 'createDelivery'], ['order-form-page', 'order-resource', 'retryDelivery'],
            ['order-detail-page', 'order-resource', 'createDelivery'], ['order-detail-page', 'order-resource', 'retryDelivery'],
            ['product-form-page', 'product-resource', 'updateImageFromUntappd'], ['untappd-beer-form-page', 'untappd-beer-resource', 'resync'],
            ['site-menu-item-index-page', 'site-menu-item-resource', 'itemMove'], ['site-menu-item-index-page', 'site-menu-item-resource', 'transferItem'],
        ];
        foreach (['vk-post-form-page', 'vk-post-index-page'] as $page) {
            foreach (['downloadImages', 'sendToSelf', 'broadcastAll'] as $method) {
                $actions[] = [$page, 'vk-post-resource', $method];
            }
        }
        foreach ($actions as [$page, $resource, $method]) {
            $url = route('moonshine.method', ['pageUri' => $page, 'resourceUri' => $resource, 'method' => $method, 'resourceItem' => 1, 'itemId' => 1]);
            $expected = $role === 'guest' ? 401 : ($role === 'manager' && in_array($page,
                ['event-logging-settings-page', 'catalog-import-settings-page', 'vk-sync-settings-page'], true) ? 403 : 405);
            $this->json($verb, $url)->assertStatus($expected);
        }
        $this->assertSame($before, $snapshot());
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_order_actions_use_existing_role_permissions_on_the_actual_order(): void
    {
        Queue::fake();
        $manager = $this->staff();
        $order = Order::create([]);
        MoonshinePermission::where('moonshine_user_role_id', $manager->moonshine_user_role_id)
            ->where('model', $order::class)->update(['permissions' => json_encode(['view' => true, 'update' => false])]);
        $this->actingAs($manager, 'moonshine')->postJson(route('moonshine.method', [
            'pageUri' => 'order-form-page', 'resourceUri' => 'order-resource', 'method' => 'createDelivery',
            'resourceItem' => $order->id, 'operation_key' => (string) Str::uuid(),
        ]))->assertForbidden();
        $this->assertSame(0, $order->deliveries()->count());
        Queue::assertNothingPushed();
    }

    public function test_actual_csrf_middleware_rejects_public_and_admin_mutations(): void
    {
        $this->app->bind(VerifyCsrfToken::class, function ($app) {
            return new class($app, $app['encrypter']) extends VerifyCsrfToken
            {
                protected function runningUnitTests()
                {
                    return false;
                }
            };
        });
        $this->app->bind(ValidateCsrfToken::class,
            fn ($app) => $app->make(VerifyCsrfToken::class));
        $this->postJson('/login', ['email' => 'csrf@example.invalid'])->assertStatus(419);
        $this->actingAs($this->staff(true), 'moonshine');
        $before = DB::table('settings')->get()->toJson();
        $this->postJson(route('moonshine.method', ['pageUri' => 'event-logging-settings-page']), ['method' => 'save'])->assertStatus(419);
        $this->postJson(route('moonshine.scheduling.run'), ['id' => 0])->assertStatus(419);
        $this->assertSame($before, DB::table('settings')->get()->toJson());
        $this->withSession(['_token' => 'security-valid-csrf'])->postJson('/login', [
            '_token' => 'security-valid-csrf', 'email' => ['invalid'], 'password' => 'invalid',
        ])->assertStatus(422);
    }
}
