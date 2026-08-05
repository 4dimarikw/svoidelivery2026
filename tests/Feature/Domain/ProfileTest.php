<?php

namespace Tests\Feature\Domain;

use Domain\Auth\Models\User;
use Domain\Profile\Models\Profile;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_name_orders_as_surname_first_name_patronymic(): void
    {
        $profile = Profile::factory()->make([
            'last_name' => 'Иванов',
            'first_name' => 'Иван',
            'patronymic' => 'Иванович',
        ]);

        $this->assertSame('Иванов Иван Иванович', $profile->full_name);
    }

    public function test_full_name_skips_blank_parts(): void
    {
        $profile = Profile::factory()->make([
            'last_name' => 'Иванов',
            'first_name' => null,
            'patronymic' => null,
        ]);

        $this->assertSame('Иванов', $profile->full_name);
    }

    public function test_full_name_is_null_when_everything_is_blank(): void
    {
        $profile = Profile::factory()->make([
            'last_name' => null,
            'first_name' => null,
            'patronymic' => null,
        ]);

        $this->assertNull($profile->full_name);
    }

    public function test_registered_event_auto_provisions_a_profile(): void
    {
        // App\Listeners\CreateUserProfile слушает Registered через auto-discovery
        // (сканирование app/Listeners) — без явного Event::listen() где-либо.
        $user = User::factory()->create();

        $this->assertNull($user->profile);

        event(new Registered($user));

        $this->assertNotNull($user->fresh()->profile);
    }
}
