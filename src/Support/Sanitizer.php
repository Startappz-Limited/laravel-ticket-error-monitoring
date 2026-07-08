<?php

namespace OurTicketing\ErrorMonitor\Support;

/**
 * Client-side redaction of secrets and PII before any data leaves the host app.
 */
class Sanitizer
{
    public const REDACTED = '[REDACTED]';

    /**
     * @param  string[]  $sensitiveKeys
     */
    public function __construct(private array $sensitiveKeys = []) {}

    /**
     * Recursively redact sensitive keys and obfuscate PII in an array tree.
     *
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>
     */
    public function clean(?array $data): array
    {
        if (empty($data)) {
            return [];
        }

        $clean = [];
        foreach ($data as $key => $value) {
            $clean[$key] = $this->cleanEntry((string) $key, $value);
        }

        return $clean;
    }

    /**
     * Resolve a single key/value pair to its sanitised form.
     */
    private function cleanEntry(string $key, mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->clean($value);
        }

        if ($this->isSensitive($key)) {
            return self::REDACTED;
        }

        return is_string($value) ? $this->obfuscatePii($value) : $value;
    }

    /**
     * Redact secrets from a free-text string (exception message, stack trace)
     * before it leaves the host app.
     *
     * Array-based context is scrubbed by key via clean(); messages are opaque
     * strings, so PDO/QueryException connection details (Host, Port, Database,
     * credentials) and DSN passwords must be stripped by pattern instead.
     */
    public function cleanText(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        // "key: value" / "key=value" connection pairs, e.g. the tail of a
        // QueryException: "(Connection: mysql, Host: 1.2.3.4, Port: 3306,
        // Database: app, ...)" or a PDO DSN "host=...;port=...;dbname=...".
        $text = preg_replace(
            '/\b(hostname|host|server|port|database|dbname|db|username|user|uid|password|passwd|pwd)(\s*[:=]\s*)[^\s,;)"\']+/i',
            '$1$2'.self::REDACTED,
            $text
        ) ?? $text;

        // Credentials embedded in DSN / URL connection strings: scheme://user:pass@host.
        $text = preg_replace(
            '#([a-z][a-z0-9+.\-]*://)[^:@\s/]+(?::[^@\s/]+)?@#i',
            '$1'.self::REDACTED.'@',
            $text
        ) ?? $text;

        return $this->obfuscatePii($text);
    }

    /**
     * Match a key against the configured denylist.
     */
    private function isSensitive(string $key): bool
    {
        $key = strtolower($key);
        foreach ($this->sensitiveKeys as $needle) {
            if (str_contains($key, strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mask emails and long digit runs (phones / card-like numbers) in free text.
     */
    private function obfuscatePii(string $value): string
    {
        $value = preg_replace('/([a-zA-Z0-9._%+-])[a-zA-Z0-9._%+-]*(@[^\s]+)/', '$1***$2', $value) ?? $value;

        return preg_replace('/\b(\d{2})\d{5,}(\d{2})\b/', '$1****$2', $value) ?? $value;
    }
}
