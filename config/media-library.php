<?php

use Services\RemoteImages\SpatieImageDownloader;
use Spatie\ImageOptimizer\Optimizers\Avifenc;
use Spatie\ImageOptimizer\Optimizers\Cwebp;
use Spatie\ImageOptimizer\Optimizers\Gifsicle;
use Spatie\ImageOptimizer\Optimizers\Jpegoptim;
use Spatie\ImageOptimizer\Optimizers\Optipng;
use Spatie\ImageOptimizer\Optimizers\Pngquant;
use Spatie\ImageOptimizer\Optimizers\Svgo;
use Spatie\MediaLibrary\Conversions\ImageGenerators\Avif;
use Spatie\MediaLibrary\Conversions\ImageGenerators\Image;
use Spatie\MediaLibrary\Conversions\ImageGenerators\Pdf;
use Spatie\MediaLibrary\Conversions\ImageGenerators\Svg;
use Spatie\MediaLibrary\Conversions\ImageGenerators\Video;
use Spatie\MediaLibrary\Conversions\ImageGenerators\Webp;
use Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob;
use Spatie\MediaLibrary\MediaCollections\FileAdder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\MediaCollections\Models\Observers\MediaObserver;
use Spatie\MediaLibrary\ResponsiveImages\Jobs\GenerateResponsiveImagesJob;
use Spatie\MediaLibrary\ResponsiveImages\TinyPlaceholderGenerator\Blurred;
use Spatie\MediaLibrary\ResponsiveImages\WidthCalculator\FileSizeOptimizedWidthCalculator;
use Spatie\MediaLibrary\Support\FileNamer\DefaultFileNamer;
use Spatie\MediaLibrary\Support\FileRemover\DefaultFileRemover;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;
use Spatie\MediaLibrary\Support\UrlGenerator\DefaultUrlGenerator;

return [

    /*
     * Диск для исходных медиафайлов из config/filesystems.php.
     */
    'disk_name' => env('MEDIA_DISK', 'public'),

    /*
     * Диск для преобразованных изображений, если он не указан в коллекции или storingConversionsOnDisk(). При
     * null используется диск исходного файла.
     */
    'conversions_disk_name' => env('MEDIA_CONVERSIONS_DISK', null),

    /*
     * Максимальный размер файла в байтах. Превышение вызывает исключение.
     */
    'max_file_size' => 1024 * 1024 * 10, // 10MB

    /*
     * Запрещённые расширения проверяются во всех частях имени без учёта регистра, включая php в имени
     * shell.php.jpg. По умолчанию используется список FileAdder.
     */
    'disallowed_extensions' => FileAdder::$defaultDisallowedExtensions,

    /*
     * Список разрешённых конечных расширений без учёта регистра; null отключает ограничение. Запрещённые
     * промежуточные расширения по-прежнему отклоняются.
     */
    'allowed_extensions' => null,

    /*
     * Подключение очереди для преобразования изображений; пустое значение использует подключение по умолчанию.
     */
    'queue_connection_name' => env('QUEUE_CONNECTION', 'sync'),

    /*
     * Очередь преобразования изображений; пустое значение использует очередь по умолчанию.
     */
    'queue_name' => env('MEDIA_QUEUE', ''),

    /*
     * Выполнение преобразований изображений через очередь.
     */
    'queue_conversions_by_default' => env('QUEUE_CONVERSIONS_BY_DEFAULT', true),

    /*
     * Запуск преобразований после фиксации транзакции базы данных.
     */
    'queue_conversions_after_database_commit' => env('QUEUE_CONVERSIONS_AFTER_DB_COMMIT', true),

    /*
     * Класс модели медиафайла.
     */
    'media_model' => Media::class,

    /*
     * Класс наблюдателя модели медиафайла.
     */
    'media_observer' => MediaObserver::class,

    /*
     * Стандартная сериализация моделей Laravel. Для компонентов Media Library Pro эту настройку следует
     * отключить: https://medialibrary.pro.
     */
    'use_default_collection_serialization' => false,

    /*
     * Модель временных загрузок Media Library Pro: https://medialibrary.pro.
     */

    /*
     * Обработка временных загрузок Media Library Pro только в рамках сессии загрузки. Отключение позволяет
     * работать без состояния сессии.
     */
    'enable_temporary_uploads_session_affinity' => true,

    /*
     * Создание миниатюр для загруженных файлов Media Library Pro.
     */
    'generate_thumbnails_for_temporary_uploads' => true,

    /*
     * Класс формирования имён создаваемых файлов.
     */
    'file_namer' => DefaultFileNamer::class,

    /*
     * Класс определения пути медиафайла.
     */
    'path_generator' => DefaultPathGenerator::class,

    /*
     * Класс удаления медиафайлов.
     */
    'file_remover_class' => DefaultFileRemover::class,

    /*
     * Генераторы путей для отдельных классов моделей.
     */
    'custom_path_generators' => [
    ],

    /*
     * Класс генерации URL медиафайлов.
     */
    'url_generator' => DefaultUrlGenerator::class,

    /*
     * Перемещение медиафайла при обновлении пути. Требует собственного PathGenerator, например на основе UUID.
     */
    'moves_media_on_update' => false,

    /*
     * Версионирование URL добавляет параметр ?v=xx.
     */
    'version_urls' => false,

    /*
     * Оптимизаторы преобразованных изображений удаляют метаданные и сжимают файлы.
     */
    'image_optimizers' => [
        Jpegoptim::class => [
            '-m85', // Максимальное качество — 85%.
            '--force', // Прогрессивное кодирование даже при увеличении размера файла.
            '--strip-all', // Удаление текстовых метаданных, включая комментарии и EXIF.
            '--all-progressive', // Прогрессивное кодирование изображения.
        ],
        Pngquant::class => [
            '--force', // Обязательный параметр пакета.
        ],
        Optipng::class => [
            '-i0', // Отключение чересстрочной развёртки.
            '-o2', // Уровень оптимизации 2 с несколькими попытками сжатия IDAT.
            '-quiet', // Обязательный параметр пакета.
        ],
        Svgo::class => [
            '--disable=cleanupIDs', // Опция отключена из-за проблем совместимости.
        ],
        Gifsicle::class => [
            '-b', // Обязательный параметр пакета.
            '-O3', // Самый медленный режим с наилучшим результатом сжатия.
        ],
        Cwebp::class => [
            '-m 6', // Самый медленный метод для максимального сжатия.
            '-pass 10', // Максимальное число проходов анализа.
            '-mt', // Многопоточная обработка для ускорения.
            '-q 90', // Коэффициент качества с минимально заметными изменениями.
        ],
        Avifenc::class => [
            '-a cq-level=23', // Постоянный уровень качества 0–63: меньшее значение повышает качество и размер файла.
            '-j all', // Число рабочих потоков; all использует все доступные ядра.
            '--min 0', // Минимальный квантователь цвета, 0–63.
            '--max 63', // Максимальный квантователь цвета, 0–63.
            '--minalpha 0', // Минимальный квантователь прозрачности, 0–63.
            '--maxalpha 63', // Максимальный квантователь прозрачности, 0–63.
            '-a end-usage=q', // Режим постоянного качества.
            '-a tune=ssim', // Настройка кодировщика по метрике искажений SSIM.
        ],
    ],

    /*
     * Генераторы изображений для медиафайлов.
     */
    'image_generators' => [
        Image::class,
        Webp::class,
        Avif::class,
        Pdf::class,
        Svg::class,
        Video::class,
    ],

    /*
     * Каталог временных файлов преобразований; при null используется storage_path('media-library/temp').
     */
    'temporary_directory_path' => null,

    /*
     * Движок преобразования изображений: gd, imagick или vips.
     */
    'image_driver' => env('IMAGE_DRIVER', 'gd'),

    /*
     * Пути FFMPEG и FFProbe для миниатюр видео; требуется пакет php-ffmpeg/php-ffmpeg.
     */
    'ffmpeg_path' => env('FFMPEG_PATH', '/usr/bin/ffmpeg'),
    'ffprobe_path' => env('FFPROBE_PATH', '/usr/bin/ffprobe'),

    /*
     * Таймаут создания миниатюр видео через FFMPEG в секундах.
     */
    'ffmpeg_timeout' => env('FFMPEG_TIMEOUT', 900),

    /*
     * Число потоков FFMPEG; 0 позволяет выбрать его автоматически.
     */
    'ffmpeg_threads' => env('FFMPEG_THREADS', 0),

    /*
     * Классы заданий пакета. Собственные задания должны наследовать соответствующие классы пакета.
     */
    'jobs' => [
        'perform_conversions' => PerformConversionsJob::class,
        'generate_responsive_images' => GenerateResponsiveImagesJob::class,
    ],

    /*
     * Класс загрузчика для addMediaFromUrl().
     */
    'media_downloader' => SpatieImageDownloader::class,

    /*
     * Проверка сертификата SSL при загрузке удалённых файлов. Отключать только в локальном окружении.
     */
    'media_downloader_ssl' => env('MEDIA_DOWNLOADER_SSL', true),

    /*
     * Срок действия временных URL в минутах.
     */
    'temporary_url_default_lifetime' => env('MEDIA_TEMPORARY_URL_DEFAULT_LIFETIME', 5),

    'remote' => [
        /*
         * Дополнительные заголовки при загрузке на удалённый диск; набор поддерживаемых заголовков зависит от
         * драйвера.
         */
        'extra_headers' => [
            'CacheControl' => 'max-age=604800',
        ],
    ],

    'responsive_images' => [
        /*
         * Класс расчёта ширины адаптивных изображений. Стандартная стратегия создаёт варианты с последовательным
         * уменьшением размера файла на 30%. См.
         * https://docs.spatie.be/laravel-medialibrary/v9/advanced-usage/generating-responsive-images.
         */
        'width_calculator' => FileSizeOptimizedWidthCalculator::class,

        /*
         * Добавление небольшой заглушки и JavaScript для определения размеров адаптивного изображения до
         * загрузки.
         */
        'use_tiny_placeholders' => true,

        /*
         * Класс генерации заглушки для постепенной загрузки; стандартная заглушка — небольшое размытое
         * изображение JPG.
         */
        'tiny_placeholder_generator' => Blurred::class,
    ],

    /*
     * Маршрут перемещения загруженных файлов S3 для компонентов Vue и React из Media Library Pro.
     */
    'enable_vapor_uploads' => env('ENABLE_MEDIA_LIBRARY_VAPOR_UPLOADS', false),

    /*
     * Значение атрибута loading для img: lazy, eager, auto или null. См.
     * https://css-tricks.com/native-lazy-loading/.
     */
    'default_loading_attribute_value' => null,

    /*
     * Общий префикс пути хранения медиафайлов.
     */
    'prefix' => env('MEDIA_PREFIX', ''),

    /*
     * Разрешает отложенную загрузку медиа, даже если она глобально отключена для моделей.
     */
    'force_lazy_loading' => env('FORCE_MEDIA_LIBRARY_LAZY_LOADING', true),
];
