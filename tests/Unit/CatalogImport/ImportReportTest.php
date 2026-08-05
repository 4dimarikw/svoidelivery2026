<?php

namespace Tests\Unit\CatalogImport;

use Services\CatalogImport\Dto\ImportReport;
use Tests\TestCase;

class ImportReportTest extends TestCase
{
    public function test_add_warnings_without_limit_keeps_everything(): void
    {
        $report = new ImportReport;

        $report->addWarnings(1, [
            ['stage' => 'A', 'message' => 'm1', 'value' => 'v1'],
            ['stage' => 'B', 'message' => 'm2', 'value' => 'v2'],
        ]);

        $this->assertSame(2, $report->warningsTotal);
        $this->assertCount(2, $report->warnings);
        $this->assertSame(['line' => 1, 'stage' => 'A', 'message' => 'm1', 'value' => 'v1'], $report->warnings[0]);
    }

    public function test_add_warnings_respects_limit_but_total_keeps_growing(): void
    {
        $report = new ImportReport;

        $report->addWarnings(1, [
            ['stage' => 'A', 'message' => 'm1', 'value' => 'v1'],
            ['stage' => 'B', 'message' => 'm2', 'value' => 'v2'],
        ], limit: 1);

        $report->addWarnings(2, [
            ['stage' => 'C', 'message' => 'm3', 'value' => 'v3'],
        ], limit: 1);

        // Хранится не больше limit=1 записи, но warningsTotal считает все три.
        $this->assertCount(1, $report->warnings);
        $this->assertSame(3, $report->warningsTotal);
        $this->assertSame('A', $report->warnings[0]['stage']);
    }

    public function test_default_counters_start_at_zero(): void
    {
        $report = new ImportReport;

        $this->assertSame(0, $report->processed);
        $this->assertSame(0, $report->skipped);
        $this->assertSame(0, $report->productsCreated);
        $this->assertSame([], $report->warnings);
    }
}
