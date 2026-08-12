# Application-owned request-summary destination-record reference

[ADR 051](../decisions/051-application-owned-structured-log-destinations.md) accepts this optional application-owned reference. Copying it remains a deliberate application decision; PHPThis does not install it into framework core, the starter skeleton, or the executable example.

This final application-owned encoder accepts only the application's existing concrete `RequestSummary`. It does not define a logger, sink port, clock abstraction, writer, stream, file, generic emission policy, rotation policy, collector, or delivery guarantee. An adopting concrete sink obtains one instant from its explicitly injected application clock inside the existing sink invocation attempt, then calls `RequestSummaryDestinationRecord::line($summary, $clock->now())` before its one selected destination write.

The namespace assumes a version-1 starter-style application. An application whose accepted request summary lives in another namespace, including the executable Redis proof's version-2 summary, changes only the application namespace or explicit `RequestSummary` import. It does not change the encoder body or either closed summary schema.

<!-- phpthis-request-summary-destination-record-reference:start -->
```php
<?php

declare(strict_types=1);

namespace App\Observability;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use RuntimeException;

final class RequestSummaryDestinationRecord
{
    private const int RECORD_SCHEMA_VERSION = 1;
    private const int MAXIMUM_RECORD_BYTES = 65_536;
    private const string OCCURRED_AT_FORMAT = 'Y-m-d\TH:i:s.u\Z';
    private const string OCCURRED_AT_PATTERN =
        '/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]{6}Z\z/D';

    private function __construct()
    {
    }

    public static function line(RequestSummary $summary, DateTimeImmutable $occurredAt): string
    {
        $summaryPayload = $summary->toArray();
        $occurredAtText = $occurredAt
            ->setTimezone(new DateTimeZone('UTC'))
            ->format(self::OCCURRED_AT_FORMAT);

        if (preg_match(self::OCCURRED_AT_PATTERN, $occurredAtText) !== 1) {
            throw new RuntimeException(
                'Unable to format the request-summary destination-record timestamp.',
            );
        }

        $level = match (true) {
            $summaryPayload['outcome'] === 'unknown_failure',
            $summaryPayload['response_status'] >= 500 => 'error',
            $summaryPayload['query_failures'] > 0,
            $summaryPayload['query_budget_exceeded'] => 'warning',
            default => 'info',
        };

        try {
            $encoded = json_encode(
                [
                    'record_schema_version' => self::RECORD_SCHEMA_VERSION,
                    'occurred_at' => $occurredAtText,
                    'level' => $level,
                    'summary' => $summaryPayload,
                ],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            );
        } catch (JsonException) {
            throw new RuntimeException('Unable to encode the request-summary destination record.');
        }

        $line = $encoded . "\n";

        if (strlen($line) > self::MAXIMUM_RECORD_BYTES) {
            throw new RuntimeException(
                'Request-summary destination record exceeds 65536 bytes.',
            );
        }

        return $line;
    }
}
```
<!-- phpthis-request-summary-destination-record-reference:end -->

`record_schema_version` versions only this outer envelope. The nested `summary` is the complete result of exactly one `RequestSummary::toArray()` call, so the encoder adds, removes, or renames no summary field. The ordered `match` is the sole HTTP level-map owner and gives unknown failure and every status at least `500` precedence over query degradation. It always emits `info`, `warning`, or `error` for a valid closed request summary; neither the sink nor configuration repeats or suppresses that decision. `debug` and `critical` remain available only to separately adopted closed process-specific mappings.

`DateTimeImmutable` is converted explicitly to UTC rather than relying on the process timezone. The fixed pattern rejects a year that cannot be represented by the required four bytes. Throwing JSON encoding prevents partial or substituted output. `strlen()` counts encoded bytes, and the check occurs after the one final LF is appended, so 65,536 bytes is accepted and 65,537 bytes is rejected.

The adopter must prove before deployment that its worst-case valid summary fits. That proof uses the exact maximum database-source count, every source's selected `QueryTrace` retained-fingerprint limit, all closed summary extensions, the encoder's fixed envelope and JSON-escaping overhead, and the final LF. The post-encode rejection is defense against drift or a mistaken bound; silently relying on sink failure for an otherwise valid maximum summary is not an adoption policy.

All timestamp, formatting, mapping, encoding, and size failures must remain inside ADR 023's already-isolated sink invocation attempt. The concrete adopting sink owns the one selected destination write and its failure handling; it does not retry, fall back, truncate, split, emit a second record, or mutate the selected response.

The installed proof executes these exact copied bytes against the starter's real version-1 summary and an isolated version-2 compatibility control. It checks the outer order, nested value, UTC microseconds, mapping precedence, JSON/LF framing, exact size boundary, fixed redacted failures, and coordinator response isolation. It deliberately performs no file or stream write and cannot prove that an adopter's independently selected upstream bounds fit the record maximum. Therefore it does not certify worst-case sizing, file safety, permissions, locking, parallel append integrity, rotation, retention, disk-full behavior, supervisor collection, Alloy, Loki, Grafana, or durable delivery; those remain application and deployment evidence under [Operational log destination profiles](destination-profiles.md).
