<?php

namespace App\Events;

/**
 * Общий сбой любого queue-job'а/Mailable, исчерпавшего все попытки очереди —
 * попадает в event_logs через App\Listeners\PersistEventLog (автодискавери
 * по LoggableEvent, как и CatalogStaleProductsZeroed/OrderFtpUploadFailed).
 * Дублирует специфичные события у job'ов, которые уже сами репортят свой
 * провал (например App\Jobs\UploadOrderToFtpJob → OrderFtpUploadFailed) —
 * это осознанно, см. App\Listeners\LogFailedQueueJob.
 */
final readonly class QueuedJobFailed implements LoggableEvent
{
    public function __construct(
        public string $jobName,
        public string $connectionName,
        public ?string $queue,
        public int $attempts,
        public ?string $uuid,
        public string $exceptionClass,
        public string $errorMessage,
    ) {}

    public function eventType(): string
    {
        return 'queue.job_failed';
    }

    public function level(): string
    {
        return 'error';
    }

    public function message(): string
    {
        return "Задача «{$this->jobName}» провалилась после {$this->attempts} попыт(ок) очереди: {$this->errorMessage}.";
    }

    public function context(): array
    {
        return [
            'job_name' => $this->jobName,
            'connection' => $this->connectionName,
            'queue' => $this->queue,
            'attempts' => $this->attempts,
            'uuid' => $this->uuid,
            'exception_class' => $this->exceptionClass,
            'error_message' => $this->errorMessage,
        ];
    }
}
