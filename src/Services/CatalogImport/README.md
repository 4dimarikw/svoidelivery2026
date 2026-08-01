# Catalog Import Service

Сервис импортирует CSV-выгрузку из 1С в каталог товаров.

**Входные данные:** CSV-файл из 1С (Windows-1251, разделитель `;`, 23 колонки). По умолчанию скачивается с FTP (`DB_1C_FTP_BASE_FILE`); можно передать локальный путь.
**Результат:** записи в таблицах `manufacturers`, `beer_styles`, `categories` (авто-создание неизвестных slug), `products`, `beer_product_details`, `product_barcodes`, `volumes`, `containers`
**Идемпотентность:** повторные запуски обновляют существующие записи (ключ — `products.external_code`, он же `КодТовара` 1С), дубликатов не создают

Схема этого проекта **плоская**: нет отдельной таблицы вариаций — цена, остаток,
объём и тара живут прямо на `products`. Пивные атрибуты (ABV/IBU/Plato/EBC,
стиль, Untappd-привязка) вынесены в 1:1-таблицу `beer_product_details`.

---

## Использование

### Подготовка (первый запуск)

```bash
php artisan db:seed --class=CategorySeeder
php artisan db:seed --class=ContainerSeeder
```

`CategorySeeder` строит категории из `config('catalog_import.categories')` —
того же реестра, которым пользуется парсер, так что slug в БД никогда не
расходится с тем, что резолвит `CategorySlugResolver`.

### Команды

```bash
# По умолчанию: скачать base_ftp_file с FTP и импортировать
php artisan catalog:import

# Локальный файл (FTP пропускается)
php artisan catalog:import storage/imports/catalog-2026.csv

# Пропустить загрузку — использовать последний файл из storage/app/private/catalog/
php artisan catalog:import --no-download

# Только указанные категории (slug через запятую)
php artisan catalog:import --categories=beer,mead

# Dry-run: полный прогон в транзакции с откатом — БД не меняется
php artisan catalog:import --dry-run
```

Весь вывод команды на **русском языке**.

### Пример отчёта

```
+---------------------------+------------+
| Метрика                   | Количество |
+---------------------------+------------+
| Строк обработано          | 94         |
| Строк пропущено           | 1          |
| Битых строк CSV           | 0          |
| Производителей создано    | 29         |
| Стилей пива создано       | 31         |
| Товаров создано           | 76         |
| Товаров обновлено         | 17         |
| Товаров без изменений     | 1          |
| Штрихкодов создано        | 93         |
| Изображений загружено     | 12         |
| Предупреждений            | 39         |
+---------------------------+------------+

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
    ├── ResolveCategoryStage       Категория/ABV/СтильПива/Наименование → Category (первый стейдж)
    ├── FilterCategoryStage        --categories=slug,... — пропуск строк вне фильтра
    ├── NormalizeRowStage          пропуск строки при: пустой id_объекта; нет КодТовара И Артикул;
    │                              цена < normalize.min_price (и категория не price_exempt);
    │                              Категория в normalize.excluded_categories
    ├── ResolveBrandStage          Производитель → Manufacturer (firstOrCreate по normalized_name)
    ├── ResolveBeerStyleStage      СтильПива → BeerStyle; синхронизация Untappd по UntappdRef
    ├── ResolveContainerStage      Упаковка/категория → Container (только для categories.*.container)
    ├── ResolveVolumeStage         Упаковка → Volume (только для categories.*.volume)
    ├── ResolveProductIdentityStage Марка → name, Наименование/Товар → title
    ├── ResolveAbvStage            ABV (Excel-формат "04.фев" → 4.2)
    ├── ResolveIbuStage            IBU
    ├── ResolvePlatoStage          Plato
    ├── ResolveEbcStage            EBC
    ├── ResolveShelfLifeStage      СрокГодности → shelf_life_days
    ├── ResolvePriceStage          Цена → price, Остаток → stock
    ├── ResolveExternalIdsStage    КодТовара → external_code, id_объекта → source_uuid, Артикул → article
    ├── ResolveFlagsStage          РейтингПродаж → sales_rating (сырая строка)
    ├── ResolveDescriptionStage    Описание → description
    ├── PersistProductStage        updateOrCreate Product по [external_code]
    ├── PersistBeerDetailsStage    updateOrCreate BeerProductDetail (только если есть пивные атрибуты)
    └── PersistBarcodeStage        firstOrCreate ProductBarcode по [barcode]
    │
    ▼ (post-commit, вне транзакции строки/чанка)
    └── PersistProductImageStage   Untappd label → медиа-коллекция 'main' (spatie/laravel-medialibrary)
    │
    ▼
ImportReport (счётчики + warnings)
```

### Ключевые компоненты

| Класс | Назначение |
|---|---|
| `CsvReader` | Открывает файл, конвертирует Windows-1251→UTF-8, отдаёт `RawRow` через generator |
| `CsvParserService` | Оркестратор: итерирует строки, запускает pipeline, формирует `ImportReport` |
| `CategoryRegistry` | Типизированный доступ к `config('catalog_import.categories')` + `category_resolution` |
| `CategorySlugResolver` | Резолвит slug категории из сырых колонок CSV через `CategoryRegistry`, без обращения к БД |
| `LookupCache` | Per-import кэш `Category`/`Container` — устраняет N+1 в Resolve*-стейджах |
| `ImportContext` | Mutable-объект, передаётся между стейджами; содержит `RawRow`, резолвенные модели (`brand`, `category`, `container`, `volume`, `beerStyle`, `product`, `untappdBeer`) и буфер `$attributes` |
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
├── CategoryRegistry.php          типизированный доступ к config('catalog_import.categories')
├── CategorySlugResolver.php      резолв slug категории (без БД)
├── LookupCache.php               per-import кэш Category/Container
├── Contracts/
│   └── ImportStage.php           interface для всех стейджей
├── Dto/
│   ├── RawRow.php                строка CSV (readonly)
│   ├── ImportContext.php         контекст между стейджами (mutable)
│   ├── ImportOptions.php         опции запуска (dryRun, categoryFilter, ...)
│   └── ImportReport.php          итоговый отчёт
└── Stages/
    ├── ResolveCategoryStage.php
    ├── FilterCategoryStage.php
    ├── NormalizeRowStage.php
    ├── ResolveBrandStage.php
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
    ├── PersistBeerDetailsStage.php
    ├── PersistBarcodeStage.php
    └── PersistProductImageStage.php   (post-commit)

config/catalog_import.php                        категории + маппинги + порядок стейджей
app/Console/Commands/CatalogImportCommand.php    Artisan-команда
```

---

## Маппинг CSV → БД

| CSV колонка | Таблица.колонка | Стейдж |
|---|---|---|
| `Производитель` | `manufacturers.name`/`normalized_name` | `ResolveBrandStage` |
| `СтильПива` | `beer_styles.name`/`normalized_name` | `ResolveBeerStyleStage` |
| `Марка` | `products.brand` | `ResolveProductIdentityStage` |
| `Наименование` (резерв `Товар`) | `products.name` + авто-`slug` (из `article`) | `ResolveProductIdentityStage` |
| `Описание` | `products.description` | `ResolveDescriptionStage` |
| `ABV` | `beer_product_details.abv` | `ResolveAbvStage` |
| `IBU` | `beer_product_details.ibu` | `ResolveIbuStage` |
| `Plato` | `beer_product_details.plato` | `ResolvePlatoStage` |
| `EBC` | `beer_product_details.ebc` | `ResolveEbcStage` |
| `UntappdRef` | синхронизация `untappd_beers` → `beer_product_details.untappd_beer_id` | `ResolveBeerStyleStage` |
| `СрокГодности` | `products.shelf_life_days` | `ResolveShelfLifeStage` |
| `РейтингПродаж` | `products.sales_rating` (сырая строка) | `ResolveFlagsStage` |
| `КодТовара` | `products.external_code` (ключ идемпотентности) | `ResolveExternalIdsStage` |
| `id_объекта` | `products.source_uuid` | `ResolveExternalIdsStage` |
| `Артикул` | `products.article` (источник slug) | `ResolveExternalIdsStage` |
| `Цена` | `products.price` | `ResolvePriceStage` |
| `Остаток` | `products.stock_quantity` + `in_stock` | `ResolvePriceStage` / `PersistProductStage` |
| `Упаковка` | `products.packaging_raw`, `package_units` | `ResolveExternalIdsStage`, `PersistProductStage` |
| `Упаковка` | `volumes` (firstOrCreate) → `products.volume_id` | `ResolveVolumeStage` |
| `Упаковка` / категория | `products.container_id` | `ResolveContainerStage` |
| `ШтрихКод` | `product_barcodes.barcode` | `PersistBarcodeStage` |
| `Категория` (сырая) | `products.source_category_path` | `ResolveCategoryStage` |
| `Категория` | верхний сегмент → ветвь алгоритма категоризации (`categories.*`) | `ResolveCategoryStage` |
| `Тип` | не используется | — |

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

**Шаг 4.** Используй в `PersistProductStage` или `PersistBeerDetailsStage`:

```php
$productData = [
    // ...
    'my_column' => $attrs['my_attribute'] ?? null,
];
```

### 3. Добавить новый тип тары

**a)** Добавь запись в `database/seeders/ContainerSeeder.php` (или вручную в БД).

**b)** Добавь маппинг в `config/catalog_import.php`. Порядок важен — первое совпадение выигрывает; более специфичные подстроки должны стоять раньше общих:

```php
'container_map' => [
    'пэт кег' => 'pet_keg',   // ← до 'пэт' (содержит ту же подстроку)
    'пэт' => 'pet',
    'ж/б' => 'can',
    'ст. бут.' => 'glass_bottle',
    'мой_маркер' => 'my_code',  // ← новая строка
],
```

### 4. Изменить определение категории или добавить новую

Категория определяется `CategorySlugResolver` (используется и `NormalizeRowStage` для
price-exemption, и `ResolveCategoryStage` для резолва модели) из реестра
`config('catalog_import.categories')` — единственного источника правды и для
парсера, и для `CategorySeeder`.

Чтобы добавить категорию: добавь запись в `categories` с нужными свойствами
(`container`/`volume`/`price_exempt`/`default_brand`/`name_from_article`/`container_code`)
и `match[]`-правилами (см. докблок над массивом `categories` в конфиге —
там расписан приоритет типов `alcohol`/`contains`/`accessory_title`).
Затем прогони `php artisan db:seed --class=CategorySeeder` — сидер строит
категории прямо из этого массива.

### 5. Отключить или переставить стейдж

В `config/catalog_import.php` массив `stages[]` — порядок выполнения. Убери строку → стейдж не выполняется. Перемести → меняется порядок.

**Важно:** стейджи, разрешающие зависимости (Brand, Category, BeerStyle, Container, Volume), должны стоять **до** `PersistProductStage`. `ResolveCategoryStage` должен идти до `ResolveBeerStyleStage`.

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

---

## Известные ограничения

- **Штрихкоды теряют точность:** Excel сохранил EAN-13 в научной нотации (`4,63E+12`). Импортируется `4630000000000` — последние цифры могут отличаться от реального штрихкода.
- **Штрихкод глобально уникален** (`product_barcodes.barcode`), а не пара `[product, barcode]` — если один физический штрихкод по ошибке присвоен разным товарам в 1С, к новому товару он не переприкрепится (`PersistBarcodeStage` создаёт запись только при первом появлении штрихкода).
- **Некоторые аксессуары без контейнера/объёма:** строки с `Упаковка = "Упаковка 6 шт."` без явного маркера из `container_map` импортируются с warning, `container_id` остаётся NULL. Добавь маркер в `container_map` если нужна точная классификация.
- **`package_units`** (число упаковок из `Упаковка`, напр. "12" из "кор. 12х0,45л") — эвристика на regex, не гарантирует 100% точность для нестандартных форматов упаковки; колонка nullable и не участвует в дальнейшей логике импорта.
