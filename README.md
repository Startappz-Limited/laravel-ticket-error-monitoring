# our-ticketing/error-monitor

Laravel SDK for the TicketGo **Application Monitoring & Crash Analytics** platform. It captures
unhandled exceptions, queue failures and (optionally) slow queries, sanitizes secrets/PII, and ships
signed reports to your TicketGo instance — which deduplicates, groups, and auto-creates tickets.

> Designed to be **fail-safe**: a misconfiguration or network error never throws into your app.

## Install

```bash
composer require our-ticketing/error-monitor
php artisan vendor:publish --tag=monitoring-config
```

Add the credentials issued when you registered the application in the TicketGo admin panel:

```dotenv
MONITORING_ENABLED=true
MONITORING_API_KEY=pk_xxxxxxxx          # public key  → X-Monitor-Key
MONITORING_SECRET=xxxxxxxxxxxxxxxx      # secret      → HMAC signs the body
MONITORING_ENDPOINT=https://support.example.com/api/v1/errors
MONITORING_RELEASE=1.4.0                # optional, for release tracking
```

That's it — the package auto-discovers its service provider and starts reporting unhandled
exceptions. No changes to `bootstrap/app.php` are required.

## What it captures

| Source | Toggle (`config/monitoring.php`) |
|---|---|
| Unhandled exceptions / fatal errors | `capture.exceptions` (on) |
| Failed queue jobs | `capture.queue_failures` (on) |
| Slow queries (≥ threshold ms) | `capture.slow_queries` (off) |

Each report includes: exception class, message, severity, full stack trace, sanitized HTTP context
and request payload, framework/PHP versions, release, hostname, route/controller, request id, and the
authenticated user id.

## Manual reporting

```php
use OurTicketing\ErrorMonitor\Facades\Monitor;

try {
    $gateway->charge($order);
} catch (\Throwable $e) {
    Monitor::report($e, ['context' => ['order_id' => $order->id]]);
    throw $e;
}

// Or a diagnostic without an exception:
Monitor::capture('Inventory sync drifted', 'high', ['skus' => 42]);
```

## Data protection

Keys listed in `config('monitoring.sanitize.fields')` are redacted (case-insensitive substring match)
from request payloads, headers and context **before** anything leaves your app. Emails and long digit
sequences are obfuscated. Exceptions in `config('monitoring.dont_report')` (auth, validation, 404 by
default) are never sent.

## How signing works

The transport sends the JSON body with two headers:

- `X-Monitor-Key`: your public key
- `X-Monitor-Signature`: `hash_hmac('sha256', $rawJsonBody, $secret)`

The platform recomputes the HMAC with the stored secret and rejects mismatches (HTTP 401).

## Performance

By default (`MONITORING_DEFERRED=true`) the HTTP call is deferred to the framework's terminating phase,
so reporting adds no latency to the user's response. In console/queue context it sends inline with a
short timeout.
