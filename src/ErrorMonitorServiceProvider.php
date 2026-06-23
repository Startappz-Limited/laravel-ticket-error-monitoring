<?php

namespace OurTicketing\ErrorMonitor;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use OurTicketing\ErrorMonitor\Exceptions\MonitorExceptionHandler;
use OurTicketing\ErrorMonitor\Http\Transport;
use OurTicketing\ErrorMonitor\Support\PayloadFactory;
use OurTicketing\ErrorMonitor\Support\Sanitizer;

class ErrorMonitorServiceProvider extends ServiceProvider
{
    /**
     * Register container bindings and decorate the exception handler.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/monitoring.php', 'monitoring');

        $this->app->singleton('error-monitor', function ($app) {
            $config = $app['config']->get('monitoring');
            $sanitizer = new Sanitizer($config['sanitize']['fields'] ?? []);

            return new MonitorClient($config, new PayloadFactory($config, $sanitizer), new Transport($config));
        });

        $this->app->alias('error-monitor', MonitorClient::class);

        $this->decorateExceptionHandler();
    }

    /**
     * Publish config and register runtime listeners.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/monitoring.php' => $this->app->configPath('monitoring.php'),
        ], 'monitoring-config');

        if (! $this->client()->ready()) {
            return;
        }

        $this->registerQueueFailureCapture();
        $this->registerSlowQueryCapture();
    }

    /**
     * Wrap the bound exception handler so unhandled exceptions are reported.
     */
    private function decorateExceptionHandler(): void
    {
        if (! $this->captureEnabled('exceptions')) {
            return;
        }

        $this->app->extend(ExceptionHandler::class, function ($handler, $app) {
            return new MonitorExceptionHandler(
                $handler,
                $app->make('error-monitor'),
                $app['config']->get('monitoring.dont_report', [])
            );
        });
    }

    /**
     * Report failed queue jobs.
     */
    private function registerQueueFailureCapture(): void
    {
        if (! $this->captureEnabled('queue_failures')) {
            return;
        }

        Event::listen(JobFailed::class, function (JobFailed $event) {
            $this->client()->report($event->exception, [
                'context' => ['queue_job' => $event->job->resolveName(), 'connection' => $event->connectionName],
            ]);
        });
    }

    /**
     * Capture queries slower than the configured threshold.
     */
    private function registerSlowQueryCapture(): void
    {
        if (! $this->captureEnabled('slow_queries')) {
            return;
        }

        $threshold = (int) $this->app['config']->get('monitoring.capture.slow_query_threshold', 1000);

        Event::listen(QueryExecuted::class, function (QueryExecuted $event) use ($threshold) {
            if ($event->time >= $threshold) {
                $this->client()->capture('Slow query ('.round($event->time).'ms)', 'medium', [
                    'sql' => $event->sql,
                    'time_ms' => $event->time,
                    'connection' => $event->connectionName,
                ]);
            }
        });
    }

    /**
     * Whether a given capture toggle is enabled.
     */
    private function captureEnabled(string $key): bool
    {
        return (bool) $this->app['config']->get("monitoring.capture.$key", false);
    }

    private function client(): MonitorClient
    {
        return $this->app->make('error-monitor');
    }
}
