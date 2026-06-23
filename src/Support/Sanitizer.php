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
