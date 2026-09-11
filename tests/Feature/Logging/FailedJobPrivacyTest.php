<?php

namespace Tests\Feature\Logging;

use Domain\Logging\Models\EventLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Support\Logging\EncryptedFailedJobProvider;
use Tests\TestCase;

class FailedJobPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_payload_is_encrypted_but_can_be_read_for_retry(): void
    {
        $provider = app('queue.failer');
        $this->assertInstanceOf(EncryptedFailedJobProvider::class, $provider);
        $payload = json_encode(['uuid' => (string) Str::uuid(), 'displayName' => 'TestJob', 'data' => ['command' => serialize((object) ['password' => 'payload-secret'])]]);
        $id = $provider->log('database', 'default', $payload, new \RuntimeException('password=exception-secret'));
        $row = DB::table('failed_jobs')->where('uuid', $id)->first();
        $this->assertStringNotContainsString('payload-secret', $row->payload);
        $this->assertStringNotContainsString('exception-secret', $row->exception);
        $this->assertStringContainsString('correlation_id', $row->exception);
        $this->assertSame($payload, $provider->find($id)->payload);
        $this->assertStringNotContainsString('payload-secret', $provider->all()[0]->payload);
        Queue::fake();
        $this->artisan('queue:retry', ['id' => [$id]])->assertSuccessful();
        $retried = Queue::pushedRaw()->sole();
        $this->assertSame(json_decode($payload, true), json_decode($retried['payload'], true));
        $this->assertNull($provider->find($id));
    }

    public function test_legacy_failed_payload_remains_readable(): void
    {
        $id = (string) Str::uuid();
        $payload = json_encode(['uuid' => $id, 'displayName' => 'OldJob']);
        DB::table('failed_jobs')->insert(['uuid' => $id, 'connection' => 'database', 'queue' => 'default', 'payload' => $payload, 'exception' => 'old failure', 'failed_at' => now()]);
        $this->assertSame($payload, app('queue.failer')->find($id)->payload);
    }

    public function test_pruning_only_removes_expired_records(): void
    {
        $this->freezeTime();
        foreach ([91, 89] as $days) {
            EventLog::query()->create(['event_type' => 'test', 'level' => 'info', 'message' => 'safe', 'context' => [], 'created_at' => now()->subDays($days)]);
        }
        foreach ([8, 6] as $days) {
            DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'safe', 'failed_at' => now()->subDays($days)]);
        }
        $this->artisan('logs:prune-sensitive')->assertSuccessful();
        $this->assertSame(1, EventLog::count());
        $this->assertSame(1, DB::table('failed_jobs')->count());
    }
}
