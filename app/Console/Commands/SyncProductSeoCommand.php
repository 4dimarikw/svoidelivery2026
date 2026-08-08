<?php

namespace App\Console\Commands;

use Domain\Catalog\Actions\SyncProductSeoAction;
use Domain\Catalog\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SyncProductSeoCommand extends Command
{
    protected $signature = 'catalog:sync-seo
        {--ids= : Обработать только товары с указанными id через запятую (точечный ресинк)}
        {--chunk=200 : Размер пачки (Eloquent chunk)}
        {--dry-run : Прогнать в транзакции и откатить — seo не изменится}';

    protected $description = 'Массово создать/обновить SEO-данные (title/description/keywords/text) для товаров';

    /**
     * Дублирует Domain\Catalog\Actions\SyncProductSeoAction::RELATIONS —
     * умышленно, тем же принципом, каким сам Action дублирует
     * ProductController::EAGER_LOAD (см. докблок там): не тащить чужую
     * private const.
     *
     * Дублировать обязательно, а не опционально: Action делает
     * $product->loadMissing(...) на каждом товаре по отдельности внутри
     * __invoke(). Без ->with() этих же связей на уровне запроса чанка
     * loadMissing() лениво догружал бы все 7 связей отдельно на каждый
     * товар — для сотен товаров это тысячи лишних запросов вместо
     * нескольких на чанк.
     */
    private const RELATIONS = [
        'category', 'manufacturer', 'volume', 'container', 'media',
        'beerDetails.beerStyle', 'beerDetails.untappdBeer',
    ];

    public function handle(SyncProductSeoAction $action): int
    {
        $chunkSize = (int) $this->option('chunk');

        if ($chunkSize <= 0) {
            $this->error('--chunk должен быть положительным числом.');

            return self::FAILURE;
        }

        $ids = $this->parseIdFilter();

        if ($ids === null) {
            return self::FAILURE;
        }

        $isDryRun = (bool) $this->option('dry-run');

        if ($isDryRun) {
            $this->info('[ТЕСТОВЫЙ РЕЖИМ] Изменения не будут сохранены.');
        }

        $query = Product::query()->with(self::RELATIONS);

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        $processed = 0;
        $synced = 0;
        $errors = [];

        DB::beginTransaction();

        $query->chunk($chunkSize, function ($products) use ($action, &$processed, &$synced, &$errors): void {
            foreach ($products as $product) {
                $processed++;

                try {
                    $action($product);
                    $synced++;
                } catch (Throwable $e) {
                    $errors[] = "Товар #{$product->id} ({$product->name}): {$e->getMessage()}";
                }
            }
        });

        if ($isDryRun) {
            DB::rollBack();
            $this->info('[ТЕСТОВЫЙ РЕЖИМ] Транзакция откачена.');
        } else {
            DB::commit();
        }

        $this->table(['Метрика', 'Количество'], [
            ['Обработано товаров', $processed],
            ['Синхронизировано', $synced],
            ['Ошибок', count($errors)],
        ]);

        if ($errors !== []) {
            $this->newLine();
            $this->warn('Ошибки:');
            foreach (array_slice($errors, 0, 50) as $error) {
                $this->line("  {$error}");
            }
            if (count($errors) > 50) {
                $this->line('  ... и ещё '.(count($errors) - 50));
            }
        }

        return self::SUCCESS;
    }

    /**
     * Парсит --ids в массив id товаров.
     *
     * Возвращает:
     * - пустой массив — флаг не передан, фильтр выключен (все товары);
     * - массив id — только реально существующие id из БД (неизвестные отброшены с warn);
     * - null — все переданные id неизвестны; команда должна завершиться с FAILURE.
     *
     * @return list<int>|null
     */
    private function parseIdFilter(): ?array
    {
        $raw = $this->option('ids');

        if ($raw === null || $raw === '') {
            return [];
        }

        $requested = array_values(array_unique(array_filter(
            array_map('trim', explode(',', $raw)),
            fn (string $id): bool => $id !== '' && ctype_digit($id),
        )));

        if ($requested === []) {
            return [];
        }

        $known = Product::query()->whereIn('id', $requested)->pluck('id')->all();
        $unknown = array_values(array_diff($requested, array_map('strval', $known)));

        if ($unknown !== []) {
            $this->warn('Неизвестные id товаров (пропущены): '.implode(', ', $unknown));
        }

        if ($known === []) {
            $this->error('Ни один из переданных id товаров не найден.');

            return null;
        }

        return $known;
    }
}
