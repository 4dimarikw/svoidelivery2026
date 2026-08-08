<?php

namespace Tests\Feature\Domain;

use Domain\Untappd\Models\UntappdBeer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UntappdBeerTest extends TestCase
{
    use RefreshDatabase;

    public function test_relative_url_is_prefixed_with_the_untappd_domain(): void
    {
        // Реальный формат, который пишет ResolveBeerStyleStage до этого
        // фикса — старые засинканные записи в БД останутся такими.
        $beer = UntappdBeer::factory()->create(['url' => '/b/hop-machine/12345']);

        $this->assertSame('https://untappd.com/b/hop-machine/12345', $beer->url);
    }

    public function test_absolute_url_is_not_prefixed_twice(): void
    {
        $beer = UntappdBeer::factory()->create(['url' => 'https://untappd.com/b/hop-machine/12345']);

        $this->assertSame('https://untappd.com/b/hop-machine/12345', $beer->url);
    }

    public function test_blank_url_stays_null(): void
    {
        $beer = UntappdBeer::factory()->create(['url' => null]);

        $this->assertNull($beer->url);
    }
}
