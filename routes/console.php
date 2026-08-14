<?php

use Infrastructure\Settings\VKSyncSettings;

Schedule::command('queue:work --stop-when-empty')->everyMinute();

// rescue() — чтение настроек на бутстрапе консоли до миграций (напр. в
// тестах перед RefreshDatabase) не должно ронять весь артисан, см.
// CLAUDE.md "Telegram login" про ту же ловушку с TelegramBot::current()
// в AppServiceProvider::boot(). Команда сама себя гасит по флагу active,
// поэтому лишний тик по дефолтному расписанию безвреден.
$vkCron = rescue(fn () => app(VKSyncSettings::class)->cron, '0 * * * *', report: false);
Schedule::command('vk:sync-posts')->cron($vkCron ?: '0 * * * *');
