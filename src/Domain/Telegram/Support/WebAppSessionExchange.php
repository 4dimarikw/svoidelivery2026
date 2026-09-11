<?php

namespace Domain\Telegram\Support;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class WebAppSessionExchange
{
    public function claim(WebAppInitData $data, int $botId, Session $session): bool
    {
        // Случайная метка сохраняется при штатной регенерации ID сессии.
        $owner = $session->get('telegram_exchange_owner');
        if (! is_string($owner) || ! preg_match('/\A[a-f0-9]{64}\z/D', $owner)) {
            $owner = bin2hex(random_bytes(32));
            $session->put('telegram_exchange_owner', $owner);
        }
        // Ключ строится из проверенной подписи, порядок и кодирование query не влияют.
        $key = 'telegram-exchange:'.hash('sha256', $botId.':'.$data->signature);
        try {
            $cache = Cache::store(config('telegram-auth.webapp_exchange_cache_store'));
            if ($cache->add($key, $owner, max(1, $data->authDate + WebAppInitData::AUTH_DATE_TTL + 1 - now()->timestamp))) {
                return true;
            }
            $claimedBy = $cache->get($key);

            return is_string($claimedBy) && hash_equals($claimedBy, $owner);
        } catch (Throwable) {
            // При недоступном хранилище не разрешаем обмен без учёта повторов.
            return false;
        }
    }
}
