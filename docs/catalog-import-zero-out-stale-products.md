# Обнуление остатков товаров, пропавших из выгрузки 1С

## 1. Назначение

1С не гарантирует, что снятый с продажи товар вообще попадёт в CSV-выгрузку — он просто перестаёт присутствовать в файле. `catalog:import` (`Services\CatalogImport\CsvParserService`) обрабатывает CSV построчно (`PersistProductStage`) и ничего не знает про строки, которых в файле нет — без отдельного механизма такой товар навсегда остаётся с последним известным остатком и продолжает продаваться на сайте, попадать в корзину и проходить проверку наличия при оформлении заказа.

Механизм закрывает эту дыру: после каждого успешного импорта отдельная задача сверяет, какие товары каталога отсутствовали в только что обработанном CSV, и обнуляет им `stock_quantity`/`in_stock`.

## 2. Поток данных

```
CsvReader → RawRow                         (на каждую строку CSV)
    │
    ├──► SeenCodeCollector::add(row)        пишет КодТовара в буфер
    │    (ДО пайплайна стейджей — присутствие в файле, не факт импорта)
    │
    └──► Pipeline::through(stages)          обычный пайплайн импорта
                │
                ▼
         PersistProductStage                create/update Product

... после обработки всех строк ...

SeenCodeCollector::flush()
    │  insertOrIgnore пачками в catalog_import_seen_codes
    ▼
CatalogImportCommand (после успешного импорта, не dry-run, без --categories)
    │  if CatalogImportSettings::$zero_out_missing
    ▼
ZeroOutStaleProductsJob::dispatch()          (очередь database, тонкая обёртка)
    │
    ▼
ZeroOutStaleProductsAction
    │  Product::query()->whereNotIn('external_code', <catalog_import_seen_codes>)
    │                   ->where(in_stock OR stock_quantity > 0)
    │
    ├─ порог превышен?  → abort, ничего не меняем, seen_codes НЕ чистим
    │
    └─ иначе → chunkById(500): $product->update(['stock_quantity'=>0,'in_stock'=>false])
               (через Eloquent, не query-builder — см. §6)
               → truncate catalog_import_seen_codes
                │
                ▼
event(CatalogStaleProductsZeroed($result))
    │
    ▼
App\Listeners\PersistEventLog   (автодискавери по LoggableEvent)
    │
    ▼
event_logs   (event_type = catalog_import.stale_products_zeroed)
```

## 3. Критерий «товар есть в CSV»

Критерий — **наличие строки с этим `КодТовара` в файле**, а не факт успешного импорта этой строки. `SeenCodeCollector::add()` вызывается сразу после чтения строки, до того как она попадёт в `Pipeline::through($stages)` — поэтому строка, отбракованная `NormalizeRowStage` (цена ниже `normalize.min_price`, исключённая категория, исключённая упаковка) или любым другим стейджем, всё равно засчитывается как «виденная».

Такой выбор осознан: если бы критерием был факт успешного создания/обновления `Product`, то ошибка в реестре категорий (`categories`/`category_match_rules`) или в правилах `NormalizeRowStage` начала бы массово обнулять товары, которые 1С по-прежнему честно выгружает — false positive на уровне всего каталога вместо одной некорректной настройки.

## 4. Предохранитель

Битый или обрезанный файл от 1С (например, 200 строк вместо 20 000) без защиты обнулил бы почти весь каталог. Перед обнулением `ZeroOutStaleProductsAction` считает:

```
staleCount   = число товаров, отсутствующих в catalog_import_seen_codes
inStockTotal = Product::query()->where('in_stock', true)->count()

если staleCount / inStockTotal * 100 > zero_out_max_percent:
    abort — ни один товар не меняется
```

При срабатывании:
- ни один `Product` не обновляется;
- `catalog_import_seen_codes` **не** очищается — таблица остаётся доступной для ручного разбора;
- событие `CatalogStaleProductsZeroed` уходит с `level = 'warning'` и `context.reason = 'threshold_exceeded'` в `event_logs`.

Второй, более ранний выход — `seenTotal === 0` (таблица кодов пуста): `reason = 'no_seen_codes'`, тоже `level = 'warning'`.

## 5. Когда обнуление не выполняется

| Условие | Причина | Что происходит |
|---|---|---|
| `--dry-run` | Импорт ничего реально не сохраняет | `SeenCodeCollector` не вызывается вообще (`reset`/`add`/`flush` пропускаются), job не ставится в очередь |
| `--categories=slug,...` | Частичный импорт не должен обнулять остальной каталог | То же — сбор кодов и job полностью отключены |
| `CatalogImportSettings::$zero_out_missing = false` | Ручное отключение (например, заведомо неполная тестовая выгрузка) | Коды всё равно собираются (для консистентности отчёта), но `CatalogImportCommand` не ставит job в очередь |

Оба условия (`dryRun`, `categoryFilter`) объединены в `ImportReport::$seenCodesTracked` — команда читает только этот флаг, не дублирует проверку.

## 6. Идемпотентность

Кандидаты на обнуление выбираются условием:

```php
->where(fn ($q) => $q->where('in_stock', true)->orWhere('stock_quantity', '>', 0))
```

Уже обнулённый товар (`in_stock = false`, `stock_quantity = 0`) этому условию не соответствует и не попадает в выборку повторно — второй прогон над тем же «пропавшим» товаром не увеличивает счётчик `zeroed` и не поднимает лишний раз SEO-хук.

Обнуление всегда идёт через `Eloquent::update()` на каждой модели внутри `chunkById(500, …)`, а не через массовый `DB::table('products')->update(...)`: `in_stock` входит в `Product::SEO_WATCHED_ATTRIBUTES` (`src/Domain/Catalog/Models/Product.php`), и только `static::saved()`-хук модели вызывает `SyncProductSeoAction` — массовый query-builder update события модели не поднимает, и JSON-LD (`Offer.availability`) остался бы `InStock` у фактически отсутствующего товара.

## 7. Настройки

`Infrastructure\Settings\CatalogImportSettings`:

| Свойство | По умолчанию | Смысл |
|---|---|---|
| `zero_out_missing` | `true` | Включает/выключает постановку `ZeroOutStaleProductsJob` в очередь |
| `zero_out_max_percent` | `20` | Порог предохранителя, % от `inStockTotal` |

Редактируются в MoonShine: **Каталог → Настройки импорта** (`app/MoonShine/Pages/CatalogImportSettingsPage.php`), на той же странице, что маркеры категорий (`alcohol_marker` и т.д.).

## 8. Файлы механизма

| Файл | Назначение |
|---|---|
| `database/migrations/2026_08_13_120000_create_catalog_import_seen_codes_table.php` | Таблица `catalog_import_seen_codes` (`external_code` — primary key) |
| `database/settings/2026_08_13_120100_add_zero_out_to_catalog_import_settings.php` | Добавляет `zero_out_missing`/`zero_out_max_percent` в группу настроек `catalog_import` |
| `src/Services/CatalogImport/SeenCodeCollector.php` | Буферизованная запись кодов строк CSV (`reset`/`add`/`flush`) |
| `src/Services/CatalogImport/CsvParserService.php` | Подключение коллектора: `reset()` в начале, `add()` на каждой строке (вне транзакции строки/чанка), `flush()` в конце; вычисляет `trackSeenCodes` |
| `src/Services/CatalogImport/Dto/ImportReport.php` | Новое поле `$seenCodesTracked` |
| `src/Domain/Catalog/Actions/ZeroOutStaleProductsAction.php` | Основная логика: выборка кандидатов, предохранитель, обнуление, truncate |
| `src/Domain/Catalog/Actions/ZeroOutStaleProductsResult.php` | DTO результата (`zeroed`, `staleFound`, `inStockTotal`, `seenTotal`, `maxPercent`, `aborted`, `reason`) |
| `src/Infrastructure/Jobs/ZeroOutStaleProductsJob.php` | Тонкая обёртка-job (`tries = 1`, `timeout = 600`, `ShouldBeUnique`), payload пустой |
| `app/Events/CatalogStaleProductsZeroed.php` | `LoggableEvent` — уходит в `event_logs` через существующий `App\Listeners\PersistEventLog` |
| `app/Console/Commands/CatalogImportCommand.php` | Ставит job после успешного импорта; строка «Обнуление пропавших товаров» в шапке файла отчёта |
| `app/MoonShine/Pages/CatalogImportSettingsPage.php` | Поля `zero_out_missing`/`zero_out_max_percent` на странице настроек |
| `lang/ru/moonshine.php`, `lang/en/moonshine.php` | Копирайт новых полей настроек |
| `src/Services/CatalogImport/README.md` | Раздел «Обнуление пропавших товаров» — краткая версия этого документа внутри README пайплайна импорта |

## 9. Тестовое покрытие

`tests/Feature/CatalogImport/ZeroOutStaleProductsTest.php` (сквозные тесты через `catalog:import`, `QUEUE_CONNECTION=sync` в `phpunit.xml` исполняет job синхронно):

| Тест | Проверяет |
|---|---|
| `test_product_missing_from_new_csv_is_zeroed_while_present_products_are_untouched` | Товар, пропавший из второго CSV, обнуляется; присутствующие — нет |
| `test_row_present_but_rejected_by_normalize_stage_is_not_zeroed` | Строка, отбракованная `NormalizeRowStage`, всё равно засчитывается «виденной» — товар не обнуляется |
| `test_dry_run_does_not_track_seen_codes_or_zero_anything` | `--dry-run` не пишет в `catalog_import_seen_codes` и не запускает job |
| `test_categories_filter_disables_zero_out_entirely` | `--categories=` полностью отключает сбор кодов и job |
| `test_threshold_exceeded_aborts_and_logs_a_warning_event` | Превышение порога отменяет обнуление и пишет `warning`-событие с `reason = threshold_exceeded` |
| `test_action_is_idempotent_and_does_not_recount_already_zeroed_products` | Повторный прогон над уже обнулённым товаром не увеличивает `zeroed` |
| `test_zeroing_updates_json_ld_offer_availability_to_out_of_stock` | Обнуление через `Eloquent::update()` действительно поднимает SEO-хук (`Offer.availability` → `OutOfStock`) |

`tests/Unit/CatalogImport/SeenCodeCollectorTest.php`:

| Тест | Проверяет |
|---|---|
| `test_add_then_flush_persists_codes` | Базовая запись через буфер |
| `test_empty_code_is_not_stored` | Пустой `КодТовара` не попадает в таблицу |
| `test_duplicate_codes_within_the_same_flush_do_not_error` | `insertOrIgnore` корректно гасит дубли внутри одного `flush()` |
| `test_reset_truncates_previous_run_data` | `reset()` очищает данные предыдущего прогона |
| `test_flush_with_empty_buffer_does_not_throw` | `flush()` на пустом буфере — безопасный no-op |

## 10. Известные ограничения

- **Планировщика нет.** `catalog:import` запускается вручную (`bootstrap/app.php` без `withSchedule()`) — обнуление наследует тот же ритм и не запускается само по себе между импортами.
- **Пик пересинхронизации SEO.** Первый прогон механизма на уже накопившемся хвосте пропавших товаров может вызвать `SyncProductSeoAction` для нескольких тысяч записей разом — поэтому обнуление вынесено в очередь, а не выполняется прямо в консольной команде.
- **`tries = 1` у `ZeroOutStaleProductsJob`.** Сознательно без автоповтора: повтор после частичного обнуления пересчитал бы предохранитель по уже изменённым данным. Следующий `catalog:import` пересоберёт `catalog_import_seen_codes` и всё равно доделает работу.
- **Отбракованные строки не обнуляются.** Прямое следствие критерия «есть строка в файле» (§3) — товар, который 1С продолжает выгружать, но с ценой ниже минимума или в исключённой таре, сохраняет старый остаток. Это осознанный компромисс, не регрессия.
