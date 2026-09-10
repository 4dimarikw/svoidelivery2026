<?php

namespace Domain\Telegram\Support;

use Closure;
use Domain\Telegram\Models\TelegramBot;
use Illuminate\Support\Facades\DB;

final class TelegramIdentityTransaction
{
    /** Null locks all bots for account-wide unlink. Callbacks must not send network requests. */
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
