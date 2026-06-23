<?php

namespace OurTicketing\ErrorMonitor\Support;

use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Derives a platform severity (critical|high|medium|low) from a throwable.
 */
class SeverityMapper
{
    /**
     * Map an exception/error to a severity string.
     */
    public static function fromThrowable(Throwable $e): string
    {
        // Native PHP fatal errors (TypeError, ParseError, etc.).
        if ($e instanceof \Error) {
            return 'critical';
        }

        if ($e instanceof HttpExceptionInterface) {
            return $e->getStatusCode() >= 500 ? 'high' : 'medium';
        }

        return 'high';
    }
}
