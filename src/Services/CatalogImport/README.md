# Catalog Import Service

Сервис импортирует CSV-выгрузку из 1С в каталог товаров.

**Входные данные:** CSV-файл из 1С (Windows-1251, разделитель `;`, 23 колонки). По умолчанию скачивается с FTP (`DB_1C_FTP_BASE_FILE`); можно передать локальный путь или ключ конфига.  
**Результат:** записи в таблицах `brands`, `beer_styles`, `products`, `product_variations`, `product_barcodes`, `volumes`, `container_types`  
**Идемпотентность:** повторные запуски обновляют существующие записи, дубликатов не создают

---

## Использование

### Подготовка (первый запуск)

```bash
php artisan db:seed --class=CategorySeeder
php artisan db:seed --class=ContainerTypeSeeder
```

### Команды

```bash
# По умолчанию: скачать base_ftp_file с FTP и импортировать
php artisan catalog:import

# Локальный файл (FTP пропускается)
php artisan catalog:import storage/imports/catalog-2026.csv

# Другой файл с FTP — ключ конфига catalog_import (base_ftp_file | ref_eq_ftp_file)
php artisan catalog:import "" ref_eq_ftp_file

# Пропустить загрузку — использовать последний файл из storage/app/private/catalog/
php artisan catalog:import --no-download

# Dry-run: полный прогон в транзакции с откатом — БД не меняется
php artisan catalog:import --dry-run
```

Весь вывод команды на **русском языке**.

### Пример отчёта

```
+----------------------+------------+
| Метрика              | Количество |
+----------------------+------------+
| Строк обработано     | 94         |
| Строк пропущено      | 1          |
| Брендов создано      | 29         |
| Стилей пива создано  | 31         |
| Товаров создано      | 76         |
| Товаров обновлено    | 17         |
| Вариаций создано     | 81         |
| Вариаций обновлено   | 12         |
| Штрихкодов создано   | 93         |
| Предупреждений       | 39         |
+----------------------+------------+

Предупреждения:
  строка 22 [ResolveContainerStage] unknown container type: штучный товар
  строка 60 [ResolveVolumeStage] cannot parse volume: 2 л бцв. ПЭТ бутылка
  ...
```

---

## Архитектура: Pipeline

Каждая строка CSV проходит через цепочку независимых **стейджей** (`Pipeline::send($ctx)->through($stages)`).

```
CsvReader
    │  yields RawRow (assoc array колонок)
    ▼
ImportContext      ← mutable-контекст, передаётся по всей цепочке
    │
    ▼  Pipeline
    ├── NormalizeRowStage          пропуск строки при: пустой id_объекта; нет КодТовара и Артикул;
    │                              цена < normalize.min_price; Категория в normalize.excluded_categories
    ├── ResolveBrandStage          Производитель → Brand (firstOrCreate)
    ├── ResolveCategoryStage       Категория/ABV/СтильПива/Наименование → Category (ранний стейдж)
    ├── ResolveBeerStyleStage      СтильПива → BeerStyle; синхронизация Untappd по UntappdRef
    ├── ResolveContainerStage      Упаковка/Артикул → ContainerType (keg/can/bottle/pet_keg/pet_bottle/piece)
    ├── ResolveVolumeStage         Упаковка → Volume (firstOrCreate по volume_ml)
    ├── ResolveProductIdentityStage Марка → name (product), Наименование/Товар → title (variation)
    ├── ResolveAbvStage            ABV (Excel-формат "04.фев" → 4.2)
    ├── ResolveIbuStage            IBU
    ├── ResolvePlatoStage          Plato
    ├── ResolveEbcStage            EBC
    ├── ResolveShelfLifeStage      СрокГодности → shelf_life_days
    ├── ResolvePriceStage          Цена → price, Остаток → stock
    ├── ResolveExternalIdsStage    КодТовара → sku, id_объекта → external_id
    ├── ResolveFlagsStage          РейтингПродаж: "Акция" → is_featured (is_new вычисляется в PersistVariationStage)
    ├── ResolveDescriptionStage    Описание → description
    ├── PersistProductStage        firstOrCreate Product по [name, brand_id]
    ├── PersistVariationStage      firstOrNew+save ProductVariation по [sku]; is_new по категории+возрасту
    └── PersistBarcodeStage        firstOrCreate ProductBarcode по [variation_id, barcode]
    │
    ▼
ImportReport (счётчики + warnings)
```

### Ключевые компоненты

| Класс | Назначение |
|---|---|
| `CsvReader` | Открывает файл, конвертирует Windows-1251→UTF-8, отдаёт `RawRow` через generator |
| `CsvParserService` | Оркестратор: итерирует строки, запускает pipeline, формирует `ImportReport` |
| `ImportContext` | Mutable-объект, передаётся между стейджами; содержит `RawRow`, резолвенные модели (`brand`, `category`, `product`, …) и буфер `$attributes` |
| `ImportStage` (interface) | `__invoke(ImportContext $ctx, Closure $next): ImportContext` |
| `ImportReport` | Счётчики + warnings по строкам |
| `RawRow` | Ассоциативный массив колонок CSV; метод `get(string $col)` с trim |

---

## Структура файлов

```
src/Services/CatalogImport/
├── README.md
├── CsvParserService.php          оркестратор
├── CsvReader.php                 чтение/перекодировка CSV
├── Contracts/
│   └── ImportStage.php           interface для всех стейджей
├── Dto/
│   ├── RawRow.php                строка CSV (readonly)
│   ├── ImportContext.php         контекст между стейджами (mutable)
│   └── ImportReport.php         итоговый отчёт
└── Stages/
    ├── NormalizeRowStage.php
    ├── ResolveBrandStage.php
    ├── ResolveCategoryStage.php
    ├── ResolveBeerStyleStage.php
    ├── ResolveContainerStage.php
    ├── ResolveVolumeStage.php
    ├── ResolveProductIdentityStage.php
    ├── ResolveAbvStage.php
    ├── ResolveIbuStage.php
    ├── ResolvePlatoStage.php
    ├── ResolveEbcStage.php
    ├── ResolveShelfLifeStage.php
    ├── ResolvePriceStage.php
    ├── ResolveExternalIdsStage.php
    ├── ResolveFlagsStage.php
    ├── ResolveDescriptionStage.php
    ├── PersistProductStage.php
    ├── PersistVariationStage.php
    └── PersistBarcodeStage.php

config/catalog_import.php                        маппинги + порядок стейджей
app/Console/Commands/CatalogImportCommand.php    Artisan-команда
tests/Feature/CatalogImport/CsvParserServiceTest.php
tests/Feature/CatalogImport/CatalogImportCommandTest.php
tests/Unit/CatalogImport/Stages/NormalizeRowStageTest.php
tests/Unit/CatalogImport/Stages/ResolveCategoryStageTest.php
tests/Unit/CatalogImport/Stages/ResolveBeerStyleStageTest.php
tests/Unit/CatalogImport/Stages/ResolveAbvStageTest.php
tests/Unit/CatalogImport/Stages/ResolveVolumeStageTest.php
tests/Unit/CatalogImport/Stages/ResolveContainerStageTest.php
```

---

## Маппинг CSV → БД

| CSV колонка | Таблица.колонка | Стейдж |
|---|---|---|
| `Производитель` | `brands.name` | `ResolveBrandStage` |
| `СтильПива` | `beer_styles.name` | `ResolveBeerStyleStage` |
| `Марка` | `products.name` | `ResolveProductIdentityStage` |
| `Наименование` (резерв `Товар`) | `product_variations.title` + авто-`slug` | `ResolveProductIdentityStage` |
| `Описание` | `products.description` | `ResolveDescriptionStage` |
| `ABV` | `products.abv` | `ResolveAbvStage` |
| `IBU` | `products.ibu` | `ResolveIbuStage` |
| `Plato` | `products.plato` | `ResolvePlatoStage` |
| `EBC` | `products.ebc` | `ResolveEbcStage` |
| `UntappdRef` | синхронизация `untappd_beers` | `ResolveBeerStyleStage` |
| `СрокГодности` | `products.shelf_life_days` | `ResolveShelfLifeStage` |
| `РейтингПродаж` | `product_variations.is_featured` | `ResolveFlagsStage` |
| *(категория + возраст вариации)* | `product_variations.is_new` | `PersistVariationStage` |
| `КодТовара` | `product_variations.sku` | `ResolveExternalIdsStage` |
| `id_объекта` | `product_variations.external_id` | `ResolveExternalIdsStage` |
| `Цена` | `product_variations.price` | `ResolvePriceStage` |
| `Остаток` | `product_variations.stock` | `ResolvePriceStage` |
| `Упаковка` | `product_variations.pack_info` | `ResolveExternalIdsStage` |
| `Упаковка` | `volumes` (firstOrCreate) → `product_variations.volume_id` | `ResolveVolumeStage` |
| `Упаковка` / `Артикул` | `product_variations.container_type_id` | `ResolveContainerStage` |
| `ШтрихКод` | `product_barcodes.barcode` | `PersistBarcodeStage` |
| `Артикул` | не используется (отображаемое имя) | — |
| `Тип` | не используется | — |
| `Категория` | верхний сегмент → ветвь алгоритма категоризации | `ResolveCategoryStage` |

---

## Как дорабатывать

### 1. Изменить парсинг существующего атрибута

Найди соответствующий стейдж по таблице выше и правь только его.

**Пример — ABV:** значения вида `04.фев` — это Excel-мусор (Excel превратил `4,2` в дату "4 февраля"). Логика: `Stages/ResolveAbvStage.php`, метод `parseExcelDate()`. Правь там — ничего другого трогать не нужно.

### 2. Добавить парсинг нового атрибута

**Шаг 1.** Создай стейдж в `Stages/`:

```php
<?php

namespace Services\CatalogImport\Stages;

use Closure;
use Services\CatalogImport\Contracts\ImportStage;
use Services\CatalogImport\Dto\ImportContext;

final class ResolveMyAttributeStage implements ImportStage
{
    public function __invoke(ImportContext $ctx, Closure $next): ImportContext
    {
        $raw = $ctx->row->get(config('catalog_import.columns.my_column'));

        $ctx->attributes['my_attribute'] = /* логика парсинга */;

        return $next($ctx);
    }
}
```

**Шаг 2.** Добавь новую колонку в `config/catalog_import.php` секцию `columns`:

```php
'columns' => [
    // ...
    'my_column' => 'НазваниеКолонки1С',  // ← семантическое имя → CSV-заголовок
],
```

**Шаг 3.** Добавь стейдж в `config/catalog_import.php` в массив `stages[]` перед `PersistProductStage`:

```php
\Services\CatalogImport\Stages\ResolveMyAttributeStage::class,
```

**Шаг 4.** Используй в `PersistProductStage` или `PersistVariationStage`:

```php
$productData = [
    // ...
    'my_column' => $attrs['my_attribute'] ?? null,
];
```

### 3. Добавить новый тип тары

**a)** Добавь запись в `ContainerTypeSeeder` (или вручную в БД).

**b)** Добавь маппинг в `config/catalog_import.php`. Порядок важен — первое совпадение выигрывает; более специфичные подстроки должны стоять раньше общих:

```php
'container_map' => [
    'пэт кег'    => 'pet_keg',      // ← до 'кег' (содержит ту же подстроку)
    'ПЭТ бутылка' => 'pet_bottle',  // ← до 'бут.'
    'кег'        => 'keg',
    'ж/б'        => 'can',
    'ст.'        => 'bottle',
    'бут.'       => 'bottle',
    'штучный'    => 'piece',
    'шт.'        => 'piece',
    'мой_маркер' => 'my_code',  // ← новая строка
],
```

### 4. Изменить определение категории

Категория определяется `ResolveCategoryStage` из сырых колонок CSV (ранний стейдж, до `ResolveBeerStyleStage`).

Алгоритм (порядок ветвей):
1. `Категория` (первый сегмент) == `alcohol_category_marker` → адвент/безалко/стиль/beer
2. `Категория` содержит `probes_marker` → `probes`
3. `Категория` == `accessory_category_marker` → keyword-map `accessory_title_map` по `Наименование`
4. иначе → `fallback_category`

Все маркеры и slug-и настраиваются в `config/catalog_import.php`. Для добавления новой категории: создай запись в `CategorySeeder`, добавь нужный маркер/ключ в конфиг.

### 5. Отключить или переставить стейдж

В `config/catalog_import.php` массив `stages[]` — порядок выполнения. Убери строку → стейдж не выполняется. Перемести → меняется порядок.

**Важно:** стейджи, разрешающие зависимости (Brand, Category, BeerStyle), должны стоять **до** `PersistProductStage`. `ResolveCategoryStage` должен идти до `ResolveBeerStyleStage`.

### 6. Добавить обработку новой CSV-колонки

Если 1С добавила новую колонку, она автоматически попадает в `RawRow::$data`. Чтобы читать её из стейджа:

1. Зарегистрируй семантическое имя в `config/catalog_import.php` → `columns`:
   ```php
   'my_column' => 'НазваниеНовойКолонки',
   ```
2. Читай через конфиг (не хардкоди русский заголовок):
   ```php
   $value = $ctx->row->get(config('catalog_import.columns.my_column'));
   ```

Если колонка ещё не существует в строке — `get()` вернёт пустую строку (без исключений).

### 7. Написать тест на новый стейдж

```php
class ResolveMyAttributeStageTest extends TestCase
{
    public function test_parses_correctly(): void
    {
        $ctx = new ImportContext(new RawRow([
            'НазваниеКолонки1С' => 'входное значение',
            'id_объекта' => 'test-uuid',
        ], 1));

        ($stage = new ResolveMyAttributeStage)($ctx, fn($c) => $c);

        $this->assertSame('ожидаемое', $ctx->attributes['my_attribute']);
    }
}
```

---

## Тесты

```bash
# Все тесты сервиса
php artisan test --compact tests/Feature/CatalogImport/ tests/Unit/CatalogImport/

# По отдельности
php artisan test --compact tests/Unit/CatalogImport/Stages/NormalizeRowStageTest.php
php artisan test --compact tests/Unit/CatalogImport/Stages/ResolveCategoryStageTest.php
php artisan test --compact tests/Unit/CatalogImport/Stages/ResolveBeerStyleStageTest.php
php artisan test --compact tests/Unit/CatalogImport/Stages/ResolveAbvStageTest.php
php artisan test --compact tests/Unit/CatalogImport/Stages/ResolveVolumeStageTest.php
php artisan test --compact tests/Unit/CatalogImport/Stages/ResolveContainerStageTest.php
php artisan test --compact tests/Feature/CatalogImport/CsvParserServiceTest.php
php artisan test --compact tests/Feature/CatalogImport/CatalogImportCommandTest.php
```

---

## Конфигурация

`config/catalog_import.php` — центральная точка настройки:

| Ключ | Назначение |
|---|---|
| `download_dir` | Путь внутри `storage/` для скачанных FTP-файлов (`app/private/catalog/`) |
| `base_ftp_file` | Имя основного файла на FTP (env `DB_1C_FTP_BASE_FILE`) |
| `ref_eq_ftp_file` | Имя справочного файла на FTP (env `DB_1C_FTP_FTP_REF_EQ_FILE`) |
| `stages` | Список классов стейджей в порядке выполнения |
| `container_map` | Подстрока из `Упаковка`/`Артикул` → код тары; первое совпадение выигрывает |
| `alcohol_category_marker` | Маркер алкогольных (`Алкогольная продукция`) |
| `advent_marker` / `advent_category` | Маркер адвент-коллекций → slug |
| `non_alcoholic_category` | Slug для строк без ABV (`non-alcoholic`) |
| `alcohol_style_categories` | Слаги для style-regex (`mead`, `cider`, `sauce`) |
| `default_beer_category` | Slug пива по умолчанию (`beer`) |
| `probes_marker` | Маркер пробников в `Категория` (`пробники`) |
| `probes_category` | Slug для пробников (`probes`) |
| `accessory_category_marker` | Маркер сопутствующих (`Сопутствующие товары`) |
| `accessory_title_map` | keyword(lowercase) → slug для сопутствующих |
| `fallback_category` | Slug если ни одна ветвь не совпала (`not-defined`) |
| `default_container` | Код тары по умолчанию (`can`) |
| `normalize.min_price` | Минимальная цена; строки ниже порога пропускаются (2) |
| `normalize.excluded_categories` | Значения `Категория`, при которых строка пропускается (`Архив`, `Завод Сырье`) |
| `flags.featured_marker` | Значение `РейтингПродаж`, устанавливающее `is_featured = true` (`Акция`) |
| `new_flag.categories` | Slug категорий, где флаг `is_new` возможен (`beer`, `mead`, `cider`, `non-alcoholic`) |
| `new_flag.days` | Вариация считается новинкой N дней с момента создания (7) |
| `columns` | Map: семантическое имя (англ.) → заголовок CSV-колонки из 1С |
| `encoding` | Кодировка CSV (`Windows-1251`) |
| `delimiter` | Разделитель CSV (`;`) |

---

## Известные ограничения

- **Штрихкоды теряют точность:** Excel сохранил EAN-13 в научной нотации (`4,63E+12`). Импортируется `4630000000000` — последние цифры могут отличаться от реального штрихкода.
- **Некоторые аксессуары без контейнера/объёма:** строки с `Упаковка = "Упаковка 6 шт."` без явного суффикса из `container_map` импортируются с warnings и падбэком на `default_container`. Добавь маркер в `container_map` если нужна точная классификация.
