<?php

namespace OurTicketing\ErrorMonitor\Support;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Assembles the JSON payload accepted by POST /api/v1/errors.
 */
class PayloadFactory
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private array $config,
        private Sanitizer $sanitizer
    ) {}

    /**
     * Build a payload from a throwable, merging caller-supplied extras.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function fromThrowable(Throwable $e, array $extra = []): array
    {
        return $this->assemble(
            class: get_class($e),
            message: $e->getMessage(),
            severity: $extra['severity'] ?? SeverityMapper::fromThrowable($e),
            stackTrace: $e->getTraceAsString(),
            extra: $extra
        );
    }

    /**
     * Build a payload for a manual capture() call (no throwable).
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function fromMessage(string $message, string $severity, array $context = []): array
    {
        return $this->assemble(
            class: 'ManualCapture',
            message: $message,
            severity: $severity,
            stackTrace: null,
            extra: ['context' => $context]
        );
    }

    /**
     * Compose the full payload structure.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function assemble(string $class, string $message, string $severity, ?string $stackTrace, array $extra): array
    {
        $context = empty($extra['context'])
            ? $this->context()
            : array_merge($this->context(), $extra['context']);

        return [
            'exception_class' => $class,
            'message' => $this->sanitizer->cleanText($message),
            'severity' => $severity,
            'environment' => $this->config['environment'] ?? 'production',
            'stack_trace' => $this->sanitizer->cleanText($stackTrace),
            'timestamp' => now()->toIso8601String(),
            'http_context' => $this->httpContext(),
            'request_payload' => $this->requestPayload(),
            'metadata' => $this->metadata(),
            'context' => $context,
        ];
    }

    /**
     * Framework/host metadata.
     *
     * @return array<string, mixed>
     */
    private function metadata(): array
    {
        $request = $this->request();
        $app = Container::getInstance();

        return [
            'framework' => 'laravel',
            'framework_version' => method_exists($app, 'version') ? $app->version() : null,
            'php_version' => PHP_VERSION,
            'release_version' => $this->config['release'] ?? null,
            'hostname' => gethostname() ?: null,
            'server_ip' => $_SERVER['SERVER_ADDR'] ?? null,
            'route' => $request?->route()?->getName(),
            'controller' => $request?->route()?->getActionName(),
            'request_id' => $request?->header('X-Request-Id'),
            'user_id' => Auth::hasUser() ? Auth::id() : null,
            'tenant_id' => $this->config['tenant_id'] ?? null,
        ];
    }

    /**
     * HTTP request line context (only inside a request lifecycle).
     *
     * @return array<string, mixed>
     */
    private function httpContext(): array
    {
        $request = $this->request();

        if (! $request) {
            return [];
        }

        return [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'headers' => $this->sanitizer->clean($request->headers->all()),
        ];
    }

    /**
     * Sanitised request input.
     *
     * @return array<string, mixed>
     */
    private function requestPayload(): array
    {
        return $this->sanitizer->clean($this->request()?->all() ?? []);
    }

    /**
     * Runtime diagnostics.
     *
     * @return array<string, mixed>
     */
    private function context(): array
    {
        return [
            'memory_peak' => round(memory_get_peak_usage(true) / 1048576, 2).' MB',
            'execution_time' => $this->executionTime(),
        ];
    }

    /**
     * Seconds since request start, when available.
     */
    private function executionTime(): ?string
    {
        $start = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;

        return $start ? round((microtime(true) - $start) * 1000).' ms' : null;
    }

    /**
     * The bound HTTP request, or null in console/queue context.
     */
    private function request(): ?Request
    {
        $container = Container::getInstance();

        return $container->bound('request') ? $container->make('request') : null;
    }
}
