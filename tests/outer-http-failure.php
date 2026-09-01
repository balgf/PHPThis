<?php

declare(strict_types=1);

use Example\Http\DevelopmentDiagnosticFailure;
use Example\Http\DevelopmentFailureResponse;
use Example\InvalidApplicationDatabasePath;
use PHPThis\Http\InvalidRequest;
use PHPThis\Http\UnknownFailureBoundary;
use PHPThis\Verification\SyntaxProfile;

require_once dirname(__DIR__) . '/verification/SyntaxProfile.php';

/**
 * @return Generator<string, Closure(): void, mixed, void>
 */
function outerHttpFailureBehaviorTests(): Generator
{
    yield 'development failure response renders exact bounded safe diagnostics' => static function (): void {
        $failure = new DevelopmentDiagnosticFailure();
        setOuterHttpFailureProperty($failure, 'file', '/application/src/Feature.php');
        setOuterHttpFailureProperty($failure, 'line', 17);
        setOuterHttpFailureProperty($failure, 'trace', [
            [
                'file' => '/application/src/Caller.php',
                'line' => 9,
                'class' => 'Example\\Caller',
                'type' => '->',
                'function' => 'call',
                'args' => ['trace-argument-private-sentinel'],
            ],
            [
                'file' => ['wrong native type'],
                'line' => '10',
                'class' => 42,
                'type' => null,
                'function' => 'typedFunction',
                'args' => ['second-trace-argument-private-sentinel'],
            ],
        ]);

        $response = (new DevelopmentFailureResponse())->respond($failure);
        $expectedBody = <<<'TEXT'
PHPThis development failure
exception[0].class="Example\\Http\\DevelopmentDiagnosticFailure"
exception[0].message="Development diagnostic failure."
exception[0].file="/application/src/Feature.php"
exception[0].line=17
exception[0].frame[0]
exception[0].frame[0].file="/application/src/Caller.php"
exception[0].frame[0].line=9
exception[0].frame[0].class="Example\\Caller"
exception[0].frame[0].type="->"
exception[0].frame[0].function="call"
exception[0].frame[1]
exception[0].frame[1].function="typedFunction"
TEXT;
        $expectedBody .= "\n";

        if (
            $response->status !== 500
            || $response->headers !== [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]
            || $response->body !== $expectedBody
            || str_contains($response->body, 'trace-argument-private-sentinel')
            || str_contains($response->body, 'second-trace-argument-private-sentinel')
        ) {
            throw new RuntimeException('The development failure response grammar changed.');
        }

        $literalOmissionMessage = new DevelopmentDiagnosticFailure();
        setOuterHttpFailureProperty($literalOmissionMessage, 'message', '<omitted>');
        setOuterHttpFailureProperty($literalOmissionMessage, 'trace', []);
        $literalBody = (new DevelopmentFailureResponse())->respond($literalOmissionMessage)->body;

        if (
            !str_contains($literalBody, "exception[0].message=\"<omitted>\"\n")
            || str_contains($literalBody, "exception[0].message=<omitted>\n")
        ) {
            throw new RuntimeException('An allowlisted literal omission marker must remain encoded data.');
        }

        $anonymousFailure = new class('anonymous-message-private-sentinel') extends RuntimeException {
        };
        setOuterHttpFailureProperty($anonymousFailure, 'trace', []);
        $anonymousBody = (new DevelopmentFailureResponse())->respond($anonymousFailure)->body;

        if (
            !str_contains($anonymousBody, "exception[0].class=\"RuntimeException\"\n")
            || str_contains($anonymousBody, '@anonymous')
            || str_contains($anonymousBody, 'anonymous-message-private-sentinel')
        ) {
            throw new RuntimeException('Anonymous diagnostics must use the named Throwable parent class.');
        }
    };

    yield 'development failure response omits every unapproved message and trace argument' => static function (): void {
        $cases = [
            new RuntimeException('native-message-private-sentinel'),
            new PDOException('pdo-message-private-sentinel SQLSTATE[HY000]'),
            new InvalidRequest('dependency-message-private-sentinel'),
            new InvalidApplicationDatabasePath('application-message-private-sentinel'),
        ];

        foreach ($cases as $index => $failure) {
            $traceArgument = 'trace-argument-private-sentinel-' . $index;
            setOuterHttpFailureProperty($failure, 'trace', [[
                'function' => 'testFunction',
                'args' => [$traceArgument, ['binding' => 'credential-private-sentinel']],
            ]]);
            $body = (new DevelopmentFailureResponse())->respond($failure)->body;

            if (
                substr_count($body, "exception[0].message=<omitted>\n") !== 1
                || str_contains($body, $failure->getMessage())
                || str_contains($body, $traceArgument)
                || str_contains($body, 'credential-private-sentinel')
            ) {
                throw new RuntimeException('An unapproved diagnostic value entered the response.');
            }
        }

        $fixturePath = __DIR__ . '/fixtures/development-failure-exact-class.php.fixture';
        $fixture = file_get_contents($fixturePath);

        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read the exact-class negative-control fixture.');
        }

        $profileFailures = SyntaxProfile::failures(
            $fixture,
            'tests/fixtures/development-failure-exact-class.php.fixture',
        );

        if ($profileFailures !== [
            'PHT002 tests/fixtures/development-failure-exact-class.php.fixture:6 named class '
                . 'TestOwnedAllowlistedFailure must be final.',
        ]) {
            throw new RuntimeException('The subclass negative control must remain deliberately non-profile.');
        }

        $result = runIsolatedPhpTest($fixturePath);

        if (
            $result['exit_code'] !== 0
            || $result['stdout'] !== "exact-class-negative-control=verified\n"
            || $result['stderr'] !== ''
        ) {
            throw new RuntimeException('The exact-class safe-message negative control failed.');
        }
    };

    yield 'development failure response enforces chain frame string and body limits' => static function (): void {
        $previous = null;

        for ($chainIndex = 4; $chainIndex >= 0; $chainIndex--) {
            $previous = new RuntimeException('chain-private-sentinel-' . $chainIndex, 0, $previous);
            setOuterHttpFailureProperty($previous, 'trace', []);
        }

        if (!$previous instanceof RuntimeException) {
            throw new RuntimeException('Unable to create the bounded failure chain.');
        }

        $chainBody = (new DevelopmentFailureResponse())->respond($previous)->body;

        if (
            substr_count($chainBody, '.class=') !== 4
            || substr_count($chainBody, "[diagnostic truncated]\n") !== 1
            || str_contains($chainBody, 'chain-private-sentinel')
        ) {
            throw new RuntimeException('The diagnostic exception-chain limit changed.');
        }

        $innerFrameFailure = new RuntimeException('inner-frame-private-sentinel');
        $innerFrames = [];

        for ($frameIndex = 0; $frameIndex < 20; $frameIndex++) {
            $innerFrames[] = ['function' => 'innerFrame' . $frameIndex];
        }

        setOuterHttpFailureProperty($innerFrameFailure, 'trace', $innerFrames);
        $outerFrameFailure = new RuntimeException(
            'outer-frame-private-sentinel',
            0,
            $innerFrameFailure,
        );
        $outerFrames = [];

        for ($frameIndex = 0; $frameIndex < 20; $frameIndex++) {
            $outerFrames[] = ['function' => 'outerFrame' . $frameIndex];
        }

        setOuterHttpFailureProperty($outerFrameFailure, 'trace', $outerFrames);
        $frameBody = (new DevelopmentFailureResponse())->respond($outerFrameFailure)->body;

        if (
            preg_match_all('/^exception\[[01]\]\.frame\[[0-9]+\]$/m', $frameBody) !== 32
            || preg_match_all('/^exception\[0\]\.frame\[[0-9]+\]$/m', $frameBody) !== 20
            || preg_match_all('/^exception\[1\]\.frame\[[0-9]+\]$/m', $frameBody) !== 12
            || !str_contains($frameBody, 'exception[1].frame[11].function="innerFrame11"')
            || str_contains($frameBody, 'exception[1].frame[12]')
            || str_contains($frameBody, 'innerFrame12')
            || substr_count($frameBody, "[diagnostic truncated]\n") !== 1
        ) {
            throw new RuntimeException('The shared diagnostic frame limit changed.');
        }

        $stringFailure = new RuntimeException('string-private-sentinel');
        setOuterHttpFailureProperty($stringFailure, 'file', str_repeat('x', 4_097));
        setOuterHttpFailureProperty($stringFailure, 'trace', []);
        $stringBody = (new DevelopmentFailureResponse())->respond($stringFailure)->body;

        if (
            !str_contains(
                $stringBody,
                'exception[0].file="' . str_repeat('x', 4_096) . "\"\n[diagnostic truncated]\n",
            )
            || str_contains($stringBody, "exception[0].line=")
            || substr_count($stringBody, "[diagnostic truncated]\n") !== 1
        ) {
            throw new RuntimeException('The diagnostic string-prefix limit changed.');
        }

        $bodyFailure = new RuntimeException('body-private-sentinel');
        $largeFrames = [];

        for ($largeFrameIndex = 0; $largeFrameIndex < 32; $largeFrameIndex++) {
            $largeFrames[] = [
                'file' => str_repeat(chr(65 + ($largeFrameIndex % 26)), 4_096),
                'line' => $largeFrameIndex + 1,
                'class' => str_repeat('C', 4_096),
                'type' => '::',
                'function' => str_repeat('f', 4_096),
            ];
        }

        setOuterHttpFailureProperty($bodyFailure, 'trace', $largeFrames);
        $boundedBody = (new DevelopmentFailureResponse())->respond($bodyFailure)->body;

        if (
            strlen($boundedBody) > 65_536
            || !str_ends_with($boundedBody, "[diagnostic truncated]\n")
            || substr_count($boundedBody, "[diagnostic truncated]\n") !== 1
        ) {
            throw new RuntimeException('The diagnostic byte limit or truncation marker changed.');
        }

        $oversizedNoFit = new RuntimeException('oversized-no-fit-private-sentinel');
        setOuterHttpFailureProperty($oversizedNoFit, 'file', str_repeat('z', 4_097));
        setOuterHttpFailureProperty($oversizedNoFit, 'trace', []);
        $oversizedNoFitBody = (new DevelopmentFailureResponse())->respond(
            nearBudgetOuterHttpFailure($oversizedNoFit),
        )->body;

        if (
            !str_contains($oversizedNoFitBody, 'exception[1].class="RuntimeException"')
            || str_contains($oversizedNoFitBody, 'exception[1].file=')
            || str_contains($oversizedNoFitBody, str_repeat('z', 128))
            || substr_count($oversizedNoFitBody, "[diagnostic truncated]\n") !== 1
            || strlen($oversizedNoFitBody) > 65_536
        ) {
            throw new RuntimeException('A non-fitting oversized string prefix must be omitted as one line.');
        }

        $ordinaryNoFit = new RuntimeException('ordinary-no-fit-private-sentinel');
        setOuterHttpFailureProperty($ordinaryNoFit, 'file', str_repeat('w', 4_096));
        setOuterHttpFailureProperty($ordinaryNoFit, 'trace', []);
        $ordinaryNoFitBody = (new DevelopmentFailureResponse())->respond(
            nearBudgetOuterHttpFailure($ordinaryNoFit),
        )->body;

        if (
            !str_contains($ordinaryNoFitBody, 'exception[1].class="RuntimeException"')
            || str_contains($ordinaryNoFitBody, 'exception[1].file=')
            || str_contains($ordinaryNoFitBody, str_repeat('w', 128))
            || substr_count($ordinaryNoFitBody, "[diagnostic truncated]\n") !== 1
            || strlen($ordinaryNoFitBody) > 65_536
        ) {
            throw new RuntimeException('A non-fitting ordinary encoded line must be omitted whole.');
        }

        $invalidUtf8 = new RuntimeException('utf8-private-sentinel');
        setOuterHttpFailureProperty($invalidUtf8, 'file', "invalid-\xFF-path");
        setOuterHttpFailureProperty($invalidUtf8, 'trace', []);
        $utf8Body = (new DevelopmentFailureResponse())->respond($invalidUtf8)->body;

        if (!str_contains($utf8Body, 'exception[0].file="invalid-\\ufffd-path"')) {
            throw new RuntimeException('Invalid diagnostic UTF-8 must use deterministic substitution.');
        }
    };

    yield 'outer failure selection isolates event renderer and emission failures' => static function (): void {
        proveOuterFailureAttemptIsolation();
        proveExampleResponseEmissionFailureBranches();
    };

    yield 'configured disclosure consumer enables details only after eligible complete selection' => static function (): void {
        proveConfiguredDisclosureDetails();
    };

    yield 'configured disclosure consumer fails closed for invalid selection and request overrides' => static function (): void {
        proveConfiguredDisclosureFailures();
    };

    yield 'example outer HTTP boundary catches bootstrap failures under safe SAPI settings' => static function (): void {
        proveExampleOuterHttpFailureBoundary();
    };
}

function setOuterHttpFailureProperty(Throwable $failure, string $property, mixed $value): void
{
    (new ReflectionProperty(Exception::class, $property))->setValue($failure, $value);
}

function nearBudgetOuterHttpFailure(Throwable $previous): RuntimeException
{
    $failure = new RuntimeException('near-budget-private-sentinel', 0, $previous);
    $frames = [];

    for ($frameIndex = 0; $frameIndex < 5; $frameIndex++) {
        $frames[] = [
            'file' => str_repeat(chr(65 + $frameIndex), 4_096),
            'class' => str_repeat(chr(70 + $frameIndex), 4_096),
            'function' => str_repeat(chr(75 + $frameIndex), 4_096),
        ];
    }

    setOuterHttpFailureProperty($failure, 'trace', $frames);

    return $failure;
}

function proveOuterFailureAttemptIsolation(): void
{
    [$eventTemporary, $eventServerRoot] = createConfiguredDisclosureServerTree(
        'throwing-event',
        true,
    );

    try {
        $eventResult = runConfiguredDisclosureCase(
            $eventTemporary,
            $eventServerRoot,
            'throwing-event-details',
            [
                'PHPTHIS_TEST_CONFIGURED_RUNTIME_PROFILE' => 'development',
                'PHPTHIS_TEST_CONFIGURED_DISCLOSURE_MODE' => 'DEVELOPMENT_DETAILS',
            ],
        );
        $eventHeaders = array_change_key_case($eventResult['response']['headers'], CASE_LOWER);

        if (
            $eventResult['response']['status'] !== 500
            || ($eventHeaders['content-type'] ?? null) !== 'text/plain; charset=utf-8'
            || ($eventHeaders['cache-control'] ?? null) !== 'private, no-store'
            || ($eventHeaders['x-content-type-options'] ?? null) !== 'nosniff'
            || !str_starts_with(
                $eventResult['response']['body'],
                "PHPThis development failure\n",
            )
            || str_contains($eventResult['response']['body'], 'throwing-event-sink-private-sentinel')
        ) {
            throw new RuntimeException(
                'A throwing real outer-event attempt changed the selected detailed response.',
            );
        }

        assertApplicationLogPayload(
            $eventResult['log'],
            'application.http_outer_failure',
            'application.http_outer_failure failure_class='
                . 'Example\\Http\\DevelopmentDiagnosticFailure',
            'throwing-event configured disclosure',
        );
    } finally {
        removeOuterHttpFailureDirectory($eventTemporary);
    }

    [$rendererTemporary, $rendererServerRoot] = createConfiguredDisclosureServerTree(
        'throwing-renderer-autoload',
        false,
        true,
    );

    try {
        $rendererResult = runConfiguredDisclosureCase(
            $rendererTemporary,
            $rendererServerRoot,
            'throwing-renderer-autoload-generic',
            [
                'PHPTHIS_TEST_CONFIGURED_RUNTIME_PROFILE' => 'development',
                'PHPTHIS_TEST_CONFIGURED_DISCLOSURE_MODE' => 'DEVELOPMENT_DETAILS',
            ],
        );
        $rendererHeaders = array_change_key_case(
            $rendererResult['response']['headers'],
            CASE_LOWER,
        );
        $generic = (new UnknownFailureBoundary())->respond();

        if (
            $rendererResult['response']['status'] !== 500
            || $rendererResult['response']['body'] !== $generic->body
            || ($rendererHeaders['content-type'] ?? null)
                !== 'application/json; charset=utf-8'
            || ($rendererHeaders['cache-control'] ?? null) !== 'private, no-store'
            || isset($rendererHeaders['x-content-type-options'])
            || substr_count(
                $rendererResult['log'],
                'phpthis_test.development_renderer_autoload_attempt',
            ) !== 1
            || str_contains(
                $rendererResult['response']['body'],
                'renderer-autoload-private-sentinel',
            )
        ) {
            throw new RuntimeException(
                'A throwing real renderer-autoload attempt did not retain the generic response.',
            );
        }

        assertApplicationLogPayload(
            $rendererResult['log'],
            'application.http_outer_failure',
            'application.http_outer_failure failure_class='
                . 'Example\\Http\\DevelopmentDiagnosticFailure',
            'throwing-renderer configured disclosure',
        );
    } finally {
        removeOuterHttpFailureDirectory($rendererTemporary);
    }
}

function proveExampleResponseEmissionFailureBranches(): void
{
    $root = dirname(__DIR__);
    $parent = $root . '/tmp/application-tests';

    if (!is_dir($parent) && !mkdir($parent, 0700, true) && !is_dir($parent)) {
        throw new RuntimeException('Unable to create the response-emission test directory.');
    }

    $temporary = $parent . '/response-emission-' . bin2hex(random_bytes(8));

    try {
        $unavailableFileBootstrap = <<<'PHP'
<?php

declare(strict_types=1);

use PHPThis\Http\LocalFileBody;
use PHPThis\Http\Response;

final readonly class UnavailableFileResponseCoordinator
{
    public function handle(array $server, array $query, array $form, array $files): Response
    {
        return new Response(
            200,
            ['Content-Length' => '4'],
            '',
            [],
            new LocalFileBody(__DIR__ . '/missing-response-body.bin', 4),
        );
    }
}

return new UnavailableFileResponseCoordinator();
PHP;
        $unavailableRoot = createExampleFrontControllerProcessTree(
            $temporary . '/unavailable-file',
            $unavailableFileBootstrap,
        );
        $unavailableResult = runIsolatedPhpTest(
            $unavailableRoot . '/example/public/index.php',
        );
        $fallbackBody =
            "{\"error\":{\"code\":\"internal_server_error\","
            . "\"message\":\"Internal server error.\"}}\n";

        if (
            $unavailableResult['exit_code'] !== 0
            || $unavailableResult['stdout'] !== $fallbackBody
        ) {
            throw new RuntimeException(
                'The real front controller did not execute its pre-response emission fallback.',
            );
        }

        assertApplicationLogPayload(
            $unavailableResult['stderr'],
            'application.response_emission_failed',
            'application.response_emission_failed',
            'pre-response emission failure',
        );

        $pendingOutputBootstrap = <<<'PHP'
<?php

declare(strict_types=1);

use PHPThis\Http\Response;

final class PendingOutputOuterFailure extends RuntimeException
{
}

final readonly class PendingOutputResponseCoordinator
{
    public function handle(array $server, array $query, array $form, array $files): Response
    {
        echo 'already-owned-output';

        throw new PendingOutputOuterFailure('pending-output-private-sentinel');
    }
}

return new PendingOutputResponseCoordinator();
PHP;
        $pendingOutputRoot = createExampleFrontControllerProcessTree(
            $temporary . '/pending-output',
            $pendingOutputBootstrap,
        );
        $pendingOutputResult = runIsolatedPhpTest(
            $pendingOutputRoot . '/example/public/index.php',
        );

        if (
            $pendingOutputResult['exit_code'] !== 0
            || $pendingOutputResult['stdout'] !== 'already-owned-output'
        ) {
            throw new RuntimeException(
                'The real front controller replaced output after response ownership began.',
            );
        }

        assertApplicationLogPayload(
            $pendingOutputResult['stderr'],
            'application.response_emission_failed',
            'application.response_emission_failed',
            'generic response-started emission failure',
        );
        assertApplicationLogPayload(
            $pendingOutputResult['stderr'],
            'application.http_outer_failure',
            'application.http_outer_failure failure_class=PendingOutputOuterFailure',
            'generic response-started outer failure',
        );
    } finally {
        removeOuterHttpFailureDirectory($temporary);
    }

    [$detailedTemporary, $detailedServerRoot] = createConfiguredDisclosureServerTree(
        'pending-detailed-output',
    );

    try {
        $autoPrependSource = <<<'PHP'
<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    if ($class === 'Example\\Http\\DevelopmentFailureResponse') {
        error_log('phpthis_test.development_renderer_autoload_success');
    }
});

echo 'already-owned-detailed-output';
PHP;
        $autoPrependPath = $detailedTemporary . '/pending-detailed-output.php';

        if (
            file_put_contents($autoPrependPath, $autoPrependSource)
                !== strlen($autoPrependSource)
        ) {
            throw new RuntimeException('Unable to create the pending detailed-output prelude.');
        }

        $detailedResult = runConfiguredDisclosureCase(
            $detailedTemporary,
            $detailedServerRoot,
            'pending-detailed-output',
            [
                'PHPTHIS_TEST_CONFIGURED_RUNTIME_PROFILE' => 'development',
                'PHPTHIS_TEST_CONFIGURED_DISCLOSURE_MODE' => 'DEVELOPMENT_DETAILS',
            ],
            $autoPrependPath,
        );

        if (
            $detailedResult['response']['body'] !== 'already-owned-detailed-output'
            || substr_count(
                $detailedResult['log'],
                'phpthis_test.development_renderer_autoload_success',
            ) !== 1
        ) {
            throw new RuntimeException(
                'The real configured front controller replaced owned output with details.',
            );
        }

        assertApplicationLogPayload(
            $detailedResult['log'],
            'application.http_outer_failure',
            'application.http_outer_failure failure_class='
                . 'Example\\Http\\DevelopmentDiagnosticFailure',
            'detailed response-started outer failure',
        );
        assertApplicationLogPayload(
            $detailedResult['log'],
            'application.response_emission_failed',
            'application.response_emission_failed',
            'detailed response-started emission failure',
        );
    } finally {
        removeOuterHttpFailureDirectory($detailedTemporary);
    }
}

function createExampleFrontControllerProcessTree(string $serverRoot, string $bootstrap): string
{
    $root = dirname(__DIR__);

    if (
        !mkdir($serverRoot . '/example/public', 0700, true)
        || !copy($root . '/autoload.php', $serverRoot . '/autoload.php')
        || !copy($root . '/example/public/index.php', $serverRoot . '/example/public/index.php')
        || file_put_contents($serverRoot . '/example/bootstrap.php', $bootstrap) !== strlen($bootstrap)
        || !symlink($root . '/src', $serverRoot . '/src')
        || !symlink($root . '/example/src', $serverRoot . '/example/src')
    ) {
        throw new RuntimeException('Unable to create the response-emission server tree.');
    }

    return $serverRoot;
}

function assertApplicationLogPayload(
    string $log,
    string $eventName,
    string $expectedPayload,
    string $context,
): void {
    $lines = preg_split('/\R/', $log);
    $payloads = [];
    $builtInServerTimestamp = '/\A\[[A-Z][a-z]{2} [A-Z][a-z]{2} '
        . '[ 0-9][0-9] [0-9]{2}:[0-9]{2}:[0-9]{2} [0-9]{4}\] /D';

    if (!is_array($lines)) {
        throw new RuntimeException('Unable to split the ' . $context . ' log.');
    }

    foreach ($lines as $line) {
        $applicationLine = preg_replace($builtInServerTimestamp, '', $line, 1);

        if (!is_string($applicationLine)) {
            throw new RuntimeException('Unable to normalize the ' . $context . ' log.');
        }

        if (str_contains($applicationLine, $eventName)) {
            $payloads[] = $applicationLine;
        }
    }

    if ($payloads !== [$expectedPayload]) {
        throw new RuntimeException('The ' . $context . ' application log payload changed.');
    }
}

function proveExampleOuterHttpFailureBoundary(): void
{
    $root = dirname(__DIR__);
    $parent = $root . '/tmp/application-tests';

    if (!is_dir($parent) && !mkdir($parent, 0700, true) && !is_dir($parent)) {
        throw new RuntimeException('Unable to create the outer-boundary test directory.');
    }

    $temporary = $parent . '/outer-http-' . bin2hex(random_bytes(8));
    $serverRoot = $temporary . '/server-root';
    $logPath = $temporary . '/server.log';
    $process = null;

    try {
        if (
            !mkdir($serverRoot . '/example/public', 0700, true)
            || !copy($root . '/autoload.php', $serverRoot . '/autoload.php')
            || !copy($root . '/example/public/index.php', $serverRoot . '/example/public/index.php')
            || !copy(
                __DIR__ . '/fixtures/example-failing-bootstrap.php',
                $serverRoot . '/example/bootstrap.php',
            )
            || !symlink($root . '/src', $serverRoot . '/src')
            || !symlink($root . '/example/src', $serverRoot . '/example/src')
            || file_put_contents($logPath, '') !== 0
            || !chmod($logPath, 0600)
        ) {
            throw new RuntimeException('Unable to create the isolated outer-boundary server tree.');
        }

        [$process, $port] = startExampleOuterHttpFailureServer($serverRoot, $logPath);
        $response = requestExampleOuterHttpFailure($port);
        proc_terminate($process);
        proc_close($process);
        $process = null;
        $serverLog = file_get_contents($logPath);
        $generic = (new UnknownFailureBoundary())->respond();
        $normalizedHeaders = array_change_key_case($response['headers'], CASE_LOWER);
        $bodyForbidden = [
            'outer-bootstrap-private-sentinel',
            'SQLSTATE',
            '/private/application/bootstrap.php',
            'SafeSapiOuterFailure',
            'Fatal error',
            'Uncaught',
            'Stack trace',
            'request-body-debug-sentinel',
            'request-body-profile-sentinel',
            'request-body-detail-sentinel',
        ];
        $logForbidden = [
            'outer-bootstrap-private-sentinel',
            'SQLSTATE',
            '/private/application/bootstrap.php',
            'Fatal error',
            'Uncaught',
            'Stack trace',
        ];

        if (
            $response['status'] !== 500
            || $response['body'] !== $generic->body
            || ($normalizedHeaders['content-type'] ?? null)
                !== 'application/json; charset=utf-8'
            || ($normalizedHeaders['cache-control'] ?? null) !== 'private, no-store'
            || isset($normalizedHeaders['x-request-id'])
            || !is_string($serverLog)
        ) {
            throw new RuntimeException('The real example outer HTTP boundary did not return one generic failure.');
        }

        assertApplicationLogPayload(
            $serverLog,
            'application.http_outer_failure',
            'application.http_outer_failure failure_class=SafeSapiOuterFailure',
            'example outer HTTP failure',
        );

        foreach ($bodyForbidden as $value) {
            if (str_contains($response['body'], $value)) {
                throw new RuntimeException('The outer HTTP boundary disclosed private failure data.');
            }
        }

        foreach ($logForbidden as $value) {
            if (str_contains($serverLog, $value)) {
                throw new RuntimeException('The outer HTTP event disclosed private failure data.');
            }
        }
    } finally {
        if (is_resource($process)) {
            proc_terminate($process);
            proc_close($process);
        }

        removeOuterHttpFailureDirectory($temporary);
    }
}

function proveConfiguredDisclosureDetails(): void
{
    $fixturePath = __DIR__ . '/fixtures/configured-disclosure-consumer.php';
    $fixture = file_get_contents($fixturePath);

    if (
        !is_string($fixture)
        || SyntaxProfile::failures(
            $fixture,
            'tests/fixtures/configured-disclosure-consumer.php',
        ) !== []
    ) {
        throw new RuntimeException('The configured-disclosure consumer must remain profile-compliant.');
    }

    [$temporary, $serverRoot] = createConfiguredDisclosureServerTree('details');

    try {
        $result = runConfiguredDisclosureCase(
            $temporary,
            $serverRoot,
            'eligible-development-details',
            [
                'PHPTHIS_TEST_CONFIGURED_RUNTIME_PROFILE' => 'development',
                'PHPTHIS_TEST_CONFIGURED_DISCLOSURE_MODE' => 'DEVELOPMENT_DETAILS',
            ],
        );
        $headers = array_change_key_case($result['response']['headers'], CASE_LOWER);
        $body = $result['response']['body'];

        if (
            $result['response']['status'] !== 500
            || ($headers['content-type'] ?? null) !== 'text/plain; charset=utf-8'
            || ($headers['cache-control'] ?? null) !== 'private, no-store'
            || ($headers['x-content-type-options'] ?? null) !== 'nosniff'
            || !str_starts_with($body, "PHPThis development failure\n")
            || !str_contains(
                $body,
                'exception[0].class="Example\\\\Http\\\\DevelopmentDiagnosticFailure"',
            )
            || !str_contains(
                $body,
                'exception[0].message="Development diagnostic failure."',
            )
            || strlen($body) > 65_536
        ) {
            throw new RuntimeException('An eligible configured consumer did not select bounded details.');
        }

        assertApplicationLogPayload(
            $result['log'],
            'application.http_outer_failure',
            'application.http_outer_failure failure_class='
                . 'Example\\Http\\DevelopmentDiagnosticFailure',
            'eligible configured disclosure',
        );

        foreach ([
            'PHPTHIS_TEST_CONFIGURED_RUNTIME_PROFILE',
            'PHPTHIS_TEST_CONFIGURED_DISCLOSURE_MODE',
            'debug=1',
            'environment=local',
            'debug=on',
            'request-body-debug-sentinel',
            'request-body-profile-sentinel',
            'request-body-detail-sentinel',
            '127.0.0.1',
        ] as $forbidden) {
            if (str_contains($body, $forbidden)) {
                throw new RuntimeException('Request or configuration input entered detailed diagnostics.');
            }
        }
    } finally {
        removeOuterHttpFailureDirectory($temporary);
    }
}

function proveConfiguredDisclosureFailures(): void
{
    [$temporary, $serverRoot] = createConfiguredDisclosureServerTree('fail-closed');
    $cases = [
        'missing-profile' => [
            'PHPTHIS_TEST_CONFIGURED_DISCLOSURE_MODE' => 'DEVELOPMENT_DETAILS',
        ],
        'missing-mode' => [
            'PHPTHIS_TEST_CONFIGURED_RUNTIME_PROFILE' => 'development',
        ],
        'empty-profile' => [
            'PHPTHIS_TEST_CONFIGURED_RUNTIME_PROFILE' => '',
            'PHPTHIS_TEST_CONFIGURED_DISCLOSURE_MODE' => 'DEVELOPMENT_DETAILS',
        ],
        'empty-mode' => [
            'PHPTHIS_TEST_CONFIGURED_RUNTIME_PROFILE' => 'development',
            'PHPTHIS_TEST_CONFIGURED_DISCLOSURE_MODE' => '',
        ],
        'malformed-profile' => [
            'PHPTHIS_TEST_CONFIGURED_RUNTIME_PROFILE' => 'preview',
            'PHPTHIS_TEST_CONFIGURED_DISCLOSURE_MODE' => 'DEVELOPMENT_DETAILS',
        ],
        'malformed-mode' => [
            'PHPTHIS_TEST_CONFIGURED_RUNTIME_PROFILE' => 'development',
            'PHPTHIS_TEST_CONFIGURED_DISCLOSURE_MODE' => 'details',
        ],
        'staging-details' => [
            'PHPTHIS_TEST_CONFIGURED_RUNTIME_PROFILE' => 'staging',
            'PHPTHIS_TEST_CONFIGURED_DISCLOSURE_MODE' => 'DEVELOPMENT_DETAILS',
        ],
        'production-details' => [
            'PHPTHIS_TEST_CONFIGURED_RUNTIME_PROFILE' => 'production',
            'PHPTHIS_TEST_CONFIGURED_DISCLOSURE_MODE' => 'DEVELOPMENT_DETAILS',
        ],
        'explicit-generic-request-override' => [
            'PHPTHIS_TEST_CONFIGURED_RUNTIME_PROFILE' => 'local',
            'PHPTHIS_TEST_CONFIGURED_DISCLOSURE_MODE' => 'GENERIC',
        ],
    ];
    $generic = (new UnknownFailureBoundary())->respond();

    try {
        foreach ($cases as $name => $environment) {
            $result = runConfiguredDisclosureCase(
                $temporary,
                $serverRoot,
                $name,
                $environment,
            );
            $headers = array_change_key_case($result['response']['headers'], CASE_LOWER);
            $expectedFailureClass = $name === 'explicit-generic-request-override'
                ? 'Example\\Http\\DevelopmentDiagnosticFailure'
                : InvalidArgumentException::class;

            if (
                $result['response']['status'] !== 500
                || $result['response']['body'] !== $generic->body
                || ($headers['content-type'] ?? null) !== 'application/json; charset=utf-8'
                || ($headers['cache-control'] ?? null) !== 'private, no-store'
                || isset($headers['x-request-id'])
                || str_contains($result['response']['body'], 'PHPThis development failure')
                || str_contains($result['response']['body'], 'request-body-debug-sentinel')
                || str_contains($result['response']['body'], 'request-body-profile-sentinel')
                || str_contains($result['response']['body'], 'request-body-detail-sentinel')
                || (
                    $name !== 'explicit-generic-request-override'
                    && str_contains(
                        $result['log'],
                        'failure_class=Example\\Http\\DevelopmentDiagnosticFailure',
                    )
                )
            ) {
                throw new RuntimeException('Configured disclosure did not fail closed for ' . $name . '.');
            }

            assertApplicationLogPayload(
                $result['log'],
                'application.http_outer_failure',
                'application.http_outer_failure failure_class=' . $expectedFailureClass,
                'fail-closed configured disclosure ' . $name,
            );
        }
    } finally {
        removeOuterHttpFailureDirectory($temporary);
    }
}

/** @return array{0: string, 1: string} */
function createConfiguredDisclosureServerTree(
    string $name,
    bool $throwingEventSink = false,
    bool $throwingRendererAutoload = false,
): array
{
    $root = dirname(__DIR__);
    $parent = $root . '/tmp/application-tests';

    if (!is_dir($parent) && !mkdir($parent, 0700, true) && !is_dir($parent)) {
        throw new RuntimeException('Unable to create the configured-disclosure test directory.');
    }

    $temporary = $parent . '/configured-disclosure-' . $name . '-' . bin2hex(random_bytes(8));
    $serverRoot = $temporary . '/server-root';
    $autoload = $throwingRendererAutoload
        ? configuredDisclosureThrowingRendererAutoload()
        : file_get_contents($root . '/autoload.php');

    if (
        !is_string($autoload)
        || !mkdir($serverRoot . '/public', 0700, true)
        || !mkdir($serverRoot . '/example', 0700)
        || file_put_contents($serverRoot . '/autoload.php', $autoload) !== strlen($autoload)
        || !copy(
            __DIR__ . '/fixtures/configured-disclosure-consumer.php',
            $serverRoot . '/public/index.php',
        )
        || !symlink($root . '/src', $serverRoot . '/src')
    ) {
        removeOuterHttpFailureDirectory($temporary);
        throw new RuntimeException('Unable to create the configured-disclosure server tree.');
    }

    if ($throwingEventSink) {
        $sink = configuredDisclosureThrowingEventSink();

        if (
            !mkdir($serverRoot . '/example/src/Observability', 0700, true)
            || !symlink($root . '/example/src/Http', $serverRoot . '/example/src/Http')
            || !symlink(
                $root . '/example/src/Observability/FailureClass.php',
                $serverRoot . '/example/src/Observability/FailureClass.php',
            )
            || file_put_contents(
                $serverRoot . '/example/src/Observability/ErrorLogOuterFailureSink.php',
                $sink,
            ) !== strlen($sink)
        ) {
            removeOuterHttpFailureDirectory($temporary);
            throw new RuntimeException('Unable to create the throwing outer-event sink.');
        }
    } elseif (!symlink($root . '/example/src', $serverRoot . '/example/src')) {
        removeOuterHttpFailureDirectory($temporary);
        throw new RuntimeException('Unable to expose the example source to configured disclosure.');
    }

    return [$temporary, $serverRoot];
}

function configuredDisclosureThrowingEventSink(): string
{
    return <<<'PHP'
<?php

declare(strict_types=1);

namespace Example\Observability;

use RuntimeException;
use Throwable;

final class ErrorLogOuterFailureSink
{
    public function emit(Throwable $failure): void
    {
        error_log(
            'application.http_outer_failure failure_class=' . FailureClass::fromThrowable($failure),
        );

        throw new RuntimeException('throwing-event-sink-private-sentinel');
    }
}
PHP;
}

function configuredDisclosureThrowingRendererAutoload(): string
{
    return <<<'PHP'
<?php

declare(strict_types=1);

$prefixes = [
    'PHPThis\\' => __DIR__ . '/src/',
    'Example\\' => __DIR__ . '/example/src/',
];

spl_autoload_register(static function (string $class) use ($prefixes): void {
    if ($class === 'Example\\Http\\DevelopmentFailureResponse') {
        error_log('phpthis_test.development_renderer_autoload_attempt');

        throw new RuntimeException('renderer-autoload-private-sentinel');
    }

    foreach ($prefixes as $prefix => $directory) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relativeClass = substr($class, strlen($prefix));
        $path = $directory . str_replace('\\', '/', $relativeClass) . '.php';

        if (is_file($path)) {
            require $path;
        }

        return;
    }
});
PHP;
}

/**
 * @param array<string, string> $configuredInputs
 * @return array{
 *     response: array{status: int, headers: array<string, string>, body: string},
 *     log: string
 * }
 */
function runConfiguredDisclosureCase(
    string $temporary,
    string $serverRoot,
    string $name,
    array $configuredInputs,
    ?string $autoPrependFile = null,
): array {
    $logPath = $temporary . '/' . $name . '.log';

    if (file_put_contents($logPath, '') !== 0 || !chmod($logPath, 0600)) {
        throw new RuntimeException('Unable to create a private configured-disclosure server log.');
    }

    $process = null;

    try {
        [$process, $port] = startExampleOuterHttpFailureServer(
            $serverRoot,
            $logPath,
            $configuredInputs,
            $serverRoot . '/public',
            $autoPrependFile,
        );
        $response = requestExampleOuterHttpFailure($port);
        proc_terminate($process);
        proc_close($process);
        $process = null;
        $log = file_get_contents($logPath);

        if (!is_string($log)) {
            throw new RuntimeException('Unable to read the configured-disclosure server log.');
        }

        return ['response' => $response, 'log' => $log];
    } finally {
        if (is_resource($process)) {
            proc_terminate($process);
            proc_close($process);
        }
    }
}

/**
 * @param array<string, string>|null $environment
 * @return array{0: resource, 1: int}
 */
function startExampleOuterHttpFailureServer(
    string $serverRoot,
    string $logPath,
    ?array $environment = null,
    ?string $documentRoot = null,
    ?string $autoPrependFile = null,
): array
{
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);

    if (!is_resource($socket)) {
        throw new RuntimeException('Unable to reserve the outer-boundary HTTP port.');
    }

    $socketName = stream_socket_get_name($socket, false);
    fclose($socket);
    $separator = is_string($socketName) ? strrpos($socketName, ':') : false;
    $portValue = $separator === false ? null : substr($socketName, $separator + 1);
    $port = is_string($portValue) ? filter_var($portValue, FILTER_VALIDATE_INT) : false;

    if (!is_int($port) || $port < 1 || $port > 65_535) {
        throw new RuntimeException('Unable to resolve the outer-boundary HTTP port.');
    }

    $command = [
        PHP_BINARY,
        '-d',
        'error_reporting=-1',
        '-d',
        'display_errors=0',
        '-d',
        'display_startup_errors=0',
        '-d',
        'log_errors=1',
        '-d',
        'zend.exception_ignore_args=1',
    ];

    if ($autoPrependFile !== null) {
        $command[] = '-d';
        $command[] = 'output_buffering=0';
        $command[] = '-d';
        $command[] = 'auto_prepend_file=' . $autoPrependFile;
    }

    $command[] = '-S';
    $command[] = '127.0.0.1:' . $port;
    $command[] = '-t';
    $command[] = $documentRoot ?? $serverRoot . '/example/public';

    $process = proc_open(
        $command,
        [
            0 => ['pipe', 'r'],
            1 => ['file', $logPath, 'a'],
            2 => ['file', $logPath, 'a'],
        ],
        $pipes,
        $serverRoot,
        $environment,
        ['bypass_shell' => true],
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start the outer-boundary HTTP server.');
    }

    fclose($pipes[0]);
    $deadline = hrtime(true) + 5_000_000_000;

    do {
        $probe = @fsockopen('127.0.0.1', $port, $probeError, $probeMessage, 0.05);

        if (is_resource($probe)) {
            fclose($probe);

            return [$process, $port];
        }

        usleep(10_000);
    } while (hrtime(true) < $deadline);

    proc_terminate($process);
    proc_close($process);
    throw new RuntimeException('The outer-boundary HTTP server did not become ready.');
}

/** @return array{status: int, headers: array<string, string>, body: string} */
function requestExampleOuterHttpFailure(int $port): array
{
    $socket = fsockopen('127.0.0.1', $port, $errorCode, $errorMessage, 2.0);

    if (!is_resource($socket)) {
        throw new RuntimeException('Unable to connect to the outer-boundary HTTP server.');
    }

    stream_set_timeout($socket, 5);
    $body = '{"debug":"request-body-debug-sentinel",'
        . '"profile":"request-body-profile-sentinel",'
        . '"details":"request-body-detail-sentinel"}';
    $request = "POST /health?debug=1&environment=local HTTP/1.1\r\n"
        . 'Host: 127.0.0.1:' . $port . "\r\n"
        . "Content-Type: application/json\r\n"
        . 'Content-Length: ' . strlen($body) . "\r\n"
        . "X-Debug: on\r\n"
        . "X-Environment: local\r\n"
        . "Cookie: debug=on; environment=local\r\n"
        . "Connection: close\r\n\r\n"
        . $body;

    if (fwrite($socket, $request) !== strlen($request)) {
        fclose($socket);
        throw new RuntimeException('Unable to write the outer-boundary HTTP request.');
    }

    $raw = stream_get_contents($socket);
    fclose($socket);

    if (!is_string($raw)) {
        throw new RuntimeException('Unable to read the outer-boundary HTTP response.');
    }

    $parts = explode("\r\n\r\n", $raw, 2);
    $head = $parts[0];
    $body = $parts[1] ?? '';
    $lines = explode("\r\n", $head);
    $statusLine = array_shift($lines);

    if (preg_match('/\AHTTP\/1\.[01] ([0-9]{3}) /D', $statusLine, $match) !== 1) {
        throw new RuntimeException('The outer-boundary HTTP status line is invalid.');
    }

    $headers = [];

    foreach ($lines as $line) {
        $separator = strpos($line, ':');

        if ($separator === false) {
            throw new RuntimeException('The outer-boundary HTTP header line is invalid.');
        }

        $headers[substr($line, 0, $separator)] = ltrim(substr($line, $separator + 1));
    }

    return ['status' => (int) $match[1], 'headers' => $headers, 'body' => $body];
}

function removeOuterHttpFailureDirectory(string $path): void
{
    if (is_link($path) || is_file($path)) {
        if (!unlink($path)) {
            throw new RuntimeException('Unable to remove an outer-boundary test file.');
        }

        return;
    }

    if (!is_dir($path)) {
        return;
    }

    $entries = scandir($path);

    if (!is_array($entries)) {
        throw new RuntimeException('Unable to inspect the outer-boundary test directory.');
    }

    foreach ($entries as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            removeOuterHttpFailureDirectory($path . '/' . $entry);
        }
    }

    if (!rmdir($path)) {
        throw new RuntimeException('Unable to remove the outer-boundary test directory.');
    }
}
