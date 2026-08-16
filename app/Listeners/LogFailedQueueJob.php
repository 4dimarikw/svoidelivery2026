<?php

namespace App\Listeners;

use App\Events\QueuedJobFailed;
use Illuminate\Queue\Events\JobFailed;
use Throwable;

/**
 * Общий предохранитель видимости: без него сбой job'а/Mailable без своего
 * failed()-репортинга (например Domain\Order\Mail\NewOrderCreated) оседает
 * только в failed_jobs/laravel.log и не виден в MoonShine. Слушает
 * вендорное Illuminate\Queue\Events\JobFailed напрямую — авто-дискавери
 * листенеров резолвит его по типу параметра handle(), как и
 * PersistEventLog::handle(LoggableEvent $event), отдельная регистрация не
 * нужна. Дёргается уже после собственного failed() job'а (см. порядок в
 * Illuminate\Queue\Jobs\Job::fail()), поэтому у job'ов со своим репортингом
 * (UploadOrderToFtpJob → OrderFtpUploadFailed) в event_logs будет две строки
 * на один сбой — см. докблок QueuedJobFailed.
 */
class LogFailedQueueJob
{
    public function handle(JobFailed $event): void
    {
        try {
            event(new QueuedJobFailed(
                jobName: $event->job->resolveName(),
                connectionName: $event->connectionName,
                queue: $event->job->getQueue(),
                attempts: $event->job->attempts(),
                uuid: $event->job->uuid(),
                exceptionClass: $event->exception::class,
                errorMessage: $event->exception->getMessage(),
            ));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
