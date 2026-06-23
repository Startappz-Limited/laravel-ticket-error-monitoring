<?php

namespace OurTicketing\ErrorMonitor\Tests;

use OurTicketing\ErrorMonitor\Support\Sanitizer;
use PHPUnit\Framework\TestCase;

class SanitizerTest extends TestCase
{
    private Sanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new Sanitizer(['password', 'token', 'authorization', 'api_key']);
    }

    public function test_masks_sensitive_keys(): void
    {
        $clean = $this->sanitizer->clean([
            'password' => 'hunter2',
            'api_token' => 'abc',
            'Authorization' => 'Bearer x',
            'name' => 'jane',
        ]);

        $this->assertSame(Sanitizer::REDACTED, $clean['password']);
        $this->assertSame(Sanitizer::REDACTED, $clean['api_token']);
        $this->assertSame(Sanitizer::REDACTED, $clean['Authorization']);
        $this->assertSame('jane', $clean['name']);
    }

    public function test_recurses_into_nested_arrays(): void
    {
        $clean = $this->sanitizer->clean(['payload' => ['token' => 't', 'page' => 3]]);

        $this->assertSame(Sanitizer::REDACTED, $clean['payload']['token']);
        $this->assertSame(3, $clean['payload']['page']);
    }

    public function test_obfuscates_email(): void
    {
        $clean = $this->sanitizer->clean(['note' => 'mail jane.doe@example.com please']);

        $this->assertStringNotContainsString('jane.doe@example.com', $clean['note']);
        $this->assertStringContainsString('@example.com', $clean['note']);
    }

    public function test_empty_input(): void
    {
        $this->assertSame([], $this->sanitizer->clean(null));
    }
}
