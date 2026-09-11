<?php

declare(strict_types=1);

namespace Tests\Feature\Logging;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class EventLogPruningTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_keeps_events_and_failed_jobs_including_expired_ones(): void
    {
        DB::table('event_logs')->insert(['event_type' => 'test', 'level' => 'info', 'message' => 'safe', 'created_at' => now()->subDays(91)]);
        DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'safe', 'failed_at' => now()->subDays(8)]);
        $this->artisan('logs:prune-sensitive --dry-run')->expectsOutputToContain('Просроченных событий: 1')->assertSuccessful();
        $this->assertSame(1, DB::table('event_logs')->count());
        $this->assertSame(1, DB::table('failed_jobs')->count());
    }

    public function test_batches_are_bounded_and_resume_next_run_without_deleting_the_cutoff(): void
    {
        $this->travelTo(now()->startOfSecond());
        $old = ['event_type' => 'test', 'level' => 'info', 'message' => 'safe', 'created_at' => now()->subDays(91)];
        for ($i = 0; $i < 100; $i++) {
            DB::table('event_logs')->insert(array_fill(0, 1000, $old));
        }
        DB::table('event_logs')->insert($old);
        $freshId = DB::table('event_logs')->insertGetId([...$old, 'created_at' => now()->subDays(90)]);
        $batches = [];
        DB::listen(function ($query) use (&$batches): void {
            if (str_starts_with($query->sql, 'delete from `event_logs`')) {
                // Eloquent подставляет целочисленные ключи непосредственно в SQL.
                preg_match('/`id` in \(([^)]+)\)/', $query->sql, $matches);
                $batches[] = count(explode(',', $matches[1]));
            }
        });
        $this->artisan('logs:prune-sensitive')->assertSuccessful();
        $this->assertSame(array_fill(0, 100, 1000), $batches);
        $this->assertSame(2, DB::table('event_logs')->count());
        $this->assertNotNull(DB::table('event_logs')->find($freshId));
        $this->artisan('logs:prune-sensitive')->assertSuccessful();
        $this->assertSame([$freshId], DB::table('event_logs')->pluck('id')->all());
    }
}
