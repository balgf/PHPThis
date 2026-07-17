<?php

declare(strict_types=1);

namespace PHPThis\Database;

use InvalidArgumentException;

/**
 * @phpstan-type QueryAggregate array{fingerprint: string, executions: int, failures: int, total_execute_duration_us: int, max_execute_duration_us: int}
 * @phpstan-type QuerySnapshot array{schema_version: int, event: string, statements: int, failures: int, tracked_fingerprints: int, repeated_fingerprints: int, maximum_executions_per_fingerprint: int, total_execute_duration_us: int, slowest_execute_duration_us: int, truncated: bool, untracked_statements: int, queries: list<QueryAggregate>}
 */
final class QueryTrace
{
    /** @var array<string, QueryAggregate> */
    private array $queries = [];
    private int $statements = 0;
    private int $failures = 0;
    private int $untrackedStatements = 0;
    private int $repeatedFingerprints = 0;
    private int $maximumExecutions = 0;
    private int $totalExecuteDurationUs = 0;
    private int $slowestExecuteDurationUs = 0;

    public function __construct(private readonly int $fingerprintLimit)
    {
        if ($fingerprintLimit < 1) {
            throw new InvalidArgumentException('Query trace fingerprint limit must be at least 1.');
        }
    }

    public function recordStatement(string $sql, int $executeDurationUs, bool $failed): void
    {
        $this->statements++;
        $this->failures += $failed ? 1 : 0;
        $this->totalExecuteDurationUs += $executeDurationUs;
        $this->slowestExecuteDurationUs = max($this->slowestExecuteDurationUs, $executeDurationUs);
        $fingerprint = 'sha256:' . hash('sha256', $sql);

        if (isset($this->queries[$fingerprint])) {
            $query = $this->queries[$fingerprint];
            $query['executions']++;
            $query['failures'] += $failed ? 1 : 0;
            $query['total_execute_duration_us'] += $executeDurationUs;
            $query['max_execute_duration_us'] = max($query['max_execute_duration_us'], $executeDurationUs);
            $this->repeatedFingerprints += $query['executions'] === 2 ? 1 : 0;
            $this->maximumExecutions = max($this->maximumExecutions, $query['executions']);
            $this->queries[$fingerprint] = $query;
            return;
        }

        if (count($this->queries) >= $this->fingerprintLimit) {
            $this->untrackedStatements++;
            return;
        }

        $this->queries[$fingerprint] = [
            'fingerprint' => $fingerprint,
            'executions' => 1,
            'failures' => $failed ? 1 : 0,
            'total_execute_duration_us' => $executeDurationUs,
            'max_execute_duration_us' => $executeDurationUs,
        ];
        $this->maximumExecutions = max($this->maximumExecutions, 1);
    }

    /** @return QuerySnapshot */
    public function snapshot(): array
    {
        return [
            'schema_version' => 1,
            'event' => 'database.query_summary',
            'statements' => $this->statements,
            'failures' => $this->failures,
            'tracked_fingerprints' => count($this->queries),
            'repeated_fingerprints' => $this->repeatedFingerprints,
            'maximum_executions_per_fingerprint' => $this->maximumExecutions,
            'total_execute_duration_us' => $this->totalExecuteDurationUs,
            'slowest_execute_duration_us' => $this->slowestExecuteDurationUs,
            'truncated' => $this->untrackedStatements > 0,
            'untracked_statements' => $this->untrackedStatements,
            'queries' => array_values($this->queries),
        ];
    }
}
