<?php

namespace Tests\Feature\Security;

use App\MoonShine\Pages\EventLoggingSettingsPage;
use App\Notifications\QueuedResetPassword as ResetPassword;
use Database\Seeders\MoonshinePermissionSeeder;
use Domain\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\LaravelSettings\Models\SettingsProperty;
use Tests\TestCase;

/** Regression coverage for the findings of the September 2026 security audit. */
class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.connections.mysql.database') !== 'svoidelivery2026_test') {
            throw new \RuntimeException('Security audit requires the dedicated test database.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
        Mail::fake();
        Notification::fake();
        Http::preventStrayRequests();
    }

    public static function authEndpoints(): array
    {
        return [['/login'], ['/register'], ['/forgot-password'], ['/reset-password']];
    }

    #[DataProvider('authEndpoints')]
    public function test_array_email_is_validation_error_not_server_error(string $path): void
    {
        $this->postJson($path, ['email' => ['audit'], 'password' => 'invalid-test-only'])
            ->assertStatus(422);
    }

    public function test_reset_mail_host_cannot_be_poisoned(): void
    {
        $user = User::factory()->create(['email' => 'audit-victim@example.invalid']);
        $this->postJson('http://audit.invalid/forgot-password', ['email' => $user->email])->assertOk();
        $notification = Notification::sent($user, ResetPassword::class)->first();
        $this->assertNotNull($notification);
        // Only compare the hostname: never print or persist reset tokens.
        $this->assertSame(parse_url(config('app.url'), PHP_URL_HOST),
            parse_url($notification->toMail($user)->actionUrl, PHP_URL_HOST));
    }

    public function test_password_reset_does_not_reveal_account_existence(): void
    {
        $user = User::factory()->create(['email' => 'audit-known@example.invalid']);
        $known = $this->postJson('/forgot-password', ['email' => $user->email]);
        $unknown = $this->postJson('/forgot-password', ['email' => 'audit-absent@example.invalid']);
        $this->assertSame($known->getStatusCode(), $unknown->getStatusCode());
        $this->assertSame($known->json(), $unknown->json());
    }

    public function test_profile_and_address_payloads_are_escaped_and_owner_cannot_be_overridden(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $payload = '<svg onload=alert(7319)>';
        $this->actingAs($user)->putJson('/account/profile', [
            'first_name' => $payload, 'default_order_comment' => $payload,
            'user_id' => $other->id, 'email_verified_at' => '2026-01-01',
        ])->assertOk();
        $this->assertSame($payload, $user->profile()->first()->first_name);
        $this->assertNull($other->profile()->first());
        $this->get('/account/profile')->assertOk()->assertDontSee($payload, false)->assertSee(e($payload), false);
        $this->postJson('/account/addresses', [
            'label' => $payload, 'city' => $payload, 'address' => $payload,
            'comment' => $payload, 'user_id' => $other->id,
        ])->assertOk();
        $address = $user->addresses()->firstOrFail();
        $this->assertSame(0, $other->addresses()->count());
        $this->get('/account/addresses')->assertOk()->assertDontSee($payload, false)->assertSee(e($payload), false);
        $this->get('/account/addresses/'.$address->id.'/edit')->assertOk()->assertDontSee($payload, false);
    }

    public function test_profile_rejects_script_urls(): void
    {
        $this->actingAs(User::factory()->create())->putJson('/account/profile', [
            'social_telegram' => 'javascript:alert(7319)',
        ])->assertUnprocessable()->assertJsonValidationErrors('social_telegram');
    }

    public function test_catalog_query_is_validated_and_escaped(): void
    {
        $this->getJson('/?q[]=audit')->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->getJson('/?q='.urlencode(str_repeat('a', 256)))->assertUnprocessable();
        $this->getJson('/?categories[]=invalid')->assertUnprocessable();
        $payload = '<svg onload=alert(7319)>';
        $this->get('/?q='.urlencode($payload))->assertOk()->assertDontSee($payload, false)->assertSee(e($payload), false);
        $this->get('/?q='.urlencode("' OR 1=1 --"))->assertOk();
    }

    private function manager(): MoonshineUser
    {
        $this->seed(MoonshinePermissionSeeder::class);
        $role = MoonshineUserRole::query()->where('name', 'Manager')->firstOrFail();

        return MoonshineUser::query()->create([
            'name' => 'Audit manager', 'email' => 'audit-manager@example.invalid',
            'password' => bcrypt('audit-test-only-password'), 'moonshine_user_role_id' => $role->id,
        ]);
    }

    public function test_manager_cannot_read_hidden_logging_settings(): void
    {
        $page = app(EventLoggingSettingsPage::class);
        $this->actingAs($this->manager(), 'moonshine')->get($page->getUrl())->assertForbidden();
    }

    public function test_manager_cannot_disable_event_logging(): void
    {
        $page = app(EventLoggingSettingsPage::class);
        $response = $this->actingAs($this->manager(), 'moonshine')
            ->post(route('moonshine.method', ['pageUri' => $page->getUriKey()]), ['method' => 'save']);
        $disabled = SettingsProperty::query()
            ->where('group', 'event_logging')->where('name', 'disabled_event_types')->value('payload');
        $this->assertEmpty(json_decode($disabled ?? '[]', true), 'Manager changed event logging settings.');
        $response->assertForbidden();
    }

    public function test_get_cannot_change_logging_settings(): void
    {
        $admin = MoonshineUser::query()->create([
            'name' => 'Audit admin', 'email' => 'audit-admin@example.invalid',
            'password' => bcrypt('audit-test-only-password'),
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
        ]);
        $page = app(EventLoggingSettingsPage::class);
        $response = $this->actingAs($admin, 'moonshine')->get(route('moonshine.method', [
            'pageUri' => $page->getUriKey(), 'method' => 'save',
        ]));
        $disabled = SettingsProperty::query()
            ->where('group', 'event_logging')->where('name', 'disabled_event_types')->value('payload');
        $this->assertEmpty(json_decode($disabled ?? '[]', true), 'GET request changed event logging settings.');
        $this->assertContains($response->getStatusCode(), [403, 405]);
    }
}
