<?php

namespace OurTicketing\ErrorMonitor\Tests;

use OurTicketing\ErrorMonitor\Support\SeverityMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SeverityMapperTest extends TestCase
{
    public function test_native_errors_are_critical(): void
    {
        $this->assertSame('critical', SeverityMapper::fromThrowable(new \TypeError('boom')));
    }

    public function test_server_http_errors_are_high(): void
    {
        $this->assertSame('high', SeverityMapper::fromThrowable(new HttpException(500)));
    }

    public function test_client_http_errors_are_medium(): void
    {
        $this->assertSame('medium', SeverityMapper::fromThrowable(new HttpException(403)));
    }

    public function test_generic_exceptions_default_to_high(): void
    {
        $this->assertSame('high', SeverityMapper::fromThrowable(new \RuntimeException('x')));
    }
}
