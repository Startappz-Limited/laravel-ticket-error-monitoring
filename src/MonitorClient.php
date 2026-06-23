<?php

namespace OurTicketing\ErrorMonitor;

use OurTicketing\ErrorMonitor\Http\Transport;
use OurTicketing\ErrorMonitor\Support\PayloadFactory;
use Throwable;

/**
 * Public entry point for the SDK. Resolve via the container or the Monitor facade.
 */
class MonitorClient
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private array $config,
        private PayloadFactory $factory,
        private Transport $transport
    ) {}

    /**
     * Report a captured throwable to the monitoring platform.
     *
     * @param  array<string, mixed>  $extra
     */
    public function report(Throwable $e, array $extra = []): void
    {
        if (! $this->ready()) {
            return;
        }

        $this->transport->send($this->factory->fromThrowable($e, $extra));
    }

    /**
     * Manually capture a message/diagnostic without a throwable.
     *
     * @param  array<string, mixed>  $context
     */
    public function capture(string $message, string $severity = 'medium', array $context = []): void
    {
        if (! $this->ready()) {
            return;
        }

        $this->transport->send($this->factory->fromMessage($message, $severity, $context));
    }

    /**
     * The SDK is operational only when enabled and fully configured.
     */
    public function ready(): bool
    {
        return ($this->config['enabled'] ?? false)
            && ! empty($this->config['api_key'])
            && ! empty($this->config['secret'])
            && ! empty($this->config['endpoint']);
    }
}
