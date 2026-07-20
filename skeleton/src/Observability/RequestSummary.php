<?php

declare(strict_types=1);

namespace App\Observability;

use InvalidArgumentException;
use PHPThis\Http\Response;
use Throwable;

/**
 * @phpstan-import-type QuerySourceSnapshot from QuerySummarySource
 * @phpstan-type RequestSummaryPayload array{
 *     schema_version: 1,
 *     event: 'application.request_summary',
 *     correlation_id: string,
 *     duration_us: int,
 *     response_status: int,
 *     outcome: 'success'|'known_failure'|'unknown_failure',
 *     unknown_failure_class: class-string<Throwable>|null,
 *     query_count: int,
 *     query_failures: int,
 *     query_execute_duration_us: int,
 *     query_budget_exceeded: bool,
 *     database_sources: list<QuerySourceSnapshot>
 * }
 */
final readonly class RequestSummary
{
    public const int SCHEMA_VERSION = 1;
    public const string EVENT = 'application.request_summary';

    /**
     * @param class-string<Throwable>|null $unknownFailureClass
     * @param 'success'|'known_failure'|'unknown_failure' $outcome
     * @param list<QuerySourceSnapshot> $querySources
     */
    private function __construct(
        public CorrelationId $correlationId,
        public int $durationUs,
        public int $responseStatus,
        public string $outcome,
        public ?string $unknownFailureClass,
        public int $queryStatements,
        public int $queryFailures,
        public int $queryExecuteDurationUs,
        public bool $queryBudgetExceeded,
        public array $querySources,
    ) {
        if ($durationUs < 0) {
            throw new InvalidArgumentException('Request-summary duration cannot be negative.');
        }
    }

    /** @param list<QuerySummarySource> $querySources */
    public static function capture(
        CorrelationId $correlationId,
        int $durationUs,
        Response $response,
        ?Throwable $unknownFailure,
        array $querySources,
    ): self {
        $snapshots = [];
        $statements = 0;
        $failures = 0;
        $executeDurationUs = 0;
        $budgetExceeded = false;

        foreach ($querySources as $querySource) {
            $snapshot = $querySource->snapshot();
            $snapshots[] = $snapshot;
            $statements = self::saturatedAdd($statements, $snapshot['query_trace']['statements']);
            $failures = self::saturatedAdd($failures, $snapshot['query_trace']['failures']);
            $executeDurationUs = self::saturatedAdd(
                $executeDurationUs,
                $snapshot['query_trace']['total_execute_duration_us'],
            );
            $budgetExceeded = $budgetExceeded || $snapshot['budget_exceeded'];
        }

        $outcome = match (true) {
            $unknownFailure !== null => 'unknown_failure',
            $response->status < 400 => 'success',
            default => 'known_failure',
        };

        return new self(
            $correlationId,
            $durationUs,
            $response->status,
            $outcome,
            $unknownFailure === null ? null : self::safeFailureClass($unknownFailure),
            $statements,
            $failures,
            $executeDurationUs,
            $budgetExceeded,
            $snapshots,
        );
    }

    /** @return RequestSummaryPayload */
    public function toArray(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'event' => self::EVENT,
            'correlation_id' => $this->correlationId->value,
            'duration_us' => $this->durationUs,
            'response_status' => $this->responseStatus,
            'outcome' => $this->outcome,
            'unknown_failure_class' => $this->unknownFailureClass,
            'query_count' => $this->queryStatements,
            'query_failures' => $this->queryFailures,
            'query_execute_duration_us' => $this->queryExecuteDurationUs,
            'query_budget_exceeded' => $this->queryBudgetExceeded,
            'database_sources' => $this->querySources,
        ];
    }

    private static function saturatedAdd(int $left, int $right): int
    {
        if ($right > PHP_INT_MAX - $left) {
            return PHP_INT_MAX;
        }

        return $left + $right;
    }

    /** @return class-string<Throwable> */
    private static function safeFailureClass(Throwable $failure): string
    {
        $class = $failure::class;

        if (!str_contains($class, '@anonymous')) {
            return $class;
        }

        $parent = get_parent_class($failure);

        if (is_string($parent) && is_a($parent, Throwable::class, true)) {
            return $parent;
        }

        return Throwable::class;
    }
}
