<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\Enums\LoggableEventType;
use App\Events\UntappdBeerSynced;
use App\Events\UntappdBeerSyncFailed;
use App\MoonShine\Pages\EventLoggingSettingsPage;
use Domain\Logging\Models\EventLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\Settings\EventLoggingSettings;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

/**
 * App\Listeners\PersistEventLog — единственный сток для всех
 * App\Events\LoggableEvent — теперь читает Infrastructure\Settings\
 * EventLoggingSettings::$disabled_event_types и молча пропускает запись
 * event_logs для отключённых типов (App\MoonShine\Pages\EventLoggingSettingsPage).
 */
class EventLoggingSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function superuser(): MoonshineUser
    {
        return MoonshineUser::query()->create([
            'email' => 'event-logging-smoke@test.local',
            'password' => bcrypt('password'),
            'name' => 'Smoke Test',
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
        ]);
    }

    public function test_default_settings_still_log_every_event_type(): void
    {
        event(new UntappdBeerSynced(beerId: 1, name: 'Beer', brewery: 'Brewery', ratingCount: 1, ratingScore: 4.0));

        $this->assertDatabaseHas('event_logs', ['event_type' => 'untappd_beer.synced']);
    }

    public function test_disabled_event_type_is_not_persisted(): void
    {
        $settings = app(EventLoggingSettings::class);
        $settings->disabled_event_types = ['untappd_beer.synced'];
        $settings->save();

        event(new UntappdBeerSynced(beerId: 1, name: 'Beer', brewery: 'Brewery', ratingCount: 1, ratingScore: 4.0));

        $this->assertSame(0, EventLog::query()->where('event_type', 'untappd_beer.synced')->count());
    }

    public function test_disabling_one_type_does_not_affect_others(): void
    {
        $settings = app(EventLoggingSettings::class);
        $settings->disabled_event_types = ['untappd_beer.synced'];
        $settings->save();

        event(new UntappdBeerSyncFailed(beerId: 1, exceptionClass: 'UntappdApiError', errorMessage: 'boom'));

        $this->assertDatabaseHas('event_logs', ['event_type' => 'untappd_beer.sync_failed']);
    }

    public function test_settings_page_renders_a_switcher_for_every_event_type(): void
    {
        $response = $this->actingAs($this->superuser(), 'moonshine')
            ->get(app(EventLoggingSettingsPage::class)->getUrl());

        $response->assertOk();

        foreach (LoggableEventType::cases() as $type) {
            $response->assertSee($type->label());
        }
    }

    public function test_saving_the_page_turns_off_the_chosen_type(): void
    {
        $page = app(EventLoggingSettingsPage::class);

        // Switcher не шлёт ключ вовсе, когда выключен (см. save()) — здесь
        // просто не передаём log_untappd_beer_synced в теле запроса.
        $payload = ['method' => 'save'];
        foreach (LoggableEventType::cases() as $type) {
            if ($type !== LoggableEventType::UntappdBeerSynced) {
                $payload['log_'.str_replace('.', '_', $type->value)] = '1';
            }
        }

        $this->actingAs($this->superuser(), 'moonshine')
            ->post(route('moonshine.method', ['pageUri' => $page->getUriKey()]), $payload)
            ->assertOk();

        app()->forgetInstance(EventLoggingSettings::class);

        $this->assertSame(
            ['untappd_beer.synced'],
            app(EventLoggingSettings::class)->disabled_event_types,
        );
    }
}
