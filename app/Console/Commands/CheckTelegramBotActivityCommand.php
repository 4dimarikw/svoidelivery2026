<?php

namespace App\Console\Commands;

use Domain\Auth\Models\User;
use Domain\Telegram\Actions\CheckTelegramBotAvailabilityAction;
use Domain\Telegram\Models\TelegramBot;
use Illuminate\Console\Command;
use Throwable;

class CheckTelegramBotActivityCommand extends Command
{
    protected $signature = 'telegram:check-activity
        {--ids= : Проверить только пользователей с указанными id через запятую}
        {--chunk=200 : Размер пачки (Eloquent chunkById)}
        {--delay=100 : Пауза между отправками, мс (Bot API держит ~30 запросов/сек)}';

    protected $description = 'Проверить для всех привязанных Telegram-чатов, может ли бот писать пользователю (sendChatAction TYPING)';

    // Умышленно без --dry-run: отправка запроса в Telegram неоткатываема,
    // транзакция вокруг неё дала бы ложное ощущение безопасности.
    public function handle(CheckTelegramBotAvailabilityAction $action): int
    {
        if (TelegramBot::current() === null) {
            $this->error('Активный бот не найден. Добавьте его: php artisan telegraph:new-bot');

            return self::FAILURE;
        }

        $chunkSize = (int) $this->option('chunk');

        if ($chunkSize <= 0) {
            $this->error('--chunk должен быть положительным числом.');

            return self::FAILURE;
        }

        $delayMs = (int) $this->option('delay');

        if ($delayMs < 0) {
            $this->error('--delay не может быть отрицательным.');

            return self::FAILURE;
        }

        $ids = $this->parseIdFilter();

        if ($ids === null) {
            return self::FAILURE;
        }

        $query = User::query()->whereHas('telegramChat')->with('telegramChat');

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        $processed = 0;
        $active = 0;
        $inactive = 0;
        $inactiveList = [];
        $errors = [];

        $query->chunkById($chunkSize, function ($users) use ($action, $delayMs, &$processed, &$active, &$inactive, &$inactiveList, &$errors): void {
            foreach ($users as $user) {
                $processed++;

                try {
                    if ($action($user)) {
                        $active++;
                    } else {
                        $inactive++;
                        $inactiveList[] = "Пользователь #{$user->id}: {$user->profile?->last_bot_error}";
                    }
                } catch (Throwable $e) {
                    $errors[] = "Пользователь #{$user->id}: {$e->getMessage()}";
                }

                if ($delayMs > 0) {
                    usleep($delayMs * 1000);
                }
            }
        });

        $this->table(['Метрика', 'Количество'], [
            ['Обработано пользователей', $processed],
            ['Бот активен', $active],
            ['Бот недоступен', $inactive],
            ['Ошибок', count($errors)],
        ]);

        if ($inactiveList !== []) {
            $this->newLine();
            $this->warn('Бот недоступен:');
            foreach (array_slice($inactiveList, 0, 50) as $line) {
                $this->line("  {$line}");
            }
            if (count($inactiveList) > 50) {
                $this->line('  ... и ещё '.(count($inactiveList) - 50));
            }
        }

        if ($errors !== []) {
            $this->newLine();
            $this->warn('Ошибки:');
            foreach (array_slice($errors, 0, 50) as $error) {
                $this->line("  {$error}");
            }
            if (count($errors) > 50) {
                $this->line('  ... и ещё '.(count($errors) - 50));
            }
        }

        return self::SUCCESS;
    }

    /**
     * Парсит --ids в массив id пользователей.
     *
     * Возвращает:
     * - пустой массив — флаг не передан, фильтр выключен (все пользователи);
     * - массив id — только реально существующие id из БД (неизвестные отброшены с warn);
     * - null — все переданные id неизвестны; команда должна завершиться с FAILURE.
     *
     * @return list<int>|null
     */
    private function parseIdFilter(): ?array
    {
        $raw = $this->option('ids');

        if ($raw === null || $raw === '') {
            return [];
        }

        $requested = array_values(array_unique(array_filter(
            array_map('trim', explode(',', $raw)),
            fn (string $id): bool => $id !== '' && ctype_digit($id),
        )));

        if ($requested === []) {
            return [];
        }

        $known = User::query()->whereIn('id', $requested)->pluck('id')->all();
        $unknown = array_values(array_diff($requested, array_map('strval', $known)));

        if ($unknown !== []) {
            $this->warn('Неизвестные id пользователей (пропущены): '.implode(', ', $unknown));
        }

        if ($known === []) {
            $this->error('Ни один из переданных id пользователей не найден.');

            return null;
        }

        return $known;
    }
}
