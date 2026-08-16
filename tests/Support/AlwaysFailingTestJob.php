<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use RuntimeException;

/**
 * Job, всегда падающий в handle() — используется в
 * Tests\Feature\Queue\LogFailedQueueJobTest, чтобы прогнать настоящий сбой
 * очереди через Illuminate\Queue\Events\JobFailed. Не анонимный класс:
 * даже SyncQueue сериализует job в payload перед исполнением
 * (Illuminate\Queue\SyncQueue::createPayload()), а анонимные классы PHP
 * сериализовать не даёт ("Serialization of 'class@anonymous' is not
 * allowed").
 */
class AlwaysFailingTestJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public function handle(): void
    {
        throw new RuntimeException('boom');
    }
}
