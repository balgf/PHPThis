<?php

declare(strict_types=1);

const AGENT_EVALUATION_CONTROLLER_TEST_FAKE_CODEX = 'AGENT_EVALUATION_CONTROLLER_TEST_FAKE_CODEX';

$mode = $argv[1] ?? null;

if ($mode === 'process-output-limit') {
    if (count($argv) !== 2) {
        fwrite(STDERR, "FAIL fake process output limit: unexpected arguments.\n");
        exit(2);
    }

    fwrite(STDOUT, str_repeat('O', 4_096) . "\n");
    exit(0);
}

if ($mode === 'process-wall-limit') {
    if (count($argv) !== 2) {
        fwrite(STDERR, "FAIL fake process wall limit: unexpected arguments.\n");
        exit(2);
    }

    sleep(5);
    exit(0);
}

if ($mode === 'process-fail') {
    if (count($argv) !== 2) {
        fwrite(STDERR, "FAIL fake process failure: unexpected arguments.\n");
        exit(2);
    }

    fwrite(STDERR, "EXPECTED synthetic process failure\n");
    exit(42);
}

if ($mode === 'process-partial-jsonl') {
    if (count($argv) !== 2) {
        fwrite(STDERR, "FAIL fake partial JSONL: unexpected arguments.\n");
        exit(2);
    }

    fwrite(STDOUT, "{\"type\":\"thread.started\",\"thread_id\":\"partial\"}\n");
    fwrite(STDOUT, "{\"type\":\"turn.started\"}\n");
    exit(0);
}

if ($mode === 'process-descendant') {
    if (count($argv) !== 2) {
        fwrite(STDERR, "FAIL fake process descendant: unexpected arguments.\n");
        exit(2);
    }

    require_once dirname(__DIR__) . '/process.php';
    $child = agentEvaluationControllerSpawnSyntheticDescendant();

    if ($child < 0) {
        fwrite(STDERR, "FAIL fake process descendant: fork failed.\n");
        exit(2);
    }

    sleep(5);
    exit(0);
}

if ($mode === 'process-orphaned-descendant') {
    if (count($argv) !== 2) {
        fwrite(STDERR, "FAIL fake orphaned process descendant: unexpected arguments.\n");
        exit(2);
    }

    require_once dirname(__DIR__) . '/process.php';
    $child = agentEvaluationControllerSpawnSyntheticDescendant();

    if ($child < 0) {
        fwrite(STDERR, "FAIL fake orphaned process descendant: fork failed.\n");
        exit(2);
    }

    if ($child === 0) {
        fclose(STDIN);
        fclose(STDOUT);
        fclose(STDERR);
        sleep(5);
        exit(0);
    }

    exit(0);
}

if ($mode === 'score-application-check') {
    if (count($argv) !== 2) {
        fwrite(STDERR, "FAIL fake application check: unexpected arguments.\n");
        exit(2);
    }

    fwrite(STDOUT, "PASS synthetic composer check\n");
    exit(0);
}

if ($mode === 'score-public-scorer') {
    if (count($argv) !== 2) {
        fwrite(STDERR, "FAIL fake public scorer: unexpected arguments.\n");
        exit(2);
    }

    fwrite(STDOUT, "PASS synthetic public scorer\n");
    exit(0);
}

$expectedFixedArguments = [
    1 => 'exec',
    2 => '--ephemeral',
    3 => '--ignore-user-config',
    4 => '--ignore-rules',
    5 => '--strict-config',
    6 => '--skip-git-repo-check',
    7 => '--sandbox',
    8 => 'workspace-write',
    9 => '--json',
    10 => '--model',
    12 => '--cd',
    14 => '-c',
    15 => 'approval_policy="never"',
    16 => '-c',
    18 => '-c',
    19 => 'shell_environment_policy.inherit="none"',
    20 => '-',
];

if (count($argv) !== 21) {
    fwrite(STDERR, "FAIL fake codex: unexpected argument count.\n");
    exit(2);
}

foreach ($expectedFixedArguments as $index => $expected) {
    if ($argv[$index] !== $expected) {
        fwrite(STDERR, "FAIL fake codex: fixed argument {$index} changed.\n");
        exit(2);
    }
}

$model = $argv[11];
$candidateArgument = $argv[13];
$reasoningSetting = $argv[17];

if (
    preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._:\/-]{0,127}\z/D', $model) !== 1
    || preg_match('/\Amodel_reasoning_effort="(?:low|medium|high|xhigh|max|ultra)"\z/D', $reasoningSetting) !== 1
) {
    fwrite(STDERR, "FAIL fake codex: model or reasoning setting is invalid.\n");
    exit(2);
}

$candidateRoot = realpath($candidateArgument);
$workingDirectory = getcwd();

if (
    !is_string($candidateRoot)
    || !is_string($workingDirectory)
    || $candidateRoot !== $candidateArgument
    || $candidateRoot !== $workingDirectory
    || is_link($candidateRoot)
    || is_dir($candidateRoot . '/.git')
    || is_file($candidateRoot . '/.git')
) {
    fwrite(STDERR, "FAIL fake codex: candidate boundary is invalid.\n");
    exit(2);
}

$prompt = stream_get_contents(STDIN, 1_048_577);

if (!is_string($prompt) || $prompt === '' || strlen($prompt) > 1_048_576 || str_contains($prompt, "\0")) {
    fwrite(STDERR, "FAIL fake codex: prompt is invalid.\n");
    exit(2);
}

$healthRoutesPath = $candidateRoot . '/src/HealthRoutes.php';
$pingHandlerPath = $candidateRoot . '/src/PingHandler.php';
$testsPath = $candidateRoot . '/tests/run.php';

foreach ([$healthRoutesPath, $testsPath] as $existingPath) {
    $resolved = realpath($existingPath);
    $metadata = lstat($existingPath);

    if (
        !is_string($resolved)
        || !is_array($metadata)
        || $metadata['nlink'] !== 1
        || !str_starts_with($resolved, $candidateRoot . DIRECTORY_SEPARATOR)
        || !is_file($resolved)
        || is_link($existingPath)
    ) {
        fwrite(STDERR, "FAIL fake codex: candidate fixture path is invalid.\n");
        exit(2);
    }
}

if (is_link($pingHandlerPath) || (file_exists($pingHandlerPath) && !is_file($pingHandlerPath))) {
    fwrite(STDERR, "FAIL fake codex: ping handler path is invalid.\n");
    exit(2);
}

if (is_file($pingHandlerPath)) {
    $resolvedPingHandler = realpath($pingHandlerPath);
    $pingHandlerMetadata = lstat($pingHandlerPath);

    if (
        !is_string($resolvedPingHandler)
        || !is_array($pingHandlerMetadata)
        || $pingHandlerMetadata['nlink'] !== 1
        || !str_starts_with($resolvedPingHandler, $candidateRoot . DIRECTORY_SEPARATOR)
    ) {
        fwrite(STDERR, "FAIL fake codex: ping handler boundary is invalid.\n");
        exit(2);
    }
}

$healthRoutes = file_get_contents($healthRoutesPath);
$tests = file_get_contents($testsPath);

if (!is_string($healthRoutes) || !is_string($tests)) {
    fwrite(STDERR, "FAIL fake codex: candidate fixture is incomplete.\n");
    exit(2);
}

$generatedHealthRoutes = <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

use PHPThis\Routing\Route;

final class HealthRoutes
{
    /** @return list<Route> */
    public static function create(): array
    {
        return [
            new Route('GET', '/health', new HealthHandler()),
            new Route('GET', '/ping', new PingHandler()),
        ];
    }
}
PHP;
$generatedHealthRoutes .= "\n";

$generatedPingHandler = <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

use PHPThis\Http\Request;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\Response;

final class PingHandler implements RequestHandler
{
    public function handle(Request $request): Response
    {
        return new Response(
            status: 200,
            headers: [
                'Content-Type' => 'application/json; charset=utf-8',
                'Cache-Control' => 'no-store',
            ],
            body: "{\"status\":\"pong\"}\n",
        );
    }
}
PHP;
$generatedPingHandler .= "\n";

$testAnchor = <<<'PHP'
$expectSame("{\"status\":\"ok\"}\n", $health->body, 'GET /health must return the exact health body.');

$notAllowed = $application->handle(new Request('POST', '/health'));
PHP;
$generatedTestEvidence = <<<'PHP'
$expectSame("{\"status\":\"ok\"}\n", $health->body, 'GET /health must return the exact health body.');

$ping = $application->handle(new Request('GET', '/ping'));
$expectSame(200, $ping->status, 'GET /ping must return 200.');
$expectSame(
    [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'no-store',
    ],
    $ping->headers,
    'GET /ping must return the exact headers.',
);
$expectSame("{\"status\":\"pong\"}\n", $ping->body, 'GET /ping must return the exact body.');

$pingNotAllowed = $application->handle(new Request('POST', '/ping'));
$expectSame(405, $pingNotAllowed->status, 'POST /ping must return 405.');
$expectSame('GET', $pingNotAllowed->headers['Allow'] ?? null, 'POST /ping must advertise GET.');
$expectSame(
    'no-store',
    $pingNotAllowed->headers['Cache-Control'] ?? null,
    'POST /ping must return the routing-owned no-store policy.',
);

$notAllowed = $application->handle(new Request('POST', '/health'));
PHP;

if (!str_contains($tests, 'GET /ping must return the exact body.')) {
    if (substr_count($tests, $testAnchor) !== 1) {
        fwrite(STDERR, "FAIL fake codex: behavior-test anchor changed.\n");
        exit(2);
    }

    $tests = str_replace($testAnchor, $generatedTestEvidence, $tests);
}

if (
    file_put_contents($healthRoutesPath, $generatedHealthRoutes, LOCK_EX) !== strlen($generatedHealthRoutes)
    || file_put_contents($pingHandlerPath, $generatedPingHandler, LOCK_EX) !== strlen($generatedPingHandler)
    || file_put_contents($testsPath, $tests, LOCK_EX) !== strlen($tests)
    || !chmod($healthRoutesPath, 0644)
    || !chmod($pingHandlerPath, 0644)
    || !chmod($testsPath, 0644)
) {
    fwrite(STDERR, "FAIL fake codex: unable to write the synthetic candidate.\n");
    exit(2);
}

$events = [
    [
        'type' => 'thread.started',
        'thread_id' => '00000000-0000-7000-8000-000000000042',
    ],
    ['type' => 'turn.started'],
    [
        'type' => 'item.completed',
        'item' => [
            'id' => 'item_1',
            'type' => 'file_change',
            'status' => 'completed',
            'paths' => ['src/HealthRoutes.php', 'src/PingHandler.php', 'tests/run.php'],
        ],
    ],
    [
        'type' => 'item.completed',
        'item' => [
            'id' => 'item_2',
            'type' => 'agent_message',
            'text' => 'Added the dependency-free ping endpoint and its behavior evidence.',
        ],
    ],
    [
        'type' => 'turn.completed',
        'usage' => [
            'input_tokens' => 1_200,
            'cached_input_tokens' => 0,
            'output_tokens' => 300,
            'reasoning_output_tokens' => 200,
        ],
    ],
];

foreach ($events as $event) {
    fwrite(STDOUT, json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n");
}

fwrite(STDERR, "PASS deterministic fake Codex generation\n");
