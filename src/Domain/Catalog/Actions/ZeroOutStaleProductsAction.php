<?php

namespace Domain\Catalog\Actions;

use Domain\Catalog\Builders\ProductBuilder;
use Domain\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;
use Infrastructure\Settings\CatalogImportSettings;

/**
 * Обнуляет stock_quantity/in_stock у товаров, отсутствующих в последнем CSV
 * от 1С — 1С не гарантирует, что снятый с продажи товар вообще попадёт в
 * выгрузку, а PersistProductStage (Services\CatalogImport) работает построчно
 * и ничего не знает про строки, которых в файле нет.
 *
 * "Отсутствует в CSV" = нет строки с таким КодТовара в catalog_import_seen_codes,
 * которую CsvParserService/SeenCodeCollector заполняет ДО пайплайна стейджей —
 * то есть попадание в файл, а не факт успешного импорта. Так ошибка в реестре
 * категорий или в правилах NormalizeRowStage (например, категория случайно
 * попала в normalize.excluded_categories) не обнуляет товары, которые 1С
 * по-прежнему честно выгружает.
 *
 * Диспатчится только из ZeroOutStaleProductsJob — обнуление всегда идёт через
 * Eloquent::update() (не query-builder), потому что in_stock входит в
 * Product::SEO_WATCHED_ATTRIBUTES, а массовый update такой хук не поднимет.
 */
final readonly class ZeroOutStaleProductsAction
{
    private const TABLE = 'catalog_import_seen_codes';

    private const CHUNK_SIZE = 500;

    public function __construct(private CatalogImportSettings $settings) {}

    public function __invoke(): ZeroOutStaleProductsResult
    {
        $seenTotal = (int) DB::table(self::TABLE)->count();

        if ($seenTotal === 0) {
            return new ZeroOutStaleProductsResult(
                zeroed: 0,
                staleFound: 0,
                inStockTotal: 0,
                seenTotal: 0,
                maxPercent: $this->settings->zero_out_max_percent,
                aborted: true,
                reason: 'no_seen_codes',
            );
        }

        $staleQuery = $this->staleQuery();
        $staleCount = (int) $staleQuery->count();
        $inStockTotal = (int) Product::query()->where('in_stock', true)->count();

        if ($inStockTotal > 0 && ($staleCount / $inStockTotal * 100) > $this->settings->zero_out_max_percent) {
            // Порог превышен — вероятно, битый/обрезанный CSV. Ни одного
            // товара не трогаем и не чистим seen_codes, чтобы можно было
            // разобраться руками, прежде чем пробовать снова.
            return new ZeroOutStaleProductsResult(
                zeroed: 0,
                staleFound: $staleCount,
                inStockTotal: $inStockTotal,
                seenTotal: $seenTotal,
                maxPercent: $this->settings->zero_out_max_percent,
                aborted: true,
                reason: 'threshold_exceeded',
            );
        }

        $zeroed = 0;

        $this->staleQuery()->chunkById(self::CHUNK_SIZE, function ($products) use (&$zeroed): void {
            foreach ($products as $product) {
                $product->update(['stock_quantity' => 0, 'in_stock' => false]);
                $zeroed++;
            }
        });

        DB::table(self::TABLE)->truncate();

        return new ZeroOutStaleProductsResult(
            zeroed: $zeroed,
            staleFound: $staleCount,
            inStockTotal: $inStockTotal,
            seenTotal: $seenTotal,
            maxPercent: $this->settings->zero_out_max_percent,
            aborted: false,
        );
    }

    /**
     * Условие `in_stock OR stock_quantity > 0` делает действие идемпотентным:
     * повторный прогон (или повторный импорт без изменений) не переписывает
     * уже обнулённые товары и не гоняет их через SEO-хук заново.
     */
    private function staleQuery(): ProductBuilder
    {
        return Product::query()
            ->whereNotIn('external_code', fn ($q) => $q->select('external_code')->from(self::TABLE))
            ->where(fn ($q) => $q->where('in_stock', true)->orWhere('stock_quantity', '>', 0));
    }
}
