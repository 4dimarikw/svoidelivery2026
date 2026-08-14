<?php

namespace Services\CatalogImport;

use App\Events\CatalogImportCompleted;
use App\Events\CatalogImportFailed;
use App\Events\CatalogVolumeDiscrepanciesDetected;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\DB;
use Services\CatalogImport\Dto\ImportContext;
use Services\CatalogImport\Dto\ImportOptions;
use Services\CatalogImport\Dto\ImportReport;
use Throwable;

readonly class CsvParserService
{
    public function __construct(
        private CsvReader $reader,
        private Pipeline $pipeline,
        private SeenCodeCollector $seenCodes,
    ) {}

    /**
     * @throws Throwable
     */
    public function import(string $path, ?ImportOptions $options = null): ImportReport
    {
        $options ??= new ImportOptions;

        $startedAt = hrtime(true);
        $report = new ImportReport;

        $stages = config('catalog_import.stages', []);
        $postCommitStages = config('catalog_import.post_commit_stages', []);
        $encoding = config('catalog_import.encoding', 'Windows-1251');
        $delimiter = config('catalog_import.delimiter', ';');
        $warningLimit = $options->warningLimit ?? (int) config('catalog_import.warning_limit', 1000);
        $transactionMode = $options->transactionMode ?? config('catalog_import.transaction_mode', 'row');
        $chunkSize = $options->chunkSize ?? (int) config('catalog_import.chunk_size', 500);

        // Обнуление пропавших товаров (ZeroOutStaleProductsAction) не должно
        // видеть ни dry-run прогон (ничего реально не импортировано), ни
        // частичный по --categories (иначе catalog:import --categories=beer
        // обнулил бы весь остальной каталог) — см. SeenCodeCollector.
        $trackSeenCodes = ! $options->dryRun && $options->categoryFilter === [];
        $report->seenCodesTracked = $trackSeenCodes;

        try {
            $lookups = new LookupCache;

            if ($trackSeenCodes) {
                $this->seenCodes->reset();
            }

            // dry-run: внешняя транзакция-обёртка откатит всё в finally
            if ($options->dryRun) {
                DB::beginTransaction();
            }

            try {
                if ($transactionMode === 'chunk') {
                    $this->importChunked($path, $encoding, $delimiter, $stages, $postCommitStages, $lookups, $report, $options, $warningLimit, $chunkSize, $trackSeenCodes);
                } else {
                    $this->importRows($path, $encoding, $delimiter, $stages, $postCommitStages, $lookups, $report, $options, $warningLimit, $transactionMode, $trackSeenCodes);
                }

                if ($trackSeenCodes) {
                    $this->seenCodes->flush();
                }

                // Пишется в отчёт до dry-run-проверки: событие при dry-run не диспатчится,
                // но CatalogImportCommand логирует длительность в любом режиме.
                $report->durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

                if (! $options->dryRun) {
                    // NB: after the ProductVariation collapse (flat products.* schema), this
                    // event's signature dropped brandsCreated→manufacturersCreated and lost
                    // variationsCreated/variationsUpdated entirely. If CatalogImportCompleted
                    // (transferred separately into Support\Logging\Events) still declares the
                    // old constructor, update it to match this call.
                    event(new CatalogImportCompleted(
                        path: $path,
                        processed: $report->processed,
                        skipped: $report->skipped,
                        manufacturersCreated: $report->manufacturersCreated,
                        stylesCreated: $report->stylesCreated,
                        productsCreated: $report->productsCreated,
                        productsUpdated: $report->productsUpdated,
                        warningsCount: $report->warningsTotal,
                        warnings: $report->warnings,
                        durationMs: $report->durationMs,
                    ));

                    if ($report->volumeDiscrepancies !== []) {
                        event(new CatalogVolumeDiscrepanciesDetected($path, $report->volumeDiscrepancies));
                    }
                }

                return $report;
            } finally {
                if ($options->dryRun) {
                    DB::rollBack();
                }
            }
        } catch (Throwable $e) {
            if (! $options->dryRun) {
                event(new CatalogImportFailed($path, $e::class, $e->getMessage()));
            }
            throw $e;
        }
    }

    /**
     * Режим 'row' / 'none': одна транзакция на строку или вообще без транзакции.
     *
     * @param  list<class-string>  $stages
     * @param  list<class-string>  $postCommitStages
     *
     * @throws Throwable
     */
    private function importRows(
        string $path,
        string $encoding,
        string $delimiter,
        array $stages,
        array $postCommitStages,
        LookupCache $lookups,
        ImportReport $report,
        ImportOptions $options,
        int $warningLimit,
        string $transactionMode,
        bool $trackSeenCodes,
    ): void {
        foreach ($this->reader->read($path, $encoding, $delimiter, fn () => $report->malformedRows++) as $row) {
            $report->processed++;

            // До пайплайна, вне транзакции строки — присутствие в CSV
            // фиксируется независимо от того, дойдёт ли строка до
            // PersistProductStage (см. SeenCodeCollector).
            if ($trackSeenCodes) {
                $this->seenCodes->add($row);
            }

            $ctx = new ImportContext($row, $lookups, $options->dryRun, $options->categoryFilter);

            if ($transactionMode === 'none') {
                $ctx = $this->runPipeline($ctx, $stages);
            } else {
                // One transaction per row guarantees no orphan Product without variation.
                // Trade-off: O(n) transactions on large files. Use transaction_mode=chunk for bulk runs.
                $ctx = DB::transaction(fn () => $this->runPipeline($ctx, $stages));
            }

            $this->runPostCommitStages($ctx, $postCommitStages);
            $this->applyCounters($report, $ctx, $warningLimit);
        }
    }

    /**
     * Режим 'chunk': одна транзакция на chunk_size строк.
     * Теряет per-row атомарность — opt-in через конфиг.
     *
     * @param  list<class-string>  $stages
     * @param  list<class-string>  $postCommitStages
     *
     * @throws Throwable
     */
    private function importChunked(
        string $path,
        string $encoding,
        string $delimiter,
        array $stages,
        array $postCommitStages,
        LookupCache $lookups,
        ImportReport $report,
        ImportOptions $options,
        int $warningLimit,
        int $chunkSize,
        bool $trackSeenCodes,
    ): void {
        $buffer = [];

        $flushBuffer = function (array $rows) use ($stages, $postCommitStages, $lookups, $report, $options, $warningLimit): void {
            $contexts = DB::transaction(function () use ($rows, $stages, $lookups, $options): array {
                $results = [];
                foreach ($rows as $row) {
                    $ctx = new ImportContext($row, $lookups, $options->dryRun, $options->categoryFilter);
                    $results[] = $this->runPipeline($ctx, $stages);
                }

                return $results;
            });

            foreach ($contexts as $ctx) {
                $this->runPostCommitStages($ctx, $postCommitStages);
                $this->applyCounters($report, $ctx, $warningLimit);
            }
        };

        foreach ($this->reader->read($path, $encoding, $delimiter, fn () => $report->malformedRows++) as $row) {
            $report->processed++;

            // Вне DB::transaction чанка — та же причина, что в importRows().
            if ($trackSeenCodes) {
                $this->seenCodes->add($row);
            }

            $buffer[] = $row;

            if (count($buffer) >= $chunkSize) {
                $flushBuffer($buffer);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            $flushBuffer($buffer);
        }
    }

    /**
     * Запускает пост-коммит стейджи (вне транзакции) для успешно обработанных строк.
     *
     * @param  list<class-string>  $stages
     */
    private function runPostCommitStages(ImportContext $ctx, array $stages): void
    {
        // dry-run: post-commit stages are skipped centrally here so individual stages
        // don't need their own dryRun guards. skip and empty-stages checks follow.
        if ($ctx->dryRun || $ctx->skip || $stages === []) {
            return;
        }

        $this->runPipeline($ctx, $stages);
    }

    /**
     * @param  list<class-string>  $stages
     */
    private function runPipeline(ImportContext $ctx, array $stages): ImportContext
    {
        return $this->pipeline
            ->send($ctx)
            ->through($stages)
            ->thenReturn();
    }

    private function applyCounters(ImportReport $report, ImportContext $ctx, int $warningLimit): void
    {
        $report->addWarnings($ctx->row->lineNumber, $ctx->warnings, $warningLimit);
        $report->addVolumeDiscrepancies($ctx->row->lineNumber, $ctx->attributes['volume_discrepancies'] ?? []);

        if ($ctx->skip) {
            $report->skipped++;

            return;
        }

        if ($ctx->brand?->wasRecentlyCreated) {
            $report->manufacturersCreated++;
        }

        if ($ctx->beerStyle?->wasRecentlyCreated) {
            $report->stylesCreated++;
        }

        if ($ctx->product !== null) {
            if ($ctx->product->wasRecentlyCreated) {
                $report->productsCreated++;
            } elseif ($ctx->product->wasChanged()) {
                $report->productsUpdated++;
            } else {
                $report->productsUnchanged++;
            }
        }

        if ($ctx->imageAttachedThisRow) {
            $report->imagesAttached++;
        }
    }
}
