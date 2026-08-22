<?php

namespace Tests\Feature\Untappd;

use Domain\Catalog\Enums\ProductStatus;
use Domain\Catalog\Models\BeerProductDetail;
use Domain\Catalog\Models\Product;
use Domain\Untappd\Models\UntappdBeer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Infrastructure\Settings\SiteSettings;
use Mockery\MockInterface;
use Services\Untappd\DTOs\BeerDTO;
use Services\Untappd\DTOs\BeerResponseDTO;
use Services\Untappd\DTOs\MetaDTO;
use Services\Untappd\DTOs\UntappdApiResponse;
use Services\Untappd\Repositories\UntappdInterface;
use Tests\TestCase;

class SyncUntappdBeersCommandTest extends TestCase
{
    use RefreshDatabase;

    private function linkedBeer(array $productOverrides = [], array $beerOverrides = []): UntappdBeer
    {
        $product = Product::factory()->create(array_merge([
            'status' => ProductStatus::PUBLISHED,
            'in_stock' => true,
            'stock_quantity' => 10,
        ], $productOverrides));

        $beer = UntappdBeer::factory()->create($beerOverrides);

        BeerProductDetail::factory()->create([
            'product_id' => $product->id,
            'untappd_beer_id' => $beer->id,
        ]);

        return $beer;
    }

    private function okResponse(UntappdBeer $beer): UntappdApiResponse
    {
        return new UntappdApiResponse(
            meta: new MetaDTO(200, null, null),
            rate_limit: null,
            response: new BeerResponseDTO(BeerDTO::fromArray([
                'bid' => $beer->beer_id,
                'beer_name' => $beer->name,
                'beer_label' => '',
                'beer_image' => 'https://example.com/label.jpg',
                'beer_description' => 'desc',
                'beer_style' => $beer->style,
                'beer_slug' => 'slug',
                'created_at' => now()->toIso8601String(),
                'rating_count' => $beer->rating_count,
                'rating_score' => 4.0,
                'brewery' => $beer->brewery,
                'beer_active' => true,
            ])),
        );
    }

    private function expectNoApiCalls(): void
    {
        $this->mock(UntappdInterface::class, function (MockInterface $mock) {
            $mock->shouldReceive('get')->never();
        });
    }

    public function test_inactive_product_status_excludes_its_beer(): void
    {
        // synced_at=null явно — фабрика по умолчанию ставит now(), а тест
        // должен доказать, что запись НЕ тронута, а не просто угадать null.
        $beer = $this->linkedBeer(['status' => ProductStatus::DRAFT], ['synced_at' => null]);

        $this->expectNoApiCalls();

        $this->artisan('untappd:sync-beers')->assertExitCode(0);

        $this->assertNull($beer->fresh()->synced_at);
    }

    public function test_out_of_stock_product_excludes_its_beer(): void
    {
        $beer = $this->linkedBeer(['stock_quantity' => 0], ['synced_at' => null]);

        $this->expectNoApiCalls();

        $this->artisan('untappd:sync-beers')->assertExitCode(0);

        $this->assertNull($beer->fresh()->synced_at);
    }

    public function test_beer_without_a_linked_product_is_excluded(): void
    {
        $beer = UntappdBeer::factory()->create(['synced_at' => null]);

        $this->expectNoApiCalls();

        $this->artisan('untappd:sync-beers')->assertExitCode(0);

        $this->assertNull($beer->fresh()->synced_at);
    }

    public function test_never_synced_beer_is_updated_before_a_previously_synced_one(): void
    {
        $stale = $this->linkedBeer(beerOverrides: ['synced_at' => null]);
        $recent = $this->linkedBeer(beerOverrides: ['synced_at' => now()->subDay()]);
        $recentOriginalSyncedAt = $recent->synced_at;

        $this->mock(UntappdInterface::class, function (MockInterface $mock) use ($stale) {
            $mock->shouldReceive('get')->once()
                ->with("beer/info/{$stale->beer_id}", ['db' => 1])
                ->andReturn($this->okResponse($stale));
        });

        $this->artisan('untappd:sync-beers', ['--limit' => 1])->assertExitCode(0);

        $this->assertNotNull($stale->fresh()->synced_at);
        $this->assertTrue($recent->fresh()->synced_at->equalTo($recentOriginalSyncedAt));
    }

    public function test_older_synced_at_is_updated_before_a_newer_one(): void
    {
        $older = $this->linkedBeer(beerOverrides: ['synced_at' => now()->subDays(10)]);
        $newer = $this->linkedBeer(beerOverrides: ['synced_at' => now()->subDay()]);
        $newerOriginalSyncedAt = $newer->synced_at;

        $this->mock(UntappdInterface::class, function (MockInterface $mock) use ($older) {
            $mock->shouldReceive('get')->once()
                ->with("beer/info/{$older->beer_id}", ['db' => 1])
                ->andReturn($this->okResponse($older));
        });

        $this->artisan('untappd:sync-beers', ['--limit' => 1])->assertExitCode(0);

        $this->assertTrue($older->fresh()->synced_at->greaterThan(Carbon::now()->subMinute()));
        $this->assertTrue($newer->fresh()->synced_at->equalTo($newerOriginalSyncedAt));
    }

    public function test_zero_limit_setting_processes_all_candidates(): void
    {
        app(SiteSettings::class)->untappd_update_limit = 0;
        app(SiteSettings::class)->save();

        $first = $this->linkedBeer(beerOverrides: ['synced_at' => null]);
        $second = $this->linkedBeer(beerOverrides: ['synced_at' => null]);

        $this->mock(UntappdInterface::class, function (MockInterface $mock) use ($first, $second) {
            $mock->shouldReceive('get')->twice()->andReturnUsing(
                fn (string $endpoint) => $this->okResponse(
                    str_contains($endpoint, (string) $first->beer_id) ? $first : $second
                )
            );
        });

        $this->artisan('untappd:sync-beers')->assertExitCode(0);

        $this->assertNotNull($first->fresh()->synced_at);
        $this->assertNotNull($second->fresh()->synced_at);
    }

    public function test_dry_run_does_not_call_the_api_or_persist_changes(): void
    {
        $beer = $this->linkedBeer(beerOverrides: ['synced_at' => null]);

        $this->expectNoApiCalls();

        $this->artisan('untappd:sync-beers', ['--dry-run' => true])->assertExitCode(0);

        $this->assertNull($beer->fresh()->synced_at);
    }

    public function test_a_failing_candidate_does_not_stop_the_rest(): void
    {
        $failing = $this->linkedBeer(beerOverrides: ['synced_at' => now()->subDays(5)]);
        $ok = $this->linkedBeer(beerOverrides: ['synced_at' => now()->subDays(1)]);
        $failingOriginalSyncedAt = $failing->synced_at;

        $this->mock(UntappdInterface::class, function (MockInterface $mock) use ($failing, $ok) {
            $mock->shouldReceive('get')
                ->with("beer/info/{$failing->beer_id}", ['db' => 1])
                ->andReturn(new UntappdApiResponse(new MetaDTO(500, 'boom', null), null, null));

            $mock->shouldReceive('get')
                ->with("beer/info/{$ok->beer_id}", ['db' => 1])
                ->andReturn($this->okResponse($ok));
        });

        $this->artisan('untappd:sync-beers')->assertExitCode(0);

        $this->assertTrue($failing->fresh()->synced_at->equalTo($failingOriginalSyncedAt));
        $this->assertNotNull($ok->fresh()->synced_at);
    }

    public function test_negative_limit_option_fails(): void
    {
        $this->artisan('untappd:sync-beers', ['--limit' => -1])->assertExitCode(1);
    }
}
