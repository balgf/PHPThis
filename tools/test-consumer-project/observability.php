<?php

declare(strict_types=1);

function installedRequestSummaryDestinationRecordSource(string $installedFramework): string
{
    $path = $installedFramework . '/docs/observability/destination-record.md';
    $document = file_get_contents($path);

    if (!is_string($document)) {
        throw new RuntimeException('Unable to read the installed destination-record reference.');
    }

    $start = "<!-- phpthis-request-summary-destination-record-reference:start -->\n```php\n";
    $end = "\n```\n<!-- phpthis-request-summary-destination-record-reference:end -->";

    if (substr_count($document, $start) !== 1 || substr_count($document, $end) !== 1) {
        throw new RuntimeException('The installed destination-record reference must contain one exact checked block.');
    }

    $startOffset = strpos($document, $start);

    if (!is_int($startOffset)) {
        throw new RuntimeException('The installed destination-record reference start marker is unavailable.');
    }

    $sourceOffset = $startOffset + strlen($start);
    $endOffset = strpos($document, $end, $sourceOffset);

    if (!is_int($endOffset) || $endOffset <= $sourceOffset) {
        throw new RuntimeException('The installed destination-record reference end marker is unavailable.');
    }

    $source = substr($document, $sourceOffset, $endOffset - $sourceOffset) . "\n";

    if (
        !str_starts_with($source, "<?php\n\ndeclare(strict_types=1);\n")
        || !str_ends_with($source, "}\n")
    ) {
        throw new RuntimeException('The installed destination-record reference has invalid copied bytes.');
    }

    $tokens = token_get_all($source, TOKEN_PARSE);

    if ($tokens === []) {
        throw new RuntimeException('The installed destination-record reference has no PHP tokens.');
    }

    if (
        !hash_equals(
            '31c993b68bf5e18a0d7cbb74e8439721f62ffb1f53eb9e8fb6744b48ff587a9f',
            hash('sha256', $source),
        )
    ) {
        throw new RuntimeException(
            'The installed destination-record reference bytes do not match the reviewed encoder.',
        );
    }

    return $source;
}

function requestSummaryDestinationRecordV1ProofProgram(): string
{
    return <<<'PHP'
<?php

declare(strict_types=1);

use App\Observability\CorrelationId;
use App\Observability\QuerySummarySource;
use App\Observability\RequestSummary;
use App\Observability\RequestSummaryDestinationRecord;
use App\Observability\RequestSummarySink;
use App\Observability\TerminalRequestCoordinator;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryBudgetExceeded;
use PHPThis\Database\QueryTrace;
use PHPThis\Http\ErrorResponseRegistry;
use PHPThis\Http\Request;
use PHPThis\Http\RequestBoundary;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\RequestReader;
use PHPThis\Http\Response;
use PHPThis\Http\UnknownFailureBoundary;

require __DIR__ . '/vendor/autoload.php';

function destinationRecordProofAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array<string, mixed> */
function destinationRecordDecoded(string $line): array
{
    destinationRecordProofAssert(
        str_ends_with($line, "\n") && substr_count($line, "\n") === 1,
        'A destination record must contain exactly one final LF.',
    );
    $decoded = json_decode(substr($line, 0, -1), true, 64, JSON_THROW_ON_ERROR);

    destinationRecordProofAssert(is_array($decoded), 'A destination record must decode to one object.');

    return $decoded;
}

function destinationRecordLevel(RequestSummary $summary, DateTimeImmutable $occurredAt): string
{
    $decoded = destinationRecordDecoded(
        RequestSummaryDestinationRecord::line($summary, $occurredAt),
    );
    $level = $decoded['level'] ?? null;

    destinationRecordProofAssert(is_string($level), 'A destination record level must be a string.');

    return $level;
}

final readonly class DestinationRecordProofResponseHandler implements RequestHandler
{
    public function __construct(private Response $response)
    {
    }

    public function handle(Request $request): Response
    {
        return $this->response;
    }
}

final class DestinationRecordProofThrowingHandler implements RequestHandler
{
    public function handle(Request $request): Response
    {
        throw new RuntimeException('destination-record-coordinator-secret');
    }
}

final class DestinationRecordProofFailingSink implements RequestSummarySink
{
    public int $attempts = 0;

    public function emit(RequestSummary $summary): void
    {
        $this->attempts++;
        RequestSummaryDestinationRecord::line(
            $summary,
            new DateTimeImmutable('+10000-01-01T00:00:00.000000+00:00'),
        );
    }
}

function destinationRecordProofCoordinator(
    RequestHandler $handler,
    RequestSummarySink $sink,
): TerminalRequestCoordinator {
    return new TerminalRequestCoordinator(
        new RequestBoundary(
            new RequestReader(1_024, 'php://input'),
            $handler,
            new ErrorResponseRegistry([]),
        ),
        new UnknownFailureBoundary(),
        CorrelationId::generate(),
        $sink,
        [],
    );
}

$secret = 'destination-record-secret-sentinel-do-not-retain';
$occurredAt = new DateTimeImmutable('2026-08-12T08:15:30.123456+08:00');
$infoSummary = RequestSummary::capture(
    CorrelationId::generate(),
    42,
    new Response(200, [], $secret),
    null,
    [],
);
$infoPayload = $infoSummary->toArray();
$infoLine = RequestSummaryDestinationRecord::line($infoSummary, $occurredAt);
$infoRecord = destinationRecordDecoded($infoLine);

destinationRecordProofAssert(
    array_keys($infoRecord) === ['record_schema_version', 'occurred_at', 'level', 'summary'],
    'The destination-record outer key order changed.',
);
destinationRecordProofAssert(
    ($infoRecord['record_schema_version'] ?? null) === 1
    && ($infoRecord['occurred_at'] ?? null) === '2026-08-12T00:15:30.123456Z'
    && ($infoRecord['level'] ?? null) === 'info'
    && ($infoRecord['summary'] ?? null) === $infoPayload,
    'The version-1 destination record changed its exact envelope or summary.',
);
destinationRecordProofAssert(
    !str_contains($infoLine, $secret),
    'The destination record retained a response-body secret.',
);

$knownClientFailure = RequestSummary::capture(
    CorrelationId::generate(),
    1,
    new Response(404, [], ''),
    null,
    [],
);
destinationRecordProofAssert(
    destinationRecordLevel($knownClientFailure, $occurredAt) === 'info',
    'A handled client failure must map to info without query degradation.',
);

$failureBudget = new QueryBudget(2);
$failureBudget->recordStatement();
$failureTrace = new QueryTrace(2);
$failureTrace->recordStatement('SELECT :value', 7, true);
$failureSource = new QuerySummarySource('proof_failure', $failureBudget, $failureTrace);
$queryFailure = RequestSummary::capture(
    CorrelationId::generate(),
    1,
    new Response(200, [], ''),
    null,
    [$failureSource],
);
destinationRecordProofAssert(
    destinationRecordLevel($queryFailure, $occurredAt) === 'warning',
    'An observed query failure must map to warning.',
);

$budget = new QueryBudget(1);
$budget->recordStatement();
$budgetRejected = false;

try {
    $budget->recordStatement();
} catch (QueryBudgetExceeded) {
    $budgetRejected = true;
}

destinationRecordProofAssert($budgetRejected, 'The budget-warning fixture must reject one statement.');
$budgetTrace = new QueryTrace(1);
$budgetTrace->recordStatement('SELECT 1', 1, false);
$budgetSummary = RequestSummary::capture(
    CorrelationId::generate(),
    1,
    new Response(200, [], ''),
    null,
    [new QuerySummarySource('proof_budget', $budget, $budgetTrace)],
);
destinationRecordProofAssert(
    destinationRecordLevel($budgetSummary, $occurredAt) === 'warning',
    'A rejected query budget must map to warning.',
);

$serverFailure = RequestSummary::capture(
    CorrelationId::generate(),
    1,
    new Response(500, [], ''),
    null,
    [$failureSource],
);
destinationRecordProofAssert(
    destinationRecordLevel($serverFailure, $occurredAt) === 'error',
    'A status of at least 500 must take precedence over query warning.',
);

$unknownFailure = RequestSummary::capture(
    CorrelationId::generate(),
    1,
    new Response(200, [], $secret),
    new RuntimeException($secret),
    [$failureSource],
);
$unknownLine = RequestSummaryDestinationRecord::line($unknownFailure, $occurredAt);
destinationRecordProofAssert(
    (destinationRecordDecoded($unknownLine)['level'] ?? null) === 'error'
    && !str_contains($unknownLine, $secret),
    'Unknown failure must take precedence without retaining exception or response secrets.',
);

$observedHttpLevels = array_values(array_unique([
    destinationRecordLevel($infoSummary, $occurredAt),
    destinationRecordLevel($knownClientFailure, $occurredAt),
    destinationRecordLevel($queryFailure, $occurredAt),
    destinationRecordLevel($budgetSummary, $occurredAt),
    destinationRecordLevel($serverFailure, $occurredAt),
    destinationRecordLevel($unknownFailure, $occurredAt),
]));
sort($observedHttpLevels, SORT_STRING);
destinationRecordProofAssert(
    $observedHttpLevels === ['error', 'info', 'warning']
    && !in_array('debug', $observedHttpLevels, true)
    && !in_array('critical', $observedHttpLevels, true),
    'The HTTP mapper must emit exactly info, warning, and error, never debug or critical.',
);

$successSink = new DestinationRecordProofFailingSink();
$selectedBody = "{\"selected\":true}\n";
$selectedResponse = destinationRecordProofCoordinator(
    new DestinationRecordProofResponseHandler(
        new Response(202, ['Content-Type' => 'application/json; charset=utf-8'], $selectedBody),
    ),
    $successSink,
)->handle(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/proof'], []);
destinationRecordProofAssert(
    $selectedResponse->status === 202
    && $selectedResponse->body === $selectedBody
    && $successSink->attempts === 1,
    'Destination-record formatting failure must leave a selected success response unchanged after one attempt.',
);

$unknownSink = new DestinationRecordProofFailingSink();
$unknownResponse = destinationRecordProofCoordinator(
    new DestinationRecordProofThrowingHandler(),
    $unknownSink,
)->handle(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/proof'], []);
destinationRecordProofAssert(
    $unknownResponse->status === 500
    && $unknownResponse->body
        === "{\"error\":{\"code\":\"internal_server_error\",\"message\":\"Internal server error.\"}}\n"
    && $unknownSink->attempts === 1,
    'Destination-record formatting failure must leave an unknown response unchanged after one attempt.',
);

fwrite(STDOUT, "PASS installed request-summary destination-record version-1 proof\n");
PHP;
}

function requestSummaryDestinationRecordIsolatedSummarySource(): string
{
    return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Observability;

final class RequestSummary
{
    public int $toArrayCalls = 0;

    /** @param array<string, mixed> $payload */
    public function __construct(
        private array $payload,
        private string $privateSecret,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $this->toArrayCalls++;

        return $this->payload;
    }
}
PHP;
}

function requestSummaryDestinationRecordIsolatedProofProgram(): string
{
    return <<<'PHP'
<?php

declare(strict_types=1);

use App\Observability\RequestSummary;
use App\Observability\RequestSummaryDestinationRecord;

require __DIR__ . '/RequestSummary.php';
require __DIR__ . '/RequestSummaryDestinationRecord.php';

function isolatedDestinationRecordAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$secret = 'isolated-destination-record-secret-do-not-retain';
$payload = [
    'schema_version' => 2,
    'event' => 'application.request_summary',
    'correlation_id' => '0123456789abcdef0123456789abcdef',
    'duration_us' => 123,
    'response_status' => 200,
    'outcome' => 'success',
    'unknown_failure_class' => null,
    'query_count' => 0,
    'query_failures' => 0,
    'query_execute_duration_us' => 0,
    'query_budget_exceeded' => false,
    'database_sources' => [],
    'document_cache' => [
        'read' => 'hit',
        'write' => 'not_attempted',
        'invalidation' => 'not_attempted',
    ],
];
isolatedDestinationRecordAssert(
    array_keys($payload) === [
        'schema_version',
        'event',
        'correlation_id',
        'duration_us',
        'response_status',
        'outcome',
        'unknown_failure_class',
        'query_count',
        'query_failures',
        'query_execute_duration_us',
        'query_budget_exceeded',
        'database_sources',
        'document_cache',
    ],
    'The isolated version-2 fixture changed its exact closed field order.',
);
$summary = new RequestSummary($payload, $secret);
$line = RequestSummaryDestinationRecord::line(
    $summary,
    new DateTimeImmutable('2026-08-12T00:00:00.000000Z'),
);
$decoded = json_decode(substr($line, 0, -1), true, 64, JSON_THROW_ON_ERROR);

isolatedDestinationRecordAssert(
    is_array($decoded)
    && array_keys($decoded) === ['record_schema_version', 'occurred_at', 'level', 'summary']
    && ($decoded['record_schema_version'] ?? null) === 1
    && ($decoded['occurred_at'] ?? null) === '2026-08-12T00:00:00.000000Z'
    && ($decoded['level'] ?? null) === 'info'
    && ($decoded['summary'] ?? null) === $payload
    && $summary->toArrayCalls === 1
    && str_ends_with($line, "\n")
    && substr_count($line, "\n") === 1
    && !str_contains($line, $secret),
    'The exact encoder must preserve one unchanged version-2 summary without its private secret.',
);

// Synthetic hard-cap fixture: proof_padding is deliberately outside the closed
// version-2 schema. This proves encoder enforcement only; an adopter separately
// calculates and proves that its exact worst-case valid record fits this cap.
$boundaryPayload = $payload;
$boundaryPayload['proof_padding'] = '';
$emptyBoundaryLine = RequestSummaryDestinationRecord::line(
    new RequestSummary($boundaryPayload, $secret),
    new DateTimeImmutable('2026-08-12T00:00:00.000000Z'),
);
$paddingBytes = 65_536 - strlen($emptyBoundaryLine);
isolatedDestinationRecordAssert($paddingBytes > 0, 'The size-bound fixture has no padding capacity.');
$boundaryPayload['proof_padding'] = str_repeat('p', $paddingBytes);
$maximumLine = RequestSummaryDestinationRecord::line(
    new RequestSummary($boundaryPayload, $secret),
    new DateTimeImmutable('2026-08-12T00:00:00.000000Z'),
);
isolatedDestinationRecordAssert(
    strlen($maximumLine) === 65_536
    && str_ends_with($maximumLine, "\n")
    && substr_count($maximumLine, "\n") === 1,
    'The encoder must accept exactly 65536 bytes including one LF.',
);

$boundaryPayload['proof_padding'] .= 'p';
$oversizeFailure = null;

try {
    RequestSummaryDestinationRecord::line(
        new RequestSummary($boundaryPayload, $secret),
        new DateTimeImmutable('2026-08-12T00:00:00.000000Z'),
    );
} catch (RuntimeException $failure) {
    $oversizeFailure = $failure->getMessage();
}

isolatedDestinationRecordAssert(
    $oversizeFailure === 'Request-summary destination record exceeds 65536 bytes.'
    && !str_contains((string) $oversizeFailure, $secret),
    'The encoder must reject 65537 bytes with one fixed redacted failure.',
);

$invalidUtf8Payload = $payload;
$invalidUtf8Payload['unknown_failure_class'] = "\xB1" . $secret;
$encodingFailure = null;

try {
    RequestSummaryDestinationRecord::line(
        new RequestSummary($invalidUtf8Payload, $secret),
        new DateTimeImmutable('2026-08-12T00:00:00.000000Z'),
    );
} catch (RuntimeException $failure) {
    $encodingFailure = $failure->getMessage();
}

isolatedDestinationRecordAssert(
    $encodingFailure === 'Unable to encode the request-summary destination record.'
    && !str_contains((string) $encodingFailure, $secret),
    'Invalid UTF-8 must produce one fixed redacted encoding failure.',
);

$timestampFailure = null;

try {
    RequestSummaryDestinationRecord::line(
        new RequestSummary($payload, $secret),
        new DateTimeImmutable('+10000-01-01T00:00:00.000000+00:00'),
    );
} catch (RuntimeException $failure) {
    $timestampFailure = $failure->getMessage();
}

isolatedDestinationRecordAssert(
    $timestampFailure === 'Unable to format the request-summary destination-record timestamp.'
    && !str_contains((string) $timestampFailure, $secret),
    'A non-four-digit year must produce one fixed redacted formatting failure.',
);

fwrite(STDOUT, "PASS installed request-summary destination-record isolated boundary proof\n");
PHP;
}

/**
 * @param array<string, string> $environment
 * @return non-empty-string
 */
function proveInstalledRequestSummaryDestinationRecordReference(
    string $project,
    string $installedFramework,
    array $environment,
): string {
    /** @var array<string, list<string>> $artifactMarkers */
    $artifactMarkers = [
        $installedFramework . '/docs/observability/destination-record.md' => [
            '# Application-owned request-summary destination-record reference',
            '[ADR 051](../decisions/051-application-owned-structured-log-destinations.md) accepts this optional application-owned reference.',
            'final class RequestSummaryDestinationRecord',
            'The installed proof executes these exact copied bytes',
            'It deliberately performs no file or stream write',
        ],
        $installedFramework . '/docs/observability/destination-profiles.md' => [
            '[checked destination-record reference](destination-record.md)',
            'certifies none of the stream, file, collector, or delivery facts below',
            '<project-root>/var/log/application.jsonl',
            'the exact project-root `.gitignore` entry `/var/log/`',
            '/var/log/<application>/application.jsonl',
            'for a container, prefer the selected stdout or stderr profile above rather than a file on its ephemeral writable layer',
            'Only an application that adopts this local-file profile adds the directory and ignore rule.',
            'The application runtime and HTTP request path never create or repair the log directory or call `mkdir`, `touch`, `chmod`, or `chown` for the destination.',
            'POSIX mode `0750` for the directory and `0640` for the file is the recommended least-privilege starting point.',
            'tail -F var/log/application.jsonl',
            'it proves neither that an application writer reopened its descriptor nor that a collector delivered the record.',
            'A finite static process role such as `web`, `worker`, or `scheduler` may become a label only after measured query frequency, volume, and cardinality evidence proves it is a bounded deployment dimension.',
            'A process identifier, PID, replica identifier, or another dynamic process value never becomes a label.',
            'Record the selected Loki or Grafana Cloud tenant or account through a stable non-secret reference',
        ],
        $installedFramework . '/docs/observability/README.md' => [
            '[Destination-record reference](destination-record.md)',
            'do not mistake the encoder proof for destination-I/O certification',
        ],
        $installedFramework . '/templates/application/.ai/observability.md' => [
            'ADR 051 accepts this optional application-owned profile.',
            '`NOT_APPLICABLE(OPERATIONAL_LOG_RECORD)`',
            '65,536-byte LF-inclusive enforcement path',
            '<project-root>/var/log/application.jsonl',
            '/var/log/<application>/application.jsonl',
            'For a container, prefer the selected stdout or stderr profile over a file on its ephemeral writable layer.',
            'POSIX directory mode `0750` and file mode `0640` are recommended only when one writer identity and one collector group fit the recorded topology',
        ],
        $project . '/.ai/observability.md' => [
            '`NOT_APPLICABLE(OPERATIONAL_LOG_RECORD)`',
            "ADR 051's accepted optional profile is not adopted or implemented by this starter.",
            'The skeleton creates, reserves, and ignores no log directory.',
            'An adopter that selects the recommended local `var/log` path adds the exact project-root `/var/log/` ignore at that time.',
        ],
    ];
    requireInstalledArtifactMarkers($artifactMarkers, 'request-summary destination-record reference');
    requireInstalledNativeRuntimeDependencyBoundary($project, $installedFramework);

    $projectGitIgnorePath = $project . '/.gitignore';
    $localLogDirectory = $project . '/var/log';

    if (!is_file($projectGitIgnorePath) || is_link($projectGitIgnorePath)) {
        throw new RuntimeException('Installed skeleton .gitignore must be one regular non-symlink file.');
    }

    $projectGitIgnore = file_get_contents($projectGitIgnorePath);

    if (
        !is_string($projectGitIgnore)
        || in_array('/var/log/', explode("\n", $projectGitIgnore), true)
    ) {
        throw new RuntimeException(
            'Installed non-adopting skeleton must not pre-ignore the optional project-root /var/log/ destination.',
        );
    }

    if (file_exists($localLogDirectory) || is_link($localLogDirectory)) {
        throw new RuntimeException(
            'Installed skeleton must not create or adopt the optional var/log destination.',
        );
    }

    $referenceSource = installedRequestSummaryDestinationRecordSource($installedFramework);
    $referencePath = $project . '/src/Observability/RequestSummaryDestinationRecord.php';
    $v1ProofPath = $project . '/installed-request-summary-destination-record-proof.php';
    $isolatedProofDirectory = $project . '/installed-request-summary-destination-record-isolated-proof';
    $mutationProofDirectory = $project . '/installed-request-summary-destination-record-mutation-proof';

    foreach (
        [$referencePath, $v1ProofPath, $isolatedProofDirectory, $mutationProofDirectory]
        as $proofPath
    ) {
        if (file_exists($proofPath) || is_link($proofPath)) {
            throw new RuntimeException('A destination-record proof path already exists in the consumer.');
        }
    }

    writeFile($referencePath, $referenceSource);

    try {
        $lintResult = runProcess([PHP_BINARY, '-l', $referencePath], $project, $environment);
        requireExactProcessResult(
            $lintResult,
            0,
            "No syntax errors detected in {$referencePath}\n",
            '',
            'The exact installed destination-record reference did not pass PHP syntax checking.',
        );

        $profileResult = runProcess(
            [$project . '/vendor/bin/phpthis', 'check'],
            $project,
            $environment,
        );
        requireSuccess(
            $profileResult,
            'The exact installed destination-record reference failed the maximum consumer profile.',
        );
        requireOutputContains($profileResult, 'PASS PHPThis application check');

        $mutatedReferenceSource = str_replace(
            'private const int MAXIMUM_RECORD_BYTES = 65_536;',
            'private const int MAXIMUM_RECORD_BYTES = 65_535;',
            $referenceSource,
        );

        if ($mutatedReferenceSource === $referenceSource) {
            throw new RuntimeException('Unable to prepare the destination-record mutation control.');
        }

        writeFile(
            $mutationProofDirectory . '/docs/observability/destination-record.md',
            "<!-- phpthis-request-summary-destination-record-reference:start -->\n"
                . "```php\n"
                . rtrim($mutatedReferenceSource, "\n")
                . "\n```\n"
                . "<!-- phpthis-request-summary-destination-record-reference:end -->\n",
        );
        $mutationFailure = null;

        try {
            installedRequestSummaryDestinationRecordSource($mutationProofDirectory);
        } catch (RuntimeException $failure) {
            $mutationFailure = $failure->getMessage();
        }

        if (
            $mutationFailure
                !== 'The installed destination-record reference bytes do not match the reviewed encoder.'
        ) {
            throw new RuntimeException(
                'The destination-record source-hash mutation control did not fail closed.',
            );
        }

        removeDirectory($mutationProofDirectory);

        writeFile($v1ProofPath, requestSummaryDestinationRecordV1ProofProgram());
        $v1Result = runProcess(
            [PHP_BINARY, '-d', 'display_errors=1', '-d', 'error_reporting=-1', $v1ProofPath],
            $project,
            $environment,
        );
        requireExactProcessResult(
            $v1Result,
            0,
            "PASS installed request-summary destination-record version-1 proof\n",
            '',
            'The installed destination-record version-1 proof changed behavior or emitted diagnostics.',
        );

        writeFile(
            $isolatedProofDirectory . '/RequestSummary.php',
            requestSummaryDestinationRecordIsolatedSummarySource(),
        );
        writeFile(
            $isolatedProofDirectory . '/RequestSummaryDestinationRecord.php',
            $referenceSource,
        );
        writeFile(
            $isolatedProofDirectory . '/proof.php',
            requestSummaryDestinationRecordIsolatedProofProgram(),
        );
        $isolatedResult = runProcess(
            [
                PHP_BINARY,
                '-d',
                'display_errors=1',
                '-d',
                'error_reporting=-1',
                $isolatedProofDirectory . '/proof.php',
            ],
            $isolatedProofDirectory,
            $environment,
        );
        requireExactProcessResult(
            $isolatedResult,
            0,
            "PASS installed request-summary destination-record isolated boundary proof\n",
            '',
            'The installed destination-record isolated boundary proof changed behavior or emitted diagnostics.',
        );
    } finally {
        if (is_file($v1ProofPath) && !unlink($v1ProofPath)) {
            throw new RuntimeException('Unable to remove the destination-record version-1 proof.');
        }

        removeDirectory($isolatedProofDirectory);
        removeDirectory($mutationProofDirectory);

        if (is_file($referencePath) && !unlink($referencePath)) {
            throw new RuntimeException('Unable to remove the copied destination-record reference.');
        }

        if (
            file_exists($referencePath)
            || file_exists($v1ProofPath)
            || file_exists($isolatedProofDirectory)
            || file_exists($mutationProofDirectory)
            || file_exists($localLogDirectory)
            || is_link($referencePath)
            || is_link($v1ProofPath)
            || is_link($isolatedProofDirectory)
            || is_link($mutationProofDirectory)
            || is_link($localLogDirectory)
        ) {
            throw new RuntimeException(
                'Destination-record proof cleanup did not restore the consumer or created a var/log destination.',
            );
        }
    }

    fwrite(STDOUT, "PASS installed request-summary destination-record reference\n");

    return 'installed-request-summary-destination-record-reference-proved';
}
