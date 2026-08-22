<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Domain\Untappd\Actions\SyncUntappdBeerAction;
use Domain\Untappd\Models\UntappdBeer;
use Illuminate\Console\Command;
use Infrastructure\Settings\SiteSettings;
use Throwable;

class SyncUntappdBeersCommand extends Command
{
    protected $signature = 'untappd:sync-beers
        {--limit= : Сколько записей обновить за запуск, переопределяет SiteSettings::untappd_update_limit (0 = без ограничения)}
        {--dry-run : Показать, какие записи были бы обновлены — Untappd API не вызывается, изменения не сохраняются}';

    protected $description = 'Обновить данные Untappd (рейтинг, лейбл, стиль) для пива, товары с которым есть в наличии';

    public function handle(SiteSettings $settings, SyncUntappdBeerAction $action): int
    {
        $limitOption = $this->option('limit');
        $limit = $limitOption !== null ? (int) $limitOption : $settings->untappd_update_limit;

        if ($limit < 0) {
            $this->error('--limit не может быть отрицательным.');

            return self::FAILURE;
        }

        // active() — опубликован + в наличии (ProductBuilder::active()). whereHas
        // компилируется в WHERE EXISTS, дублей untappd_beers не даёт, даже если несколько
        // активных товаров ссылаются на одну и ту же запись.
        $query = UntappdBeer::query()
            ->whereHas('beerProductDetails.product', fn ($q) => $q->active())
            // NULL (никогда не синканные) — самые просроченные, идут первыми.
            ->orderByRaw('synced_at IS NULL DESC, synced_at ASC');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $beers = $query->get();

        if ($beers->isEmpty()) {
            $this->info('Нет записей untappd_beers, требующих обновления.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info('[ТЕСТОВЫЙ РЕЖИМ] Untappd API не вызывается, изменения не сохраняются.');
            $this->table(['beer_id', 'name', 'synced_at'], $beers->map(fn (UntappdBeer $beer) => [
                $beer->beer_id,
                $beer->name,
                $beer->synced_at?->toDateTimeString() ?? 'никогда',
            ])->all());

            return self::SUCCESS;
        }

        $synced = 0;
        $failed = 0;

        foreach ($beers as $beer) {
            try {
                $action($beer) ? $synced++ : $failed++;
            } catch (Throwable $e) {
                $failed++;
                $this->warn("beer_id {$beer->beer_id}: {$e->getMessage()}");
            }
        }

        $this->table(['Метрика', 'Количество'], [
            ['Кандидатов', $beers->count()],
            ['Обновлено', $synced],
            ['Ошибок', $failed],
        ]);

        return self::SUCCESS;
    }
}
