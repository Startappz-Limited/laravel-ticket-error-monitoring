<?php

namespace OurTicketing\ErrorMonitor\Facades;

use Illuminate\Support\Facades\Facade;
use OurTicketing\ErrorMonitor\MonitorClient;

/**
 * @method static void report(\Throwable $e, array $extra = [])
 * @method static void capture(string $message, string $severity = 'medium', array $context = [])
 * @method static bool ready()
 *
 * @see MonitorClient
 */
class Monitor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'error-monitor';
    }
}
