<?php

namespace OurTicketing\ErrorMonitor\Exceptions;

use Illuminate\Contracts\Debug\ExceptionHandler;
use OurTicketing\ErrorMonitor\MonitorClient;
use Throwable;

/**
 * Decorates the host application's exception handler so unhandled exceptions are
 * forwarded to the monitoring platform before the original handler runs.
 *
 * All other handler behaviour (rendering, console output) is delegated untouched.
 */
class MonitorExceptionHandler implements ExceptionHandler
{
    /**
     * @param  array<int, class-string>  $dontReport
     */
    public function __construct(
        private ExceptionHandler $inner,
        private MonitorClient $monitor,
        private array $dontReport = []
    ) {}

    /**
     * Report to the platform (best-effort) then delegate to the inner handler.
     */
    public function report(Throwable $e): void
    {
        if ($this->shouldMonitor($e)) {
            rescue(fn () => $this->monitor->report($e), report: false);
        }

        $this->inner->report($e);
    }

    public function shouldReport(Throwable $e): bool
    {
        return $this->inner->shouldReport($e);
    }

    public function render($request, Throwable $e)
    {
        return $this->inner->render($request, $e);
    }

    public function renderForConsole($output, Throwable $e): void
    {
        $this->inner->renderForConsole($output, $e);
    }

    /**
     * Forward any handler methods not on the interface (e.g. reportable()).
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->inner->{$method}(...$arguments);
    }

    /**
     * Skip ignored exception classes (and their subclasses).
     */
    private function shouldMonitor(Throwable $e): bool
    {
        foreach ($this->dontReport as $ignored) {
            if ($e instanceof $ignored) {
                return false;
            }
        }

        return true;
    }
}
