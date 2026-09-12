<?php

namespace Support\Logging;

use Illuminate\Queue\Failed\DatabaseUuidFailedJobProvider;
use Illuminate\Support\Facades\Crypt;

final class EncryptedFailedJobProvider extends DatabaseUuidFailedJobProvider
{
    public function log($connection, $queue, $payload, $exception)
    {
        $redactor = new LogRedactor;
        $original = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        $envelope = json_encode([
            'uuid' => $original['uuid'],
            'displayName' => $redactor->text($original['displayName'] ?? 'job'),
            'encrypted_failed_payload' => Crypt::encryptString($payload),
        ], JSON_THROW_ON_ERROR);

        return parent::log($redactor->text($connection), $redactor->text($queue), $envelope,
            json_encode(['correlation_id' => LogCorrelation::id(), 'exception' => $redactor->value($exception)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    public function find($id)
    {
        $record = parent::find($id);
        if ($record !== null) {
            $envelope = json_decode($record->payload, true);
            if (isset($envelope['encrypted_failed_payload'])) {
                // Расшифровывает данные для повторного запуска; поддерживает также записи без шифрования.
                $record->payload = Crypt::decryptString($envelope['encrypted_failed_payload']);
            }
        }

        return $record;
    }
}
