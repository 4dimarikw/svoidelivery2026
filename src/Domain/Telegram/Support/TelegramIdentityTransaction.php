<?php

namespace Domain\Telegram\Support;

use Closure;
use Domain\Telegram\Models\TelegramBot;
use Illuminate\Support\Facades\DB;

final class TelegramIdentityTransaction
{
    /**
     * При null блокируются все боты для отвязки аккаунта. Обработчики внутри транзакции не должны отправлять
     * сетевые запросы.
     */
    public function run(?array $botIds, Closure $callback): mixed
    {
        return DB::transaction(function () use ($botIds, $callback) {
            $query = TelegramBot::query()->orderBy('id');
            if ($botIds !== null) {
                $query->whereKey($botIds);
            }
            $bots = $query->lockForUpdate()->get();

            return $callback($bots);
        }, 3);
    }
}
