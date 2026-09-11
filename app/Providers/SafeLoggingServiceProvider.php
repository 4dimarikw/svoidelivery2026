<?php

namespace App\Providers;

use Illuminate\Queue\Failed\DatabaseUuidFailedJobProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Support\Logging\EncryptedFailedJobProvider;
use Support\Logging\LogCorrelation;
use Support\Logging\RedactingLogManager;

class SafeLoggingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('log', fn ($app) => new RedactingLogManager($app));
        Log::clearResolvedInstance('log');
        $this->app->extend('queue.failer', function ($provider, $app) {
            if ($provider instanceof DatabaseUuidFailedJobProvider) {
                $config = $app['config']['queue.failed'];

                return new EncryptedFailedJobProvider($app['db'], $config['database'], $config['table']);
            }

            return $provider;
        });
    }

    public function boot(): void
    {
        Queue::before(fn () => LogCorrelation::reset());
        $except = [...(array) config('debugbar.except', []), 'auth/telegram/*'];
        $webhook = config('telegraph.webhook_url', config('telegraph.webhook.url'));
        if (is_string($webhook)) {
            $except[] = ltrim(str_replace('{token}', '*', $webhook), '/');
        }
        config(['debugbar.except' => array_unique($except)]);
        if ($this->app->environment('production')) {
            // Debugbar хранит сырые запросы отдельно от каналов Monolog.
            config(['debugbar.enabled' => false]);
        }
    }
}
