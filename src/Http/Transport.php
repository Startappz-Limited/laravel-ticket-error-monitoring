<?php

namespace OurTicketing\ErrorMonitor\Http;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Http;

/**
 * Signs and ships payloads to the monitoring endpoint. Fail-safe by design:
 * a transport error must never propagate into the host application.
 */
class Transport
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private array $config) {}

    /**
     * Send a payload, deferring to the terminating phase when configured.
     *
     * @param  array<string, mixed>  $payload
     */
    public function send(array $payload): void
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

        if ($body === false) {
            return;
        }

        $dispatch = fn () => $this->post($body);

        if (($this->config['send_after_response'] ?? true) && $this->canDefer()) {
            Container::getInstance()->terminating($dispatch);

            return;
        }

        $dispatch();
    }

    /**
     * Perform the signed HTTP POST, swallowing any failure.
     */
    private function post(string $body): void
    {
        try {
            Http::withHeaders([
                'X-Monitor-Key' => $this->config['api_key'],
                'X-Monitor-Signature' => hash_hmac('sha256', $body, (string) $this->config['secret']),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->timeout((int) ($this->config['timeout'] ?? 3))
                ->withOptions(['verify' => (bool) ($this->config['verify_ssl'] ?? true)])
                ->withBody($body, 'application/json')
                ->post((string) $this->config['endpoint']);
        } catch (\Throwable) {
            // Monitoring must never break the host app; drop the report silently.
        }
    }

    /**
     * Deferred sending is only safe when a terminable container is available.
     */
    private function canDefer(): bool
    {
        return method_exists(Container::getInstance(), 'terminating');
    }
}
