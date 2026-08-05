<?php

namespace Tests\Feature\CatalogImport;

use Domain\Catalog\Enums\CategoryMatchType;
use Domain\Catalog\Enums\CategoryMatchWhen;
use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\CategoryMatchRule;
use Domain\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalogCsv;
use Tests\TestCase;

class CatalogImportCommandTest extends TestCase
{
    use BuildsCatalogCsv;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->cleanupCatalogCsvTmpFiles();

        parent::tearDown();
    }

    public function test_validate_registry_preflight_blocks_the_import(): void
    {
        // 'beer' уже несёт единственное активное alcohol/default правило —
        // второе где угодно роняет catalog:validate-registry.
        $extra = Category::query()->create([
            'slug' => 'test-cmd-broken',
            'code' => 'test_cmd_broken',
            'name' => 'Тест',
            'is_active' => true,
        ]);
        CategoryMatchRule::query()->create([
            'category_id' => $extra->id,
            'type' => CategoryMatchType::Alcohol,
            'match_when' => CategoryMatchWhen::DefaultRule,
            'priority' => 999,
            'is_active' => true,
        ]);

        $path = $this->writeCatalogCsv([$this->validCatalogRow()]);

        $this->artisan('catalog:import', ['path' => $path])->assertExitCode(1);

        $this->assertSame(0, Product::query()->count());
    }

    public function test_explicit_path_argument_imports_without_ftp(): void
    {
        $path = $this->writeCatalogCsv([$this->validCatalogRow()]);

        $this->artisan('catalog:import', ['path' => $path])->assertExitCode(0);

        $this->assertSame(1, Product::query()->count());
    }

    public function test_dry_run_flag_leaves_database_untouched(): void
    {
        $path = $this->writeCatalogCsv([$this->validCatalogRow()]);

        $this->artisan('catalog:import', ['path' => $path, '--dry-run' => true])->assertExitCode(0);

        $this->assertSame(0, Product::query()->count());
    }

    public function test_categories_option_filters_by_slug(): void
    {
        $path = $this->writeCatalogCsv([
            $this->validCatalogRow(['product_code' => 'CODE-BEER']),
            $this->validCatalogRow([
                'product_code' => 'CODE-ACCESSORY',
                'category' => 'Сопутствующие товары',
                'abv' => '',
                'name_full' => 'Тара пэт 1л',
            ]),
        ]);

        $this->artisan('catalog:import', ['path' => $path, '--categories' => 'beer'])->assertExitCode(0);

        $this->assertSame(1, Product::query()->count());
        $this->assertNotNull(Product::query()->firstWhere('external_code', 'CODE-BEER'));
    }

    public function test_unknown_category_slug_fails_the_command(): void
    {
        $path = $this->writeCatalogCsv([$this->validCatalogRow()]);

        $this->artisan('catalog:import', ['path' => $path, '--categories' => 'нет-такого-slug'])
            ->assertExitCode(1);
    }

    public function test_missing_file_fails_without_throwing(): void
    {
        $this->artisan('catalog:import', ['path' => sys_get_temp_dir().'/does-not-exist-'.uniqid().'.csv'])
            ->assertExitCode(1);
    }
}
