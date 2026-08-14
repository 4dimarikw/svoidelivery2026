<?php

namespace Tests\Unit\CatalogImport;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Services\CatalogImport\Dto\RawRow;
use Services\CatalogImport\SeenCodeCollector;
use Tests\TestCase;

class SeenCodeCollectorTest extends TestCase
{
    use RefreshDatabase;

    private function row(string $code): RawRow
    {
        $column = config('catalog_import.columns.product_code');

        return new RawRow([$column => $code], 1);
    }

    private function collector(): SeenCodeCollector
    {
        return app(SeenCodeCollector::class);
    }

    public function test_add_then_flush_persists_codes(): void
    {
        $collector = $this->collector();
        $collector->reset();

        $collector->add($this->row('CODE-A'));
        $collector->add($this->row('CODE-B'));
        $collector->flush();

        $this->assertSame(2, DB::table('catalog_import_seen_codes')->count());
        $this->assertDatabaseHas('catalog_import_seen_codes', ['external_code' => 'CODE-A']);
        $this->assertDatabaseHas('catalog_import_seen_codes', ['external_code' => 'CODE-B']);
    }

    public function test_empty_code_is_not_stored(): void
    {
        $collector = $this->collector();
        $collector->reset();

        $collector->add($this->row(''));
        $collector->flush();

        $this->assertSame(0, DB::table('catalog_import_seen_codes')->count());
    }

    public function test_duplicate_codes_within_the_same_flush_do_not_error(): void
    {
        $collector = $this->collector();
        $collector->reset();

        $collector->add($this->row('DUP'));
        $collector->add($this->row('DUP'));
        $collector->flush();

        $this->assertSame(1, DB::table('catalog_import_seen_codes')->count());
    }

    public function test_reset_truncates_previous_run_data(): void
    {
        $collector = $this->collector();
        $collector->reset();
        $collector->add($this->row('OLD'));
        $collector->flush();

        $this->assertSame(1, DB::table('catalog_import_seen_codes')->count());

        $collector->reset();

        $this->assertSame(0, DB::table('catalog_import_seen_codes')->count());
    }

    public function test_flush_with_empty_buffer_does_not_throw(): void
    {
        $collector = $this->collector();
        $collector->reset();

        $collector->flush();

        $this->assertSame(0, DB::table('catalog_import_seen_codes')->count());
    }
}
