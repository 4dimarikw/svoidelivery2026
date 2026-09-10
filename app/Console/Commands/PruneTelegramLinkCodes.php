<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneTelegramLinkCodes extends Command
{
    protected $signature = 'telegram:prune-link-codes';

    protected $description = 'Удалить коды Telegram, истёкшие более суток назад';

    public function handle(): int
    {
        DB::table('telegram_link_codes')->where('expires_at', '<', now()->subDay())->delete();

        return self::SUCCESS;
    }
}
