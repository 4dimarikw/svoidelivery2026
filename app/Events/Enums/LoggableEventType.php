<?php

declare(strict_types=1);

namespace App\Events\Enums;

/**
 * Перечень значений LoggableEvent::eventType(), встречающихся в проекте —
 * не источник истины для App\Listeners\PersistEventLog (он сравнивает по
 * сырой строке), а реестр для UI (App\MoonShine\Pages\EventLoggingSettingsPage
 * строит переключатели циклом по cases()). Новый LoggableEvent без своего
 * case здесь просто не получит тумблера в админке и продолжит логироваться
 * как раньше — безопасный дефолт, не ломает существующие события.
 *
 * По одному case на УНИКАЛЬНУЮ строку eventType(): App\Events\FeedbackSentSuccess
 * и App\Events\FeedbackSentFailed делят одну строку ('ProfileController.feedbackSent'),
 * значит и один переключатель. Две "чужеродные" по формату строки
 * (GetPostFromVk.sync_failed, ProfileController.feedbackSent) — уже
 * существующие исторические имена, не переименовываются здесь.
 */
enum LoggableEventType: string
{
    case UntappdBeerSynced = 'untappd_beer.synced';
    case UntappdBeerSyncFailed = 'untappd_beer.sync_failed';
    case UntappdBeerSyncBatchCompleted = 'untappd_beer.sync_batch_completed';
    case CatalogImportCompleted = 'catalog_import.completed';
    case CatalogImportFailed = 'catalog_import.failed';
    case CatalogImportStaleProductsZeroed = 'catalog_import.stale_products_zeroed';
    case CatalogImportVolumeDiscrepanciesDetected = 'catalog_import.volume_discrepancies_detected';
    case CatalogImportFtpDownloadFailed = 'catalog_import.ftp_download_failed';
    case CatalogImportRowsSkipped = 'catalog_import.rows_skipped';
    case CatalogImportPriceCoerced = 'catalog_import.price_coerced';
    case CatalogProductMediaRefreshFailed = 'catalog.product_media_refresh_failed';
    case OrderFtpUploadFailed = 'order.ftp_upload_failed';
    case OrderTelegramNotificationFailed = 'order.telegram_notification_failed';
    case OrderNotificationEmailFailed = 'order.notification_email_failed';
    case OrderCreationFailed = 'order.creation_failed';
    case QueueJobFailed = 'queue.job_failed';
    case VkPostBroadcast = 'vk_post.broadcast';
    case VkSyncFailed = 'GetPostFromVk.sync_failed';
    case FeedbackSent = 'ProfileController.feedbackSent';
    case TelegramActivityCheckCompleted = 'telegram.activity_check_completed';
    case TelegramBotUsernameFetchFailed = 'telegram.bot_username_fetch_failed';
    case AuthTelegramSignatureInvalid = 'auth.telegram_signature_invalid';
    case SecurityCaptchaFailOpen = 'security.captcha_fail_open';

    public function label(): string
    {
        return match ($this) {
            self::UntappdBeerSynced => 'Синхронизация пива Untappd',
            self::UntappdBeerSyncFailed => 'Ошибка синхронизации пива Untappd',
            self::UntappdBeerSyncBatchCompleted => 'Итог прогона синхронизации Untappd',
            self::CatalogImportCompleted => 'Импорт каталога завершён',
            self::CatalogImportFailed => 'Ошибка импорта каталога',
            self::CatalogImportStaleProductsZeroed => 'Обнуление остатков устаревших товаров',
            self::CatalogImportVolumeDiscrepanciesDetected => 'Расхождения по объёму при импорте',
            self::CatalogImportFtpDownloadFailed => 'Ошибка загрузки каталога с FTP',
            self::CatalogImportRowsSkipped => 'Строки каталога пропущены при импорте',
            self::CatalogImportPriceCoerced => 'Цена/остаток приведены к 0 при импорте',
            self::CatalogProductMediaRefreshFailed => 'Ошибка обновления изображения товара',
            self::OrderFtpUploadFailed => 'Ошибка выгрузки заказа на FTP',
            self::OrderTelegramNotificationFailed => 'Ошибка Telegram-уведомления о заказе',
            self::OrderNotificationEmailFailed => 'Ошибка email-уведомления о заказе',
            self::OrderCreationFailed => 'Ошибка оформления заказа',
            self::QueueJobFailed => 'Ошибка фоновой задачи (очередь)',
            self::VkPostBroadcast => 'Рассылка поста VK в Telegram',
            self::VkSyncFailed => 'Ошибка синхронизации постов VK',
            self::FeedbackSent => 'Отправка обратной связи из профиля',
            self::TelegramActivityCheckCompleted => 'Итог проверки активности Telegram-ботов',
            self::TelegramBotUsernameFetchFailed => 'Ошибка получения username Telegram-бота',
            self::AuthTelegramSignatureInvalid => 'Подделанная подпись Telegram-логина',
            self::SecurityCaptchaFailOpen => 'SmartCaptcha пропустила проверку (fail-open)',
        };
    }
}
