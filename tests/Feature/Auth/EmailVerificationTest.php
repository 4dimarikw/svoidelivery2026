<?php

namespace Tests\Feature\Auth;

use Domain\Auth\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_email_screen_renders_for_unverified_user(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('verification.notice'))->assertOk();
    }

    public function test_signed_url_verifies_the_email_and_dispatches_verified_event(): void
    {
        Event::fake();

        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $response = $this->actingAs($user)->get($url);

        Event::assertDispatched(Verified::class);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $response->assertRedirect(route('account.profile.edit').'?verified=1');
    }

    public function test_verified_middleware_blocks_the_account_area_until_verified(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('account.profile.edit'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_verified_middleware_lets_verified_users_through(): void
    {
        $user = User::factory()->create(); // email_verified_at по умолчанию заполнен фабрикой

        $this->actingAs($user)->get(route('account.profile.edit'))->assertOk();
    }
}
