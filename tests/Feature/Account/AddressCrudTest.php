<?php

namespace Tests\Feature\Account;

use Domain\Auth\Models\User;
use Domain\Profile\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressCrudTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'label' => 'Дом',
            'city' => 'Москва',
            'address' => 'Тверская, д. 1',
            'comment' => 'Позвонить за час',
        ], $overrides);
    }

    public function test_index_lists_only_the_current_users_addresses(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Address::factory()->for($user)->create();
        Address::factory()->for($other)->create();

        $response = $this->actingAs($user)->get(route('account.addresses.index'));

        $response->assertOk();
        $response->assertViewHas('addresses', fn ($addresses) => $addresses->count() === 1);
    }

    public function test_store_creates_an_address_for_the_current_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('account.addresses.store'), $this->payload());

        $this->assertSame(1, $user->addresses()->count());
        $this->assertDatabaseHas('addresses', [
            'city' => 'Москва',
            'user_id' => $user->id,
            'label' => 'Дом',
            'address' => 'Тверская, д. 1',
            'comment' => 'Позвонить за час',
        ]);
    }

    public function test_store_requires_city_and_address(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('account.addresses.create'))
            ->post(route('account.addresses.store'), []);

        $response->assertSessionHasErrors(['city', 'address']);
    }

    public function test_ajax_store_redirects_via_x_redirect_header(): void
    {
        // AddressController::store() — единственная ajax-форма в этой фиче,
        // которая реально навигирует: X-Redirect на account.addresses.index.
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('account.addresses.store'), $this->payload());

        $response->assertOk();
        $response->assertHeader('X-Redirect', route('account.addresses.index'));
    }

    public function test_owner_can_view_edit_form(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create();

        $this->actingAs($user)->get(route('account.addresses.edit', $address))->assertOk();
    }

    public function test_non_owner_gets_403_on_edit(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        $address = Address::factory()->for($user)->create();

        $this->actingAs($stranger)->get(route('account.addresses.edit', $address))->assertForbidden();
    }

    public function test_non_owner_gets_403_on_update(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        $address = Address::factory()->for($user)->create();

        $this->actingAs($stranger)
            ->put(route('account.addresses.update', $address), $this->payload())
            ->assertForbidden();
    }

    public function test_non_owner_gets_403_on_destroy(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        $address = Address::factory()->for($user)->create();

        $this->actingAs($stranger)
            ->delete(route('account.addresses.destroy', $address))
            ->assertForbidden();

        $this->assertDatabaseHas('addresses', ['id' => $address->id]);
    }

    public function test_owner_can_update_their_address(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create(['city' => 'Старый город']);

        $this->actingAs($user)->put(route('account.addresses.update', $address), $this->payload(['city' => 'Новый город']));

        $this->assertSame('Новый город', $address->fresh()->city);
    }

    public function test_ajax_update_stays_inline_without_x_redirect(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create();

        $response = $this->actingAs($user)
            ->putJson(route('account.addresses.update', $address), $this->payload());

        $response->assertOk();
        $response->assertHeaderMissing('X-Redirect');
    }

    public function test_owner_can_delete_their_address(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create();

        $response = $this->actingAs($user)->delete(route('account.addresses.destroy', $address));

        $response->assertRedirect(route('account.addresses.index'));
        $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
    }
}
