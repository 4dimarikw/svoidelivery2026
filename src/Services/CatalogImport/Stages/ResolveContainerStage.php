<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Domain\Catalog\Models\Container;
use Services\CatalogImport\CategoryRegistry;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;

/**
 * Тип тары: определяет Container для текущего товара.
 *
 * Логика (3 шага):
 * 1. Категория не отмечена `container: true` в реестре (categories.{slug}.container) →
 *    стейдж пропускается целиком.
 * 2. У категории задан фиксированный `container_code` (напр. pet-tare-packages → pet) —
 *    значение `Упаковка` игнорируется.
 * 3. Иначе: определение только по колонке `Упаковка` через first-match-wins
 *    маппинг `container_map`. Нет совпадений → warning "unknown container type".
 *    Код найден, но не сидирован → warning "container code not seeded".
 */
final class ResolveContainerStage implements ImportStage
{
    private readonly CategoryRegistry $categories;

    public function __construct(?CategoryRegistry $categories = null)
    {
        $this->categories = $categories ?? app(CategoryRegistry::class);
    }

    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        $slug = $ctx->category?->slug;

        // Step 1 — categories not flagged container: true are skipped.
        if ($slug === null || ! $this->categories->expectsContainer($slug)) {
            return $next($ctx);
        }

        $fixedCode = $this->categories->containerCode($slug);

        if ($fixedCode !== null) {
            // Step 2 — category has a fixed container_code, Упаковка ignored.
            $code = $fixedCode;
        } else {
            // Step 3 — detect from Упаковка column only.
            $package = (string) $ctx->row->get(config('catalog_import.columns.package'));
            $code = $this->detectCode($package);

            if ($code === null) {
                $ctx->addWarning(self::class, 'unknown container type', $package);

                return $next($ctx);
            }
        }

        $ctx->container = $ctx->lookups?->findContainerByCode($code)
            ?? Container::where('code', $code)->first();

        if ($ctx->container === null) {
            $ctx->addWarning(self::class, "container code '{$code}' not seeded", $code);
        }

        return $next($ctx);
    }

    private function detectCode(string $source): ?string
    {
        $map = config('catalog_import.container_map', []);
        $haystack = $this->normalize($source);

        foreach ($map as $needle => $code) {
            if (str_contains($haystack, $this->normalize($needle))) {
                return $code;
            }
        }

        return null;
    }

    /**
     * 1С пишет маркеры тары непоследовательно («ст. бут.» и «ст бут.»,
     * иногда в верхнем регистре), поэтому перед сравнением точки схлопываются
     * в пробелы, пробелы — в один, регистр приводится к нижнему.
     */
    private function normalize(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $value = str_replace('.', ' ', $value);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
