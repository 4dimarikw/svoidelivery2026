<?php

namespace Tests\Feature\Domain;

use Domain\Auth\Models\User;
use Domain\Profile\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_default_address_unsets_the_previous_default(): void
    {
        $user = User::factory()->create();
        $first = Address::factory()->for($user)->default()->create();

        $second = Address::factory()->for($user)->default()->create();

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }

    public function test_updating_an_address_to_default_unsets_others(): void
    {
        $user = User::factory()->create();
        $first = Address::factory()->for($user)->default()->create();
        $second = Address::factory()->for($user)->create(['is_default' => false]);

        $second->update(['is_default' => true]);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }

    public function test_unsetting_default_does_not_touch_other_addresses(): void
    {
        $user = User::factory()->create();
        $first = Address::factory()->for($user)->default()->create();
        $second = Address::factory()->for($user)->create(['is_default' => false]);

        $first->update(['is_default' => false]);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertFalse($second->fresh()->is_default);
    }

    public function test_default_flags_are_scoped_per_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $addressA = Address::factory()->for($userA)->default()->create();
        $addressB = Address::factory()->for($userB)->default()->create();

        // Создание дефолтного адреса у userB не должно трогать userA.
        $this->assertTrue($addressA->fresh()->is_default);
        $this->assertTrue($addressB->fresh()->is_default);
    }
}
