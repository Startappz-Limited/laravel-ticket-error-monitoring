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

    public function test_redacts_connection_details_in_query_exception_message(): void
    {
        $message = "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'sent_at' in 'SET' "
            .'(Connection: mysql, Host: 46.101.89.24, Port: 3306, Database: point, '
            .'SQL: update `otp_codes` set `sent_at` = 2026-07-08 10:46:03 where `id` = 175)';

        $clean = $this->sanitizer->cleanText($message);

        $this->assertStringNotContainsString('46.101.89.24', $clean);
        $this->assertStringNotContainsString('Database: point', $clean);
        $this->assertStringContainsString('Host: '.Sanitizer::REDACTED, $clean);
        $this->assertStringContainsString('Port: '.Sanitizer::REDACTED, $clean);
        $this->assertStringContainsString('Database: '.Sanitizer::REDACTED, $clean);
        // The SQL statement itself stays for debuggability.
        $this->assertStringContainsString('update `otp_codes`', $clean);
    }

    public function test_redacts_dsn_credentials(): void
    {
        $this->assertSame(
            'mysql://'.Sanitizer::REDACTED.'@db.internal:3306/app',
            $this->sanitizer->cleanText('mysql://root:s3cr3t@db.internal:3306/app')
        );
    }

    public function test_redacts_pdo_dsn_and_inline_password(): void
    {
        $clean = $this->sanitizer->cleanText('mysql:host=10.0.0.5;port=3306;dbname=app;user=root;password=hunter2');

        $this->assertStringNotContainsString('10.0.0.5', $clean);
        $this->assertStringNotContainsString('hunter2', $clean);
        $this->assertStringNotContainsString('dbname=app', $clean);
    }

    public function test_cleantext_leaves_ordinary_text_intact(): void
    {
        $this->assertSame('Something went wrong while saving.', $this->sanitizer->cleanText('Something went wrong while saving.'));
        $this->assertNull($this->sanitizer->cleanText(null));
    }
}
