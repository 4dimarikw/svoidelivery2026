<?php

namespace Tests\Feature\Untappd;

use App\Events\UntappdBeerSynced;
use App\Events\UntappdBeerSyncFailed;
use Domain\Untappd\Actions\SyncUntappdBeerAction;
use Domain\Untappd\Models\UntappdBeer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery\MockInterface;
use Services\Untappd\DTOs\BeerDTO;
use Services\Untappd\DTOs\BeerResponseDTO;
use Services\Untappd\DTOs\MetaDTO;
use Services\Untappd\DTOs\UntappdApiResponse;
use Services\Untappd\Repositories\UntappdInterface;
use Tests\TestCase;

class SyncUntappdBeerActionTest extends TestCase
{
    use RefreshDatabase;

    private function beerDto(array $overrides = []): BeerDTO
    {
        return BeerDTO::fromArray(array_merge([
            'bid' => 12345,
            'beer_name' => 'Hop Machine',
            'beer_label' => 'https://example.com/fallback-label.jpg',
            'beer_label_hd' => '',
            'beer_image' => 'https://example.com/label.jpg',
            'beer_abv' => 6.5,
            'beer_ibu' => 60,
            'beer_description' => 'New description',
            'beer_style' => 'IPA - American',
            'beer_slug' => 'hop-machine',
            'created_at' => now()->toIso8601String(),
            'rating_count' => 100,
            'rating_score' => 4.2,
            'brewery' => 'Test Brewery',
            'beer_active' => true,
        ], $overrides));
    }

    private function apiResponse(?BeerDTO $beer, int $code = 200, ?string $errorDetail = null): UntappdApiResponse
    {
        return new UntappdApiResponse(
            meta: new MetaDTO($code, $errorDetail, null),
            rate_limit: null,
            response: $beer !== null ? new BeerResponseDTO($beer) : null,
        );
    }

    public function test_successful_response_updates_the_beer_and_dispatches_synced_event(): void
    {
        Event::fake([UntappdBeerSynced::class, UntappdBeerSyncFailed::class]);

        $beer = UntappdBeer::factory()->create(['beer_id' => 12345, 'synced_at' => null]);

        $this->mock(UntappdInterface::class, function (MockInterface $mock) {
            $mock->shouldReceive('get')
                ->once()
                ->with('beer/info/12345', ['db' => 1])
                ->andReturn($this->apiResponse($this->beerDto()));
        });

        $action = new SyncUntappdBeerAction;
        $result = $action($beer);

        $this->assertTrue($result);

        $beer->refresh();
        $this->assertSame('Hop Machine', $beer->name);
        $this->assertSame('Test Brewery', $beer->brewery);
        $this->assertSame('IPA - American', $beer->style);
        $this->assertSame('New description', $beer->description);
        $this->assertSame(100, $beer->rating_count);
        $this->assertEquals(4.2, (float) $beer->rating_score);
        $this->assertSame('https://example.com/label.jpg', $beer->label);
        $this->assertSame('https://untappd.com/b/hop-machine/12345', $beer->url);
        $this->assertNotNull($beer->synced_at);

        Event::assertDispatched(UntappdBeerSynced::class, fn (UntappdBeerSynced $event) => $event->beerId === 12345);
        Event::assertNotDispatched(UntappdBeerSyncFailed::class);
    }

    public function test_missing_beer_image_falls_back_to_beer_label(): void
    {
        Event::fake();

        $beer = UntappdBeer::factory()->create(['beer_id' => 12345]);

        $this->mock(UntappdInterface::class, function (MockInterface $mock) {
            $mock->shouldReceive('get')->once()->andReturn($this->apiResponse($this->beerDto([
                'beer_image' => '',
            ])));
        });

        (new SyncUntappdBeerAction)($beer);

        $this->assertSame('https://example.com/fallback-label.jpg', $beer->fresh()->label);
    }

    public function test_non_200_meta_code_leaves_the_beer_untouched_and_dispatches_failed_event(): void
    {
        Event::fake([UntappdBeerSynced::class, UntappdBeerSyncFailed::class]);

        $beer = UntappdBeer::factory()->create(['beer_id' => 12345, 'name' => 'Old Name']);

        $this->mock(UntappdInterface::class, function (MockInterface $mock) {
            $mock->shouldReceive('get')->once()->andReturn(
                $this->apiResponse(null, 404, 'beer not found')
            );
        });

        $result = (new SyncUntappdBeerAction)($beer);

        $this->assertFalse($result);
        $this->assertSame('Old Name', $beer->fresh()->name);

        Event::assertDispatched(UntappdBeerSyncFailed::class, fn (UntappdBeerSyncFailed $event) => $event->beerId === 12345
            && $event->errorMessage === 'beer not found');
        Event::assertNotDispatched(UntappdBeerSynced::class);
    }

    public function test_missing_beer_in_response_dispatches_failed_event(): void
    {
        Event::fake([UntappdBeerSyncFailed::class]);

        $beer = UntappdBeer::factory()->create(['beer_id' => 12345]);

        $this->mock(UntappdInterface::class, function (MockInterface $mock) {
            $mock->shouldReceive('get')->once()->andReturn($this->apiResponse(null, 200));
        });

        $result = (new SyncUntappdBeerAction)($beer);

        $this->assertFalse($result);
        Event::assertDispatched(UntappdBeerSyncFailed::class);
    }
}
