<?php

declare(strict_types=1);

namespace App\Observability;

use InvalidArgumentException;
use PHPThis\Http\RequestBoundary;
use PHPThis\Http\Response;
use PHPThis\Http\UnknownFailureBoundary;
use Throwable;

final readonly class TerminalRequestCoordinator
{
    private const int MAXIMUM_QUERY_SOURCES = 8;

    /** @var list<QuerySummarySource> */
    private array $querySources;

    /** @param list<QuerySummarySource> $querySources */
    public function __construct(
        private RequestBoundary $requestBoundary,
        private UnknownFailureBoundary $unknownFailures,
        private CorrelationId $correlationId,
        private RequestSummarySink $summarySink,
        array $querySources,
    ) {
        if (count($querySources) > self::MAXIMUM_QUERY_SOURCES) {
            throw new InvalidArgumentException('At most eight query-summary sources are supported.');
        }

        $sourceNames = [];
        $acceptedSources = [];

        foreach ($querySources as $querySource) {
            if (isset($sourceNames[$querySource->name])) {
                throw new InvalidArgumentException('Query-summary source names must be unique.');
            }

            $sourceNames[$querySource->name] = true;

            foreach ($acceptedSources as $acceptedSource) {
                if ($querySource->sharesObservationStateWith($acceptedSource)) {
                    throw new InvalidArgumentException(
                        'Query-summary sources must use distinct budgets and traces.',
                    );
                }
            }

            $acceptedSources[] = $querySource;
        }

        $this->querySources = $querySources;
    }

    /**
     * @param array<array-key, mixed> $server
     * @param array<array-key, mixed> $query
     * @param array<array-key, mixed> $parsedFields
     * @param array<array-key, mixed> $files
     */
    public function handle(
        array $server,
        array $query,
        array $parsedFields = [],
        array $files = [],
    ): Response
    {
        $startedAt = (float) hrtime(true);
        $unknownFailure = null;

        try {
            $response = $this->requestBoundary->handle($server, $query, $parsedFields, $files);
        } catch (Throwable $failure) {
            $unknownFailure = $failure;
            $response = $this->unknownFailures->respond();
        }

        $response = $this->withCorrelationId($response);
        $durationUs = max(0, (int) round(((float) hrtime(true) - $startedAt) / 1_000));
        $summary = RequestSummary::capture(
            $this->correlationId,
            $durationUs,
            $response,
            $unknownFailure,
            $this->querySources,
        );

        try {
            $this->summarySink->emit($summary);
        } catch (Throwable) {
            // The single synchronous attempt is isolated from the already-final response.
        }

        return $response;
    }

    private function withCorrelationId(Response $response): Response
    {
        $headers = [];

        foreach ($response->headers as $name => $value) {
            if (strtolower($name) !== 'x-request-id') {
                $headers[$name] = $value;
            }
        }

        $headers['X-Request-ID'] = $this->correlationId->value;

        return new Response(
            $response->status,
            $headers,
            $response->body,
            $response->cookies,
            $response->fileBody,
        );
    }
}
