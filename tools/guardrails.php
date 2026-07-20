<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/verification/SyntaxProfile.php';

use PHPThis\Verification\SyntaxProfile;

/**
 * @return list<string>
 */
function routingLookupFailures(string $contents, string $relativePath): array
{
    $tokens = token_get_all($contents);
    $traversalFunctions = [
        'array_all',
        'array_any',
        'array_filter',
        'array_find',
        'array_find_key',
        'array_map',
        'array_reduce',
        'array_search',
        'array_walk',
        'array_walk_recursive',
        'in_array',
        'uasort',
        'uksort',
        'usort',
    ];
    /** @var array<string, list<string>> $callsByMethod */
    $callsByMethod = [];
    /** @var array<string, list<string>> $failuresByMethod */
    $failuresByMethod = [];
    /** @var list<string> $methodOrder */
    $methodOrder = [];
    $pendingMethod = null;
    $currentMethod = null;
    $currentMethodBraceDepth = null;
    $braceDepth = 0;

    foreach ($tokens as $index => $token) {
        $tokenId = is_array($token) ? $token[0] : null;
        $tokenText = is_array($token) ? $token[1] : $token;

        if ($currentMethod === null && $tokenId === T_FUNCTION) {
            $nameIndex = routingNextSignificantTokenIndex($tokens, $index + 1);

            if ($nameIndex !== null && routingTokenText($tokens[$nameIndex]) === '&') {
                $nameIndex = routingNextSignificantTokenIndex($tokens, $nameIndex + 1);
            }

            $nameToken = $nameIndex === null ? null : $tokens[$nameIndex];
            $pendingMethod = is_array($nameToken) && in_array($nameToken[0], [T_STRING, T_MATCH], true)
                ? $nameToken[1]
                : null;
            continue;
        }

        if ($tokenText === '{') {
            $braceDepth++;

            if ($pendingMethod !== null) {
                $currentMethod = $pendingMethod;
                $currentMethodBraceDepth = $braceDepth;
                $callsByMethod[$currentMethod] = [];
                $failuresByMethod[$currentMethod] = [];
                $methodOrder[] = $currentMethod;
                $pendingMethod = null;
            }

            continue;
        }

        if ($tokenText === '}') {
            if ($currentMethodBraceDepth === $braceDepth) {
                $currentMethod = null;
                $currentMethodBraceDepth = null;
            }

            $braceDepth--;
            continue;
        }

        if ($currentMethod === null) {
            if ($pendingMethod !== null && $tokenText === ';') {
                $pendingMethod = null;
            }

            continue;
        }

        if (
            in_array($tokenId, [T_FOR, T_FOREACH, T_WHILE, T_DO], true)
            && !(
                $tokenId === T_FOREACH
                && routingIsBoundedPathSegmentForeach($tokens, $index)
            )
        ) {
            $failuresByMethod[$currentMethod][] = sprintf(
                '%s:%d uses a loop in lookup-reachable Router method %s; route lookup must remain indexed.',
                $relativePath,
                $token[2],
                $currentMethod,
            );
        }

        if ($tokenId === T_VARIABLE && $tokenText === '$this') {
            $operatorIndex = routingNextSignificantTokenIndex($tokens, $index + 1);
            $operatorToken = $operatorIndex === null ? null : $tokens[$operatorIndex];
            $operatorId = is_array($operatorToken) ? $operatorToken[0] : null;

            if (in_array($operatorId, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true)) {
                $methodIndex = routingNextSignificantTokenIndex($tokens, $operatorIndex + 1);
                $methodToken = $methodIndex === null ? null : $tokens[$methodIndex];
                $openIndex = $methodIndex === null
                    ? null
                    : routingNextSignificantTokenIndex($tokens, $methodIndex + 1);

                if (
                    is_array($methodToken)
                    && $methodToken[0] === T_STRING
                    && $openIndex !== null
                    && routingTokenText($tokens[$openIndex]) === '('
                ) {
                    $callsByMethod[$currentMethod][] = $methodToken[1];
                }
            }
        }

        if (!in_array($tokenId, [T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED], true)) {
            continue;
        }

        $functionName = strtolower(ltrim($tokenText, '\\'));
        $separator = strrpos($functionName, '\\');

        if ($separator !== false) {
            $functionName = substr($functionName, $separator + 1);
        }

        if (!in_array($functionName, $traversalFunctions, true)) {
            continue;
        }

        $previousIndex = routingPreviousSignificantTokenIndex($tokens, $index - 1);
        $previousToken = $previousIndex === null ? null : $tokens[$previousIndex];
        $previousId = is_array($previousToken) ? $previousToken[0] : null;
        $openIndex = routingNextSignificantTokenIndex($tokens, $index + 1);

        if (
            $openIndex !== null
            && routingTokenText($tokens[$openIndex]) === '('
            && !in_array(
                $previousId,
                [T_FUNCTION, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON],
                true,
            )
        ) {
            $failuresByMethod[$currentMethod][] = sprintf(
                '%s:%d calls traversal function %s in lookup-reachable Router method %s; route lookup must remain indexed.',
                $relativePath,
                $token[2],
                $functionName,
                $currentMethod,
            );
        }
    }

    $reachableMethods = [];
    $pendingMethods = ['match', 'allowedMethodsForPath'];

    while ($pendingMethods !== []) {
        $method = array_pop($pendingMethods);

        if (isset($reachableMethods[$method])) {
            continue;
        }

        $reachableMethods[$method] = true;

        foreach ($callsByMethod[$method] ?? [] as $calledMethod) {
            $pendingMethods[] = $calledMethod;
        }
    }

    $failures = [];

    foreach ($methodOrder as $orderedMethod) {
        if (!isset($reachableMethods[$orderedMethod])) {
            continue;
        }

        foreach ($failuresByMethod[$orderedMethod] as $failure) {
            $failures[] = $failure;
        }
    }

    return $failures;
}

/**
 * @param list<array{0: int, 1: string, 2: int}|string> $tokens
 */
function routingIsBoundedPathSegmentForeach(array $tokens, int $foreachIndex): bool
{
    $openIndex = routingNextSignificantTokenIndex($tokens, $foreachIndex + 1);
    $segmentsIndex = $openIndex === null
        ? null
        : routingNextSignificantTokenIndex($tokens, $openIndex + 1);
    $asIndex = $segmentsIndex === null
        ? null
        : routingNextSignificantTokenIndex($tokens, $segmentsIndex + 1);
    $segmentIndex = $asIndex === null
        ? null
        : routingNextSignificantTokenIndex($tokens, $asIndex + 1);
    $closeIndex = $segmentIndex === null
        ? null
        : routingNextSignificantTokenIndex($tokens, $segmentIndex + 1);

    if (
        $openIndex === null
        || routingTokenText($tokens[$openIndex]) !== '('
        || $segmentsIndex === null
        || $asIndex === null
        || $segmentIndex === null
        || $closeIndex === null
    ) {
        return false;
    }

    $segmentsToken = $tokens[$segmentsIndex];
    $asToken = $tokens[$asIndex];
    $segmentToken = $tokens[$segmentIndex];

    return is_array($segmentsToken)
        && $segmentsToken[0] === T_VARIABLE
        && $segmentsToken[1] === '$segments'
        && is_array($asToken)
        && $asToken[0] === T_AS
        && is_array($segmentToken)
        && $segmentToken[0] === T_VARIABLE
        && $segmentToken[1] === '$segment'
        && routingTokenText($tokens[$closeIndex]) === ')'
        && routingHasLocalPathSegmentsAssignment($tokens, $foreachIndex);
}

/**
 * @param list<array{0: int, 1: string, 2: int}|string> $tokens
 */
function routingHasLocalPathSegmentsAssignment(array $tokens, int $foreachIndex): bool
{
    for ($index = $foreachIndex - 1; $index >= 0; $index--) {
        $token = $tokens[$index];

        if (is_array($token) && $token[0] === T_FUNCTION) {
            return false;
        }

        if (!is_array($token) || $token[0] !== T_VARIABLE || $token[1] !== '$segments') {
            continue;
        }

        $equalsIndex = routingNextSignificantTokenIndex($tokens, $index + 1);

        if ($equalsIndex === null || routingTokenText($tokens[$equalsIndex]) !== '=') {
            return false;
        }

        $explodeIndex = routingNextSignificantTokenIndex($tokens, $equalsIndex + 1);
        $openIndex = $explodeIndex === null
            ? null
            : routingNextSignificantTokenIndex($tokens, $explodeIndex + 1);
        $delimiterIndex = $openIndex === null
            ? null
            : routingNextSignificantTokenIndex($tokens, $openIndex + 1);
        $commaIndex = $delimiterIndex === null
            ? null
            : routingNextSignificantTokenIndex($tokens, $delimiterIndex + 1);
        $pathIndex = $commaIndex === null
            ? null
            : routingNextSignificantTokenIndex($tokens, $commaIndex + 1);
        $closeIndex = $pathIndex === null
            ? null
            : routingNextSignificantTokenIndex($tokens, $pathIndex + 1);

        if (
            $explodeIndex === null
            || $openIndex === null
            || $delimiterIndex === null
            || $commaIndex === null
            || $pathIndex === null
            || $closeIndex === null
        ) {
            return false;
        }

        $explodeToken = $tokens[$explodeIndex];
        $delimiterToken = $tokens[$delimiterIndex];
        $pathToken = $tokens[$pathIndex];

        return is_array($explodeToken)
            && $explodeToken[0] === T_STRING
            && strtolower($explodeToken[1]) === 'explode'
            && routingTokenText($tokens[$openIndex]) === '('
            && is_array($delimiterToken)
            && $delimiterToken[0] === T_CONSTANT_ENCAPSED_STRING
            && in_array($delimiterToken[1], ["'/'", '"/"'], true)
            && routingTokenText($tokens[$commaIndex]) === ','
            && is_array($pathToken)
            && $pathToken[0] === T_VARIABLE
            && $pathToken[1] === '$path'
            && routingTokenText($tokens[$closeIndex]) === ')';
    }

    return false;
}

/**
 * @param list<array{0: int, 1: string, 2: int}|string> $tokens
 */
function routingNextSignificantTokenIndex(array $tokens, int $start): ?int
{
    for ($index = $start, $count = count($tokens); $index < $count; $index++) {
        $token = $tokens[$index];

        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        return $index;
    }

    return null;
}

/**
 * @param list<array{0: int, 1: string, 2: int}|string> $tokens
 */
function routingPreviousSignificantTokenIndex(array $tokens, int $start): ?int
{
    for ($index = $start; $index >= 0; $index--) {
        $token = $tokens[$index];

        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        return $index;
    }

    return null;
}

/** @param array{0: int, 1: string, 2: int}|string $token */
function routingTokenText(array|string $token): string
{
    return is_array($token) ? $token[1] : $token;
}

$root = dirname(__DIR__);
$phpFiles = [];
$markdownFiles = [];
$failures = [];
$nativeSessionFunctions = [
    'session_abort',
    'session_cache_expire',
    'session_cache_limiter',
    'session_commit',
    'session_create_id',
    'session_decode',
    'session_destroy',
    'session_encode',
    'session_gc',
    'session_get_cookie_params',
    'session_id',
    'session_module_name',
    'session_name',
    'session_regenerate_id',
    'session_register_shutdown',
    'session_reset',
    'session_save_path',
    'session_set_cookie_params',
    'session_set_save_handler',
    'session_start',
    'session_status',
    'session_unset',
    'session_write_close',
];
$phpstanConfig = file_get_contents($root . '/phpstan.neon');

if (!is_string($phpstanConfig)) {
    $failures[] = 'Cannot read phpstan.neon.';
} else {
    if (!str_contains($phpstanConfig, 'vendor/phpstan/phpstan-strict-rules/rules.neon')) {
        $failures[] = 'phpstan.neon must include PHPStan strict rules.';
    }

    if (!str_contains($phpstanConfig, 'verification/phpstan/extension.php')) {
        $failures[] = 'phpstan.neon must include PHPThis Strict Profile rules.';
    }

    if (preg_match('/strictRules:\s*\R\s+allRules:\s*true\b/', $phpstanConfig) !== 1) {
        $failures[] = 'phpstan.neon must explicitly enable every installed strict rule.';
    }

    if (preg_match('/^\s*ignoreErrors\s*:/m', $phpstanConfig) === 1) {
        $failures[] = 'phpstan.neon must not define ignoreErrors.';
    }
}

$requiredRepositoryFiles = [
    '.github/workflows/ci.yml',
    'RELEASING.md',
    '.ai/cache.md',
    '.ai/cli.md',
    '.ai/crud.md',
    '.ai/database.md',
    '.ai/http.md',
    '.ai/jobs.md',
    '.ai/observability.md',
    '.ai/request-policy.md',
    '.ai/routing.md',
    '.ai/session.md',
    'docs/consumer-contract.md',
    'docs/caching.md',
    'docs/cli.md',
    'docs/cli/README.md',
    'docs/cli/arguments-output.md',
    'docs/cli/composition.md',
    'docs/cli/scheduling-locking.md',
    'docs/cli/testing.md',
    'docs/crud.md',
    'docs/getting-started.md',
    'docs/jobs.md',
    'docs/jobs/README.md',
    'docs/jobs/envelope.md',
    'docs/jobs/lifecycle.md',
    'docs/jobs/operations.md',
    'docs/jobs/schema.md',
    'docs/jobs/testing.md',
    'docs/knowledge-map.md',
    'docs/observability/README.md',
    'docs/observability/correlation-id.md',
    'docs/observability/database-evidence.md',
    'docs/observability/event-schema.md',
    'docs/observability/sink-failure.md',
    'docs/observability/testing.md',
    'docs/request-policy.md',
    'docs/releases/0.1.0-alpha.1.md',
    'docs/security.md',
    'docs/sessions.md',
    'docs/vocabulary.md',
    'docs/decisions/011-ai-first-authoring.md',
    'docs/decisions/012-pdo-transport-application-owned-dialects.md',
    'docs/decisions/013-optional-crud-reference-profile.md',
    'docs/decisions/014-sql-data-and-finite-structure.md',
    'docs/decisions/015-explicit-native-session-lifecycle.md',
    'docs/decisions/016-cache-policy-before-cache-mechanism.md',
    'docs/decisions/017-bounded-trailing-positive-integer-routes.md',
    'docs/decisions/018-bounded-alpha-1-release-scope.md',
    'docs/decisions/019-bounded-multiple-typed-routes.md',
    'docs/decisions/020-application-owned-request-policy.md',
    'docs/decisions/021-application-owned-typed-input-boundaries.md',
    'docs/decisions/022-application-owned-finite-data-paths.md',
    'docs/decisions/023-application-owned-terminal-request-summaries.md',
    'docs/decisions/024-application-owned-sqlite-durable-jobs.md',
    'docs/decisions/025-application-owned-explicit-cli-and-scheduler.md',
    'example/AGENTS.md',
    'example/.ai/README.md',
    'example/.ai/cli.md',
    'example/.ai/data.md',
    'example/.ai/jobs.md',
    'example/.ai/observability.md',
    'example/bin/console.php',
    'example/src/ApplicationComposition.php',
    'example/src/ApplicationDatabasePath.php',
    'example/src/InvalidApplicationDatabasePath.php',
    'example/src/Cli/ApplicationCommandExecution.php',
    'example/src/Cli/ApplicationCommandLine.php',
    'example/src/Cli/ApplicationCommandName.php',
    'example/src/Cli/ApplicationCommandOutcome.php',
    'example/src/Cli/ApplicationCommands.php',
    'example/src/Cli/InvalidApplicationCommandArguments.php',
    'example/src/Cli/LocalScheduleLock.php',
    'example/src/Cli/README.md',
    'example/src/Cli/UnknownApplicationCommand.php',
    'example/src/Jobs/InvalidUserWelcomeJobEnvelope.php',
    'example/src/Jobs/README.md',
    'example/src/Jobs/RecordUserWelcomeDelivery.php',
    'example/src/Jobs/SqliteUserWelcomeJobLease.php',
    'example/src/Jobs/SqliteUserWelcomeJobWorker.php',
    'example/src/Jobs/SystemUserWelcomeJobClock.php',
    'example/src/Jobs/UserWelcomeJobClock.php',
    'example/src/Jobs/UserWelcomeJobEnvelope.php',
    'example/src/Jobs/UserWelcomeJobHandler.php',
    'example/src/Jobs/UserWelcomeJobOutcome.php',
    'example/src/Observability/CorrelationId.php',
    'example/src/Observability/ErrorLogRequestSummarySink.php',
    'example/src/Observability/QuerySummarySource.php',
    'example/src/Observability/README.md',
    'example/src/Observability/RequestSummary.php',
    'example/src/Observability/RequestSummarySink.php',
    'example/src/Observability/TerminalRequestCoordinator.php',
    'example/src/Documents/DocumentRoutes.php',
    'example/src/Documents/AccountId.php',
    'example/src/Documents/AuthenticateDocumentRequest.php',
    'example/src/Documents/AuthenticatedPrincipal.php',
    'example/src/Documents/CrossTenant.php',
    'example/src/Documents/DenyAllDocumentAuthentication.php',
    'example/src/Documents/DenyAllDocumentAuthorization.php',
    'example/src/Documents/DenyAllDocumentTenantResolution.php',
    'example/src/Documents/DocumentKey.php',
    'example/src/Documents/Forbidden.php',
    'example/src/Documents/ResolveDocumentTenant.php',
    'example/src/Documents/ResolvedTenant.php',
    'example/src/Documents/Unauthenticated.php',
    'example/src/Documents/GetDocument/AuthorizeGetDocument.php',
    'example/src/Documents/GetDocument/DocumentDetails.php',
    'example/src/Documents/GetDocument/GetDocumentHandler.php',
    'example/src/Documents/GetDocument/RetrieveAuthorizedDocument.php',
    'example/src/Documents/GetDocument/SelectAuthorizedDocument.php',
    'example/src/Documents/ListDocuments/AuthorizeListDocuments.php',
    'example/src/Documents/ListDocuments/ListDocumentsPageRequest.php',
    'example/src/Documents/ListDocuments/DocumentSummary.php',
    'example/src/Documents/ListDocuments/ListDocumentsHandler.php',
    'example/src/Users/GetUser/GetUserHandler.php',
    'example/src/Users/GetUser/UserDetails.php',
    'example/src/Users/GetUser/UserId.php',
    'example/src/Users/CreateUser/CreateUserOperation.php',
    'example/src/Users/CreateUser/TransactionalCreateUser.php',
    'example/src/Users/UserRoutes.php',
    'templates/application/AGENTS.md',
    'templates/application/.ai/README.md',
    'templates/application/.ai/architecture.md',
    'templates/application/.ai/change-workflow.md',
    'templates/application/.ai/cli.md',
    'templates/application/.ai/data.md',
    'templates/application/.ai/integrations.md',
    'templates/application/.ai/jobs.md',
    'templates/application/.ai/observability.md',
    'templates/application/.ai/operations.md',
    'templates/application/.ai/project.md',
    'templates/application/.ai/request-policy.md',
    'templates/application/.ai/rules.md',
    'templates/application/.ai/testing.md',
    'templates/application/docs/decisions/README.md',
    'skeleton/AGENTS.md',
    'skeleton/.gitignore',
    'skeleton/.github/workflows/ci.yml',
    'skeleton/LICENSE',
    'skeleton/README.md',
    'skeleton/.ai/README.md',
    'skeleton/.ai/architecture.md',
    'skeleton/.ai/change-workflow.md',
    'skeleton/.ai/cli.md',
    'skeleton/.ai/data.md',
    'skeleton/.ai/integrations.md',
    'skeleton/.ai/jobs.md',
    'skeleton/.ai/observability.md',
    'skeleton/.ai/operations.md',
    'skeleton/.ai/project.md',
    'skeleton/.ai/request-policy.md',
    'skeleton/.ai/rules.md',
    'skeleton/.ai/testing.md',
    'skeleton/bootstrap.php',
    'skeleton/composer.json',
    'skeleton/docs/decisions/README.md',
    'skeleton/public/index.php',
    'skeleton/src/HealthHandler.php',
    'skeleton/src/HealthRoutes.php',
    'skeleton/src/Observability/CorrelationId.php',
    'skeleton/src/Observability/ErrorLogRequestSummarySink.php',
    'skeleton/src/Observability/QuerySummarySource.php',
    'skeleton/src/Observability/README.md',
    'skeleton/src/Observability/RequestSummary.php',
    'skeleton/src/Observability/RequestSummarySink.php',
    'skeleton/src/Observability/TerminalRequestCoordinator.php',
    'skeleton/src/Routes.php',
    'skeleton/tests/run.php',
    'bin/phpthis',
    'verification/ApplicationChecker.php',
    'verification/SyntaxProfile.php',
    'verification/phpstan/ConnectionCallableArrayRule.php',
    'verification/phpstan/ConnectionMethodCallableRule.php',
    'verification/phpstan/ConnectionSqlRuleSupport.php',
    'verification/phpstan/ConstantSqlStringRule.php',
    'verification/phpstan/DirectPdoConstructionRule.php',
    'verification/phpstan/MixedScalarCoercionRule.php',
    'verification/phpstan/extension.php',
    'src/Http/CookieSameSite.php',
    'src/Http/ResponseCookie.php',
    'src/Routing/PathParameters.php',
    'src/Routing/Route.php',
    'src/Routing/RouteMatch.php',
    'src/Routing/RouteParameterType.php',
    'src/Routing/RouteSegment.php',
    'src/Routing/Router.php',
    'src/Session/SessionConfiguration.php',
    'src/Session/SessionLifecycle.php',
    'src/Session/SessionSnapshot.php',
    'src/Session/SessionUnavailable.php',
    'tests/observability.php',
    'tests/jobs.php',
    'tests/cli.php',
    'tests/cli-schedule-lock-holder.php',
    'tests/job-worker-crash.php',
    'tests/request-policy.php',
    'tests/fixtures/routing-construction-traversal.php.fixture',
    'tests/fixtures/routing-lookup-index-loop.php.fixture',
    'tests/fixtures/routing-lookup-helper-loop.php.fixture',
    'tests/fixtures/routing-path-segment-traversal.php.fixture',
    'tests/fixtures/routing-lookup-traversal.php.fixture',
    'tools/package-files.txt',
    'tools/setup-example.php',
    'tools/test-database-drivers.php',
];

foreach ($requiredRepositoryFiles as $requiredRepositoryFile) {
    if (!is_file($root . '/' . $requiredRepositoryFile)) {
        $failures[] = "Required repository file is missing: {$requiredRepositoryFile}.";
    }
}

$routingGuardFixtures = [
    'tests/fixtures/routing-construction-traversal.php.fixture' => [],
    'tests/fixtures/routing-path-segment-traversal.php.fixture' => [],
    'tests/fixtures/routing-lookup-index-loop.php.fixture' => [
        'tests/fixtures/routing-lookup-index-loop.php.fixture:31 uses a loop in lookup-reachable Router method scanIndex; route lookup must remain indexed.',
    ],
    'tests/fixtures/routing-lookup-helper-loop.php.fixture' => [
        'tests/fixtures/routing-lookup-helper-loop.php.fixture:29 uses a loop in lookup-reachable Router method scanRoutes; route lookup must remain indexed.',
    ],
    'tests/fixtures/routing-lookup-traversal.php.fixture' => [
        'tests/fixtures/routing-lookup-traversal.php.fixture:25 calls traversal function array_find in lookup-reachable Router method findRoute; route lookup must remain indexed.',
        'tests/fixtures/routing-lookup-traversal.php.fixture:36 calls traversal function array_filter in lookup-reachable Router method filterMethods; route lookup must remain indexed.',
    ],
];

foreach ($routingGuardFixtures as $relativePath => $expectedFailures) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        $failures[] = "Cannot read routing guard fixture {$relativePath}.";
        continue;
    }

    $actualFailures = routingLookupFailures($contents, $relativePath);

    if ($actualFailures !== $expectedFailures) {
        $failures[] = sprintf(
            'Routing guard fixture diagnostics changed: %s. Expected %s; got %s.',
            $relativePath,
            json_encode($expectedFailures, JSON_THROW_ON_ERROR),
            json_encode($actualFailures, JSON_THROW_ON_ERROR),
        );
    }
}

$automatedBehaviorEvidenceMarkers = [
    '.ai/application-context.md' => 'no-op commands are not behavior evidence',
    'docs/consumer-contract.md' => '## Automated behavior evidence',
    'docs/decisions/010-framework-owned-consumer-check.md' => 'No generic checker can determine whether an arbitrary application-owned suite adequately proves the requested behavior.',
    'docs/getting-started.md' => 'Every observable behavior change must add or update automated tests.',
    'templates/application/AGENTS.md' => 'Every observable behavior change must add or update application-owned automated tests.',
    'templates/application/.ai/testing.md' => '## Automated behavior evidence',
    'templates/application/.ai/change-workflow.md' => 'automated behavior evidence must remain apparent to the next agent',
    'skeleton/AGENTS.md' => 'Every observable behavior change must add or update application-owned automated tests.',
    'skeleton/.ai/testing.md' => '## Automated behavior evidence',
    'skeleton/.ai/change-workflow.md' => 'automated behavior evidence must remain apparent to the next agent',
    'skeleton/README.md' => 'the application remains free to choose its test library, runner, and file placement',
];

foreach ($automatedBehaviorEvidenceMarkers as $relativePath => $marker) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents) || !str_contains($contents, $marker)) {
        $failures[] = "The mandatory automated-behavior-evidence contract is missing from {$relativePath}.";
    }
}

$alphaReleaseContractMarkers = [
    '.ai/README.md' => 'Prepare, assess, or publish a release',
    '.ai/application-context.md' => 'Keep Alpha scope approval separate from authorization to create tags',
    '.ai/testing.md' => 'The Git export comparison requires a clean worktree',
    'README.md' => 'Package availability and current release state are external facts',
    'RELEASING.md' => '## Alpha 1 release gate',
    'ROADMAP.md' => 'Alpha 1 publication state is external',
    'SECURITY.md' => 'Private vulnerability reports are still assessed on a best-effort basis.',
    'docs/getting-started.md' => 'Acceptance of the scope is not publication.',
    'docs/knowledge-map.md' => 'Assess or prepare a PHPThis release',
    'docs/decisions/018-bounded-alpha-1-release-scope.md' => 'Complete CRUD is not an Alpha 1 release prerequisite.',
    'tools/package-files.txt' => 'docs/decisions/018-bounded-alpha-1-release-scope.md',
];

foreach ($alphaReleaseContractMarkers as $relativePath => $marker) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents) || !str_contains($contents, $marker)) {
        $failures[] = "The accepted bounded Alpha 1 release contract is missing from {$relativePath}.";
    }
}

$alphaReleaseIdentityArtifactMarkers = [
    '.ai/README.md' => [
        '`docs/releases/0.1.0-alpha.1.md`',
    ],
    'RELEASING.md' => [
        '## Approved Alpha 1 identity',
        'Composer version: `0.1.0-alpha.1`',
        'Framework tag: `v0.1.0-alpha.1`',
        'Skeleton tag: `v0.1.0-alpha.1`',
        'The exact candidate commit, release date, and accountable-human publication authorization belong in the external release evidence',
        'This approval does not create or authorize creation of either tag, either package-host entry, either GitHub release, or the announcement.',
        'Alpha 1 must not be announced until the complete gate below passes',
    ],
    'docs/knowledge-map.md' => [
        '`docs/releases/0.1.0-alpha.1.md`',
    ],
    'docs/releases/0.1.0-alpha.1.md' => [
        'Release identity: `0.1.0-alpha.1`. Publication state is external',
        'external release evidence recorded with the release work item using the checklist in `RELEASING.md`',
        'They are intentionally not embedded in these tracked notes because changing them would produce a different candidate commit.',
        'The public `composer create-project --stability=alpha phpthis/skeleton` path is supported only when both packages are indexed',
        'It is not production-ready and makes no backward-compatibility promise across prereleases.',
    ],
    'docs/decisions/018-bounded-alpha-1-release-scope.md' => [
        'When this decision was accepted',
        'This decision does not record mutable publication state',
    ],
    'skeleton/README.md' => [
        'Package availability is an external fact',
        'A published artifact must be proved through `RELEASING.md`.',
    ],
    'tools/package-files.txt' => [
        'docs/releases/0.1.0-alpha.1.md',
    ],
];

foreach ($alphaReleaseIdentityArtifactMarkers as $relativePath => $markers) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        $failures[] = "Cannot read Alpha 1 identity artifact {$relativePath}.";
        continue;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $failures[] = "The approved Alpha 1 identity marker is missing from {$relativePath}.";
        }
    }
}

$mutableReleaseStateForbiddenMarkers = [
    'Status: unpublished; project state remains pre-alpha',
    'PHPThis is still pre-alpha.',
    'Until tagged packages are published',
    'It remains pre-alpha because neither',
    'Until every mandatory release gate passes, the public project status remains pre-alpha.',
    'The public artifact and skeleton path are still unproved.',
    'no alpha has been published',
    'path is intentionally unavailable until',
];

$mutableReleaseStateAuthorityFiles = [
    'README.md',
    'RELEASING.md',
    'ROADMAP.md',
    'SECURITY.md',
    'docs/getting-started.md',
    'docs/releases/0.1.0-alpha.1.md',
    'docs/decisions/018-bounded-alpha-1-release-scope.md',
    'skeleton/README.md',
];

foreach ($mutableReleaseStateAuthorityFiles as $relativePath) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        $failures[] = "Cannot read release-state authority {$relativePath}.";
        continue;
    }

    foreach ($mutableReleaseStateForbiddenMarkers as $marker) {
        if (str_contains($contents, $marker)) {
            $failures[] = "Mutable release-state claim remains in {$relativePath}: {$marker}";
        }
    }
}

$sessionContractMarkers = [
    '.ai/README.md' => '`.ai/session.md`',
    'docs/knowledge-map.md' => '`docs/sessions.md`',
    'templates/application/.ai/architecture.md' => '{{SESSION_ADOPTION_AND_KEY_SCHEMA_OR_NOT_APPLICABLE}}',
    'templates/application/.ai/operations.md' => '{{SESSION_NATIVE_FILE_STORAGE_POLICY_OR_NOT_APPLICABLE}}',
    'templates/application/.ai/testing.md' => 'Adopted session transport',
    'skeleton/.ai/README.md' => 'vendor/phpthis/framework/docs/sessions.md',
    'skeleton/.ai/operations.md' => 'ext-session',
    'skeleton/.ai/testing.md' => 'NOT_APPLICABLE(SESSION_EVIDENCE)',
];

foreach ($sessionContractMarkers as $relativePath => $marker) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents) || !str_contains($contents, $marker)) {
        $failures[] = "Session contract route or application-context field is missing from {$relativePath}.";
    }
}

$cacheContractMarkers = [
    '.ai/README.md' => '`.ai/cache.md`',
    '.ai/http.md' => '`.ai/cache.md`',
    'docs/knowledge-map.md' => '`docs/caching.md`',
    'templates/application/.ai/architecture.md' => '{{CACHE_ADOPTION_OR_NOT_APPLICABLE}}',
    'templates/application/.ai/operations.md' => '{{CACHE_RUNTIME_ADOPTION_OR_NOT_APPLICABLE}}',
    'templates/application/.ai/testing.md' => 'Adopted cache behavior',
    'skeleton/.ai/README.md' => 'vendor/phpthis/framework/docs/caching.md',
    'skeleton/.ai/architecture.md' => 'NOT_APPLICABLE(CACHE)',
    'skeleton/.ai/testing.md' => 'NOT_APPLICABLE(CACHE_EVIDENCE)',
];

foreach ($cacheContractMarkers as $relativePath => $marker) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents) || !str_contains($contents, $marker)) {
        $failures[] = "Cache contract route or application-context field is missing from {$relativePath}.";
    }
}

$cachePolicyArtifactMarkers = [
    '.ai/cache.md' => [
        'The framework currently provides no generic cache API',
        '## HTTP response caching',
        '## Server-side data caching',
    ],
    'docs/caching.md' => [
        'PHPThis has an accepted cache policy but no framework cache mechanism.',
        '`NOT_APPLICABLE(CACHE)`',
        'A warm cache is not evidence that a database path avoids N+1 queries.',
        'stale-refill race',
    ],
    'docs/decisions/016-cache-policy-before-cache-mechanism.md' => [
        'Status: accepted',
        'Framework-owned 404, 405, and unknown-failure 500 responses',
        'no cache client or backend dependency, generic cache API',
        'an explicit stale-refill policy',
    ],
    'templates/application/.ai/architecture.md' => [
        '{{HTTP_CACHE_POLICY_DECISION}}',
        '{{HTTP_CACHE_RESPONSE_POLICY}}',
        '{{CACHEABLE_RESPONSE_FRESHNESS_AND_REVALIDATION_POLICY}}',
    ],
    'templates/application/.ai/operations.md' => [
        '{{HTTP_CACHE_RUNTIME_POLICY}}',
    ],
    'templates/application/.ai/data.md' => [
        '{{CACHE_INVALIDATION_AND_STALE_REFILL_POLICY_OR_NOT_APPLICABLE}}',
    ],
    'templates/application/.ai/testing.md' => [
        'HTTP cache policy evidence',
        'a concurrent miss racing an authoritative write',
    ],
    'skeleton/.ai/README.md' => [
        'HTTP_CACHE_POLICY(NO_STORE)',
        'Cache-Control: no-store',
    ],
    'skeleton/.ai/testing.md' => [
        'HTTP_CACHE_EVIDENCE(NO_STORE)',
        'a concurrent miss racing an authoritative write',
    ],
    'tools/package-files.txt' => [
        'docs/caching.md',
        'docs/decisions/016-cache-policy-before-cache-mechanism.md',
    ],
];

foreach ($cachePolicyArtifactMarkers as $relativePath => $markers) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        $failures[] = "Cannot read cache policy artifact {$relativePath}.";
        continue;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $failures[] = "Cache policy artifact marker is missing from {$relativePath}.";
        }
    }
}

$routingArtifactMarkers = [
    '.ai/routing.md' => [
        '{name:positive-int}',
        '{name:token}',
        'at most two',
        'RouteMatch',
        'PathParameters',
        'Route::segments()',
        'must not scan the route list or an index collection',
    ],
    'docs/decisions/017-bounded-trailing-positive-integer-routes.md' => [
        'Status: accepted',
        '[1-9][0-9]*',
        'PHP_INT_MAX',
        'one parameter name',
        'does not claim Update or Delete support',
    ],
    'docs/decisions/019-bounded-multiple-typed-routes.md' => [
        'Status: accepted',
        '[A-Za-z0-9][A-Za-z0-9_-]{0,63}',
        'at most two',
        'Contract version 4',
        '2,300',
        'supersedes ADR 017 only',
    ],
    'example/src/Documents/DocumentRoutes.php' => [
        '/accounts/{account_id:positive-int}/documents/{document_key:token}',
    ],
    'example/src/Documents/GetDocument/GetDocumentHandler.php' => [
        "positiveInteger('account_id')",
        "token('document_key')",
        'AccountId::fromPositiveInteger',
        'DocumentKey::fromToken',
        "'Cache-Control' => 'private, no-store'",
    ],
    'example/src/Users/UserRoutes.php' => [
        '/users/{user_id:positive-int}',
    ],
    'example/src/Users/GetUser/GetUserHandler.php' => [
        "positiveInteger('user_id')",
        'UserId::fromPositiveInteger',
        'WHERE users.id = :user_id',
        "'Cache-Control' => 'no-store'",
    ],
    'tools/package-files.txt' => [
        'docs/decisions/017-bounded-trailing-positive-integer-routes.md',
        'docs/decisions/019-bounded-multiple-typed-routes.md',
        'src/Routing/PathParameters.php',
        'src/Routing/RouteMatch.php',
        'src/Routing/RouteParameterType.php',
        'src/Routing/RouteSegment.php',
    ],
];

foreach ($routingArtifactMarkers as $relativePath => $markers) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        $failures[] = "Cannot read typed routing artifact {$relativePath}.";
        continue;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $failures[] = "Typed routing artifact marker is missing from {$relativePath}.";
        }
    }
}

$requestPolicyArtifactMarkers = [
    '.ai/README.md' => [
        '`.ai/request-policy.md`',
    ],
    '.ai/request-policy.md' => [
        'authenticate -> resolve tenant -> authorize -> protected handler',
        'PHPThis provides no credential parser or verifier.',
        'Cache-Control: private, no-store',
    ],
    'docs/knowledge-map.md' => [
        '`docs/request-policy.md`',
    ],
    'docs/request-policy.md' => [
        'PHPThis keeps authentication, tenant resolution, and authorization application-owned.',
        'Missing, malformed, and rejected credentials map to one generic `401`',
        'Ordinary forbidden and cross-tenant decisions map to the same generic `403`.',
        'When a policy reads storage, give it a separately named connection, budget, and trace from protected handler work.',
    ],
    'docs/decisions/020-application-owned-request-policy.md' => [
        'Status: accepted',
        'adds no core runtime contract',
        'Consumer Contract version 4 and Strict Profile version 2 remain unchanged.',
        'No core PHP file, runtime dependency, Consumer Contract version, Strict Profile version, or PHPThis diagnostic changes.',
    ],
    'example/src/Documents/GetDocument/GetDocumentHandler.php' => [
        '$this->authenticate->authenticate($request)',
        '$this->resolveTenant->resolve($principal, $accountId)',
        '$this->authorize->authorize($principal, $tenant, $documentKey)',
        '$this->retrieve->retrieve(',
    ],
    'example/src/Documents/GetDocument/SelectAuthorizedDocument.php' => [
        'documents.account_id = :account_id',
        'documents.account_id = :resolved_tenant_account_id',
        'account_memberships.principal_id = :principal_id',
        'account_memberships.account_id = :membership_tenant_account_id',
    ],
    'example/bootstrap.php' => [
        'ApplicationDatabasePath::fromString(',
        'new ApplicationComposition($databasePath)',
        '->http()',
    ],
    'example/src/ApplicationComposition.php' => [
        'new DenyAllDocumentAuthentication()',
        'Unauthenticated::class => new Response(',
        'Forbidden::class => $forbiddenResponse',
        'CrossTenant::class => $forbiddenResponse',
    ],
    'tests/request-policy.php' => [
        'consumer replaces every document policy and passes explicit authority values',
        'permitted document policy keeps protected missing responses private and generic',
        'protected document query fails closed when requested and resolved tenants differ',
        'mapped document denials emit no sensitive log data',
        'unexpected document policy failures use the generic redacted boundary',
    ],
    'templates/application/.ai/request-policy.md' => [
        '{{REQUEST_POLICY_ADAPTER_PATH}}',
        '{{CREDENTIAL_PARSER_EVIDENCE_OR_LIMIT}}',
    ],
    'skeleton/.ai/request-policy.md' => [
        'NOT_APPLICABLE(REQUEST_POLICY)',
        'vendor/phpthis/framework/docs/request-policy.md',
    ],
    'tools/package-files.txt' => [
        'docs/request-policy.md',
        'docs/decisions/020-application-owned-request-policy.md',
        'templates/application/.ai/request-policy.md',
    ],
];

foreach ($requestPolicyArtifactMarkers as $relativePath => $markers) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        $failures[] = "Cannot read request-policy artifact {$relativePath}.";
        continue;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $failures[] = "Request-policy artifact marker is missing from {$relativePath}.";
        }
    }
}

$typedInputBoundaryArtifactMarkers = [
    '.ai/application-context.md' => [
        'every adopted inbound operation',
        '`NOT_APPLICABLE(INPUT)`',
    ],
    '.ai/types.md' => [
        'No normalization is implicit.',
        'Native `json_decode` does not expose duplicate object keys and retains the last value',
        'Consumer Contract v5 and Strict Profile v2 are current.',
    ],
    'docs/type-safety.md' => [
        'external mixed data -> named parser factory -> final readonly value -> native typed code',
        'Invalid input makes zero seam calls when one exists and cannot trigger operation-owned downstream I/O or mutation.',
        'A duplicate-key-aware parser requires a separate decision',
    ],
    'docs/getting-started.md' => [
        "each inbound operation's raw representation",
        '`NOT_APPLICABLE(INPUT)`',
    ],
    'docs/guardrails.md' => [
        'The typed-input guard retains ADR 021',
    ],
    'VISION.md' => [
        'at most one operation-specific typed seam',
    ],
    'docs/decisions/021-application-owned-typed-input-boundaries.md' => [
        'Status: accepted',
        'Each accepting operation owns one named parser factory',
        'This decision adds application-owned example evidence and authoring guidance only.',
        'Consumer Contract version 4 and Strict Profile version 2 remain unchanged.',
    ],
    'docs/decisions/013-optional-crud-reference-profile.md' => [
        'ADR 021 supersedes this record only where the earlier Create tree',
        'List remains handler-local after parsing its concrete `ListUsersPageRequest`',
    ],
    'example/src/Users/CreateUser/CreateUserCommand.php' => [
        'private function __construct(',
        'public static function fromJson(string $json): self',
        'array_key_exists(\'name\', $values)',
        'JSON_THROW_ON_ERROR',
        'FILTER_VALIDATE_EMAIL, 0',
    ],
    'example/src/Users/CreateUser/CreateUserHandler.php' => [
        '$command = CreateUserCommand::fromJson($request->body);',
        '$this->createUser->execute($command);',
    ],
    'example/src/Users/CreateUser/CreateUserOperation.php' => [
        'interface CreateUserOperation',
        'execute(CreateUserCommand $command): void',
    ],
    'example/src/Users/CreateUser/TransactionalCreateUser.php' => [
        'final readonly class TransactionalCreateUser implements CreateUserOperation',
        'public function execute(CreateUserCommand $command): void',
    ],
    'tests/run.php' => [
        'HTTP command parses one exact JSON object',
        'HTTP command exposes native duplicate-key last-value behavior',
        'HTTP command rejects malformed coercive and unknown input',
        'HTTP handler invokes only its typed create-user operation',
        'HTTP handler rejects invalid commands before use-case invocation',
        'mapped input failures emit no submitted data or log entry',
        '$expectedStatus = $case === \'exact_endpoint_overflow\' ? 413 : 400;',
        'example request boundary maps client failures before database work',
        'transactional user creation rejects invalid input before database work',
    ],
    'templates/application/.ai/architecture.md' => [
        '{{INPUT_BOUNDARY_ADOPTION_OR_NOT_APPLICABLE}}',
        '{{INPUT_OPERATION_1_FACTORY_AND_TYPE}}',
        'No normalization is implicit.',
    ],
    'templates/application/.ai/testing.md' => [
        '{{INPUT_BOUNDARY_TEST_COMMAND_OR_NOT_APPLICABLE}}',
        'no operation-owned downstream database work',
        'When a separate typed operation seam exists, assert zero calls.',
        'duplicate-key-aware contract requires a separately accepted parser decision',
    ],
    'skeleton/.ai/README.md' => [
        'NOT_APPLICABLE(INPUT)',
        'do not add a generic input guide or validation mechanism',
    ],
    'skeleton/.ai/architecture.md' => [
        'NOT_APPLICABLE(INPUT)',
        'operation-specific named parser factory',
    ],
    'skeleton/.ai/testing.md' => [
        'NOT_APPLICABLE(INPUT_EVIDENCE)',
        'no operation-owned downstream I/O or mutation',
        'zero typed-seam calls when one exists',
    ],
    'tools/package-files.txt' => [
        'docs/decisions/021-application-owned-typed-input-boundaries.md',
    ],
];

foreach ($typedInputBoundaryArtifactMarkers as $relativePath => $markers) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        $failures[] = "Cannot read typed-input-boundary artifact {$relativePath}.";
        continue;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $failures[] = "Typed-input-boundary artifact marker is missing from {$relativePath}.";
        }
    }
}

$finiteDataPathArtifactMarkers = [
    'docs/decisions/022-application-owned-finite-data-paths.md' => [
        'Status: accepted',
        'The protected document-list proof remains entirely application-owned.',
        'Consumer Contract version 4 and Strict Profile version 2 remain unchanged.',
        'eight complete application-owned statements',
        'an explicit empty list means an empty page and zero protected SQL',
        'each category is 1–64 bytes, valid UTF-8, and free of ASCII control bytes and DEL, with no normalization',
        'Cursor traversal is not a snapshot',
        'exercised only as SQLite-specific evidence by the repository\'s current PDO SQLite runtime',
        'not universal authentication, authorization, tenant-isolation, or row-security proof',
        'No ORM, query builder, repository, generic paginator, SQL/binding/placeholder helper, transaction callback, dialect abstraction, generated SQL, or dynamic SQL is accepted by this decision.',
        'No framework core, dependency, Consumer Contract version, Strict Profile version, or diagnostic changes.',
    ],
    'docs/consumer-contract.md' => [
        'ADR 022 records one finite SQLite application data path',
        'Consumer Contract version 5 carries Strict Profile version 2 forward unchanged.',
    ],
    'docs/guardrails.md' => [
        'The finite-data-path guard retains ADR 022',
        'three-driver harness remains PDO transport evidence only',
    ],
    'example/AGENTS.md' => [
        'complete raw engine-specific SQL visible',
        'complete SQL string and its explicit named parameter array together at that call site',
        'Do not add or use an ORM',
        'The document-list SQL is SQLite-specific application evidence.',
    ],
    'example/.ai/README.md' => [
        'evidence-oriented application context, not a traditional framework manual',
        'complete raw SQLite SQL and explicit named parameter arrays',
        'generic paginator',
    ],
    'example/.ai/data.md' => [
        'exactly one, two, or three category placeholders',
        'empty page, zero protected SQL',
        'Each accepted non-empty category is an exact 1–64-byte string',
        'v1:<order>:<sort_rank>:<document_key>',
        'traversal is not a snapshot',
        'MySQL and PostgreSQL are certified only for the base PDO transport harness.',
        'do not prove universal authorization',
    ],
    'example/src/Documents/DocumentRoutes.php' => [
        '/accounts/{account_id:positive-int}/documents',
        'new ListDocumentsHandler(',
    ],
    'example/src/Documents/ListDocuments/AuthorizeListDocuments.php' => [
        'interface AuthorizeListDocuments',
        'public function authorizeList(',
        'AuthenticatedPrincipal $principal',
        'ResolvedTenant $tenant',
    ],
    'example/src/Documents/ListDocuments/ListDocumentsPageRequest.php' => [
        'final readonly class ListDocumentsPageRequest',
        'if ($field !== \'order\' && $field !== \'categories\' && $field !== \'cursor\')',
        'return \'rank_asc\';',
        'count($submitted) > 3',
        "if (\$submitted === [''])",
        '$cursorOrder !== $order',
        '$cursorRank < 0 || $cursorRank > 1_000_000',
    ],
    'example/src/Documents/ListDocuments/DocumentSummary.php' => [
        'final readonly class DocumentSummary',
        'public static function fromDatabaseRow(array $row): self',
        'Document summary row must contain exactly document_key, title, category, and sort_rank.',
        '$parsed < 0 || $parsed > 1_000_000',
    ],
    'example/src/Documents/ListDocuments/ListDocumentsHandler.php' => [
        'private const int PAGE_SIZE = 50;',
        'private const int FETCH_LIMIT = self::PAGE_SIZE + 1;',
        '$pageRequest->categories === []',
        'documents.account_id = :requested_account_id',
        'documents.account_id = :resolved_tenant_account_id',
        'account_memberships.principal_id = :principal_id',
        'account_memberships.account_id = :membership_tenant_account_id',
        ':cursor_is_absent = 1',
        'documents.category IN (:category_1, :category_2, :category_3)',
        'ORDER BY documents.sort_rank ASC, documents.document_key COLLATE BINARY ASC',
        'ORDER BY documents.sort_rank DESC, documents.document_key COLLATE BINARY DESC',
        '\'cursor_primary_sort_rank\' => $cursorRank',
        '\'cursor_tie_sort_rank\' => $cursorRank',
        '\'cursor_document_key\' => $cursorDocumentKey',
        '\'cursor_is_absent\' => $cursorIsAbsent',
        '\'fetch_limit\' => self::FETCH_LIMIT',
        'DocumentSummary::fromDatabaseRow($row)',
        '\'next_cursor\' => $nextCursor',
    ],
    'tests/request-policy.php' => [
        'document list page request accepts only finite orders categories and canonical composite cursors',
        'document list page request rejects adversarial shapes and malformed cursors before SQL',
        'protected document list preserves policy order and rejects denials before SQL',
        'protected document list passes typed authority and rejects invalid query before protected SQL',
        'document list executes eight finite raw SQL branches and empty filters use zero SQL',
        'document list binds SQL-shaped category data and preserves tenant isolation',
        'document list composite cursor covers exact lookahead and stable 125-document traversal',
        'document list page keeps one statement and fingerprint across fixture sizes',
        'document list source uses direct raw SQL without ORM binding or pagination helpers',
    ],
    'templates/application/.ai/data.md' => [
        'finite code-owned fragments are necessary',
        'every bounded list or cursor',
    ],
    'templates/application/.ai/testing.md' => [
        'Every adopted cursor or bounded list proves its recorded omitted and empty-input behavior',
        'not universal authorization, tenant-isolation, or SQL-injection proof',
    ],
    'skeleton/.ai/data.md' => [
        'finite code-owned mapping',
        "cursor's version, stable tie-break and snapshot policy",
    ],
    'skeleton/.ai/testing.md' => [
        'exact zero- versus non-zero-statement bounds',
        'base PDO transport evidence as application-SQL certification',
    ],
    'tools/package-files.txt' => [
        'docs/decisions/022-application-owned-finite-data-paths.md',
    ],
];

foreach ($finiteDataPathArtifactMarkers as $relativePath => $markers) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        $failures[] = "Cannot read finite-data-path artifact {$relativePath}.";
        continue;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $failures[] = "Finite-data-path artifact marker is missing from {$relativePath}.";
        }
    }
}

$observabilityArtifactMarkers = [
    '.ai/README.md' => [
        '`.ai/observability.md`',
        'ADR 023',
    ],
    '.ai/observability.md' => [
        'application.request_summary',
        'at most eight finite code-owned database sources',
        'exactly one sink invocation attempt',
        'Never claim durable delivery',
    ],
    'docs/consumer-contract.md' => [
        'ADR 023 defines the mandatory request-level observability boundary',
        'application.request_summary',
        'at most eight database sources',
        'make exactly one sink invocation attempt',
        'Exactly one sink invocation attempt is not durable delivery.',
    ],
    'docs/knowledge-map.md' => [
        '`docs/observability/README.md`',
        'ADR 023',
    ],
    'docs/logging.md' => [
        '[0-9a-f]{32}',
        '`application.request_summary`',
        'at most eight explicitly registered `database_sources`',
        "anonymous-class runtime name embeds source path and line",
        'make exactly one sink invocation attempt',
        'not durable delivery',
        '`phpthis.request.unhandled`',
    ],
    'docs/observability/README.md' => [
        'ADR 023 is the accepted decision',
        '`tests/observability.php`',
    ],
    'docs/observability/correlation-id.md' => [
        '[0-9a-f]{32}',
        'X-Request-ID',
        'TerminalRequestCoordinator::handle',
    ],
    'docs/observability/database-evidence.md' => [
        'at most eight unique names',
        'no two sources share a `QueryBudget` or `QueryTrace`',
        'A rejected over-budget call sets exceeded state',
    ],
    'docs/observability/event-schema.md' => [
        'version-1 `application.request_summary` schema',
        'Known denials gain no denial-specific field',
        'anonymous throwable uses its nearest named parent',
    ],
    'docs/observability/sink-failure.md' => [
        'exactly one synchronous sink invocation attempt',
        'An invocation attempt is not durable delivery.',
    ],
    'docs/observability/testing.md' => [
        '`tests/observability.php`',
        'exactly one sink invocation attempt',
        'They do not prove durable storage',
    ],
    'docs/decisions/023-application-owned-terminal-request-summaries.md' => [
        'Status: accepted',
        'Consumer Contract version 5 carries Strict Profile version 2 forward unchanged.',
        '[0-9a-f]{32}',
        'application.request_summary',
        'at most eight entries',
        'exactly one sink invocation attempt',
        'does not mean durable delivery',
        '`phpthis.request.unhandled`',
        'No ORM, repository, query builder, SQL generator, SQL/binding/placeholder helper, logger facade, global helper, middleware, event pipeline, discovery mechanism, or hidden database instrumentation is accepted by this decision.',
    ],
    'docs/decisions/README.md' => [
        '023-application-owned-terminal-request-summaries.md',
    ],
    'verification/ApplicationChecker.php' => [
        "'.ai/observability.md',",
    ],
    'tools/test-consumer-project.php' => [
        'proveObservabilityContextIsRequired(',
        'Required application context file is missing: .ai/observability.md.',
    ],
    'src/Database/QueryBudget.php' => [
        'private bool $exceeded = false;',
        '$this->exceeded = true;',
        'public function exceeded(): bool',
    ],
    'src/Http/UnknownFailureBoundary.php' => [
        'public function respond(): Response',
    ],
    'example/.ai/observability.md' => [
        '`list_users`, `get_user`, `create_user`, `get_document`, and `list_documents`',
        'one attempt is not durable delivery',
    ],
    'example/bootstrap.php' => [
        'ApplicationDatabasePath::fromString(',
        'new ApplicationComposition($databasePath)',
        '->http()',
    ],
    'example/src/ApplicationComposition.php' => [
        'return new TerminalRequestCoordinator(',
        'CorrelationId::generate()',
        "new QuerySummarySource('list_users'",
        "new QuerySummarySource('get_user'",
        "new QuerySummarySource('create_user'",
        "new QuerySummarySource('get_document'",
        "'list_documents',",
    ],
    'example/public/index.php' => [
        '$coordinator->handle($_SERVER, $_GET)',
    ],
    'example/src/Observability/CorrelationId.php' => [
        'bin2hex(random_bytes(16))',
    ],
    'example/src/Observability/QuerySummarySource.php' => [
        "'budget_exceeded' => \$this->budget->exceeded(),",
        'sharesObservationStateWith',
    ],
    'example/src/Observability/RequestSummary.php' => [
        "public const string EVENT = 'application.request_summary';",
        "'database_sources' => \$this->querySources,",
        'private static function saturatedAdd',
        'private static function safeFailureClass',
        "str_contains(\$class, '@anonymous')",
    ],
    'example/src/Observability/ErrorLogRequestSummarySink.php' => [
        'JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES',
        'error_log($encoded)',
    ],
    'example/src/Observability/TerminalRequestCoordinator.php' => [
        'private const int MAXIMUM_QUERY_SOURCES = 8;',
        '$this->summarySink->emit($summary);',
        '$headers[\'X-Request-ID\'] = $this->correlationId->value;',
    ],
    'templates/application/.ai/observability.md' => [
        '{{TERMINAL_REQUEST_SUMMARY_COORDINATOR_PATH}}',
        '{{TERMINAL_SUMMARY_DATABASE_SOURCES_OR_EMPTY}}',
        '{{TERMINAL_SUMMARY_TEST_COMMAND}}',
        'One invocation attempt never means durable delivery.',
    ],
    'skeleton/.ai/observability.md' => [
        '`NOT_APPLICABLE(no database)`',
        'delivery is not guaranteed',
    ],
    'skeleton/bootstrap.php' => [
        'return new TerminalRequestCoordinator(',
        'CorrelationId::generate()',
        'new ErrorLogRequestSummarySink()',
    ],
    'skeleton/public/index.php' => [
        '$coordinator->handle($_SERVER, $_GET)',
    ],
    'skeleton/src/Observability/CorrelationId.php' => [
        'bin2hex(random_bytes(16))',
    ],
    'skeleton/src/Observability/RequestSummary.php' => [
        "public const string EVENT = 'application.request_summary';",
        "'database_sources' => \$this->querySources,",
        'private static function safeFailureClass',
        "str_contains(\$class, '@anonymous')",
    ],
    'skeleton/src/Observability/ErrorLogRequestSummarySink.php' => [
        'JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES',
        'error_log($encoded)',
    ],
    'skeleton/src/Observability/TerminalRequestCoordinator.php' => [
        'private const int MAXIMUM_QUERY_SOURCES = 8;',
        '$this->summarySink->emit($summary);',
        '$headers[\'X-Request-ID\'] = $this->correlationId->value;',
    ],
    'skeleton/tests/run.php' => [
        'Runtime GET /health must expose one generated correlation ID.',
        'Each terminal coordinator must expose fresh request-scoped state.',
    ],
    'tests/run.php' => [
        "require __DIR__ . '/observability.php';",
        'foreach (observabilityTests() as $name => $test)',
    ],
    'tests/observability.php' => [
        'correlation IDs are generated with 128 random bits in canonical form',
        'terminal coordinator emits one success summary and owns the response request ID',
        'default error-log sink serializes exactly one closed request summary',
        'terminal coordinator emits one status-only summary for every mapped or routed failure',
        'terminal coordinator emits one class-only summary for an unknown failure',
        "str_contains(\$encoded, '@anonymous')",
        'terminal coordinator reports repeated exact SQL without retaining SQL or bindings',
        'terminal coordinator aggregates ordered sources failures and bounded trace truncation',
        'terminal coordinator distinguishes exact budget use from one rejected attempt',
        'terminal coordinator keeps success and unknown responses unchanged when the sink throws',
        'terminal request summary excludes request response database and exception secrets',
        'query summary sources are finite uniquely named and connection local',
        'sequential terminal requests use fresh IDs budgets and traces',
    ],
    'tools/package-files.txt' => [
        'docs/decisions/023-application-owned-terminal-request-summaries.md',
        'docs/observability/README.md',
        'docs/observability/correlation-id.md',
        'docs/observability/database-evidence.md',
        'docs/observability/event-schema.md',
        'docs/observability/sink-failure.md',
        'docs/observability/testing.md',
        'templates/application/.ai/observability.md',
    ],
];

foreach ($observabilityArtifactMarkers as $relativePath => $markers) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        $failures[] = "Cannot read observability artifact {$relativePath}.";
        continue;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $failures[] = "Observability artifact marker is missing from {$relativePath}.";
        }
    }
}

$durableJobArtifactMarkers = [
    '.ai/README.md' => [
        'Add or change durable deferred work',
        '`.ai/jobs.md`',
        'ADR 024',
    ],
    '.ai/jobs.md' => [
        '# Durable jobs contract',
        'same `Connection`, in the same explicit SQLite transaction',
        'Treat delivery as at-least-once.',
        'claim and finalize zero or one delivery',
        'generic framework command map',
        'Do not add an ORM',
    ],
    '.ai/testing.md' => [
        'exact finite retry delays from freshly observed failure time',
        'completion rollback when handler time reaches lease expiry',
    ],
    'docs/jobs.md' => [
        'one accepted durable-job recipe and no framework queue mechanism',
        'This is at-least-once delivery.',
        'one finite complete `UPDATE ... RETURNING` statement',
        'claim-time snapshot is not sufficient',
        'PHPThis ships no job or envelope type',
    ],
    'docs/jobs/README.md' => [
        'Durable-job knowledge index',
        'SQLite schema',
    ],
    'docs/jobs/envelope.md' => [
        'bounded untrusted input',
        'Dispatch is an exhaustive finite `match`',
    ],
    'docs/jobs/lifecycle.md' => [
        'same `Connection`, explicit transaction, and SQLite database',
        'freshly observed transition time',
    ],
    'docs/jobs/operations.md' => [
        'Each invocation creates a fresh connection',
        'repository proves behavior on file-backed fixtures',
    ],
    'docs/jobs/schema.md' => [
        'SQLite `STRICT` tables',
        'partial index',
        'PHPThis supplies no migration runner',
    ],
    'docs/jobs/testing.md' => [
        'real worker subprocess terminated after claim',
        'sample it again before every fenced transition',
    ],
    'docs/decisions/024-application-owned-sqlite-durable-jobs.md' => [
        'Status: accepted',
        'Consumer Contract version 5 and Strict Profile version 2 remain unchanged.',
        'entirely application-owned and SQLite-specific',
        'claims at most one due job',
        'Delivery is at least once.',
        'No Consumer Contract, Strict Profile, framework core, generic job lifecycle, reusable worker API, or cross-engine queue claim is introduced.',
    ],
    'docs/consumer-contract.md' => [
        '## Optional application-owned durable jobs',
        'Contract version 5 does not make that additional file a checker requirement',
        'Delivery remains at least once.',
    ],
    'docs/decisions/README.md' => [
        '024-application-owned-sqlite-durable-jobs.md',
    ],
    'docs/getting-started.md' => [
        '`NOT_APPLICABLE(JOBS)` in `.ai/jobs.md`',
        'fresh-time lease fencing',
    ],
    'docs/guardrails.md' => [
        'The durable-job guard retains ADR 024',
        'continued absence from framework core and package runtime APIs',
    ],
    'docs/knowledge-map.md' => [
        '`docs/jobs.md`, `docs/security.md`',
        'verify that no framework queue mechanism exists',
    ],
    'docs/security.md' => [
        'Treat every stored job envelope as untrusted input',
        '## Durable-job limits',
        'do not prove exactly-once execution',
    ],
    'docs/vocabulary.md' => [
        '| durable-job envelope |',
        '| commit-visible job publication |',
        '| one-shot worker |',
        '| at-least-once delivery |',
        '| dead letter |',
    ],
    'README.md' => [
        'Durable deferred work begins with one application-owned SQLite recipe',
        'without adding a framework queue or exactly-once claim',
    ],
    'ROADMAP.md' => [
        'ADR 024 accepts one application-owned SQLite durable-job proof',
        'ADR 024 accepts one SQLite-specific application recipe, not core job, worker, dispatcher, broker, or exactly-once contracts',
    ],
    'example/.ai/README.md' => [
        'Change durable-job publication, envelopes, worker lifecycle, retries, or dead letters',
        '`.ai/jobs.md`, `.ai/data.md`, `.ai/observability.md`',
    ],
    'example/.ai/data.md' => [
        '## Durable-job tables',
        '`application_jobs` and `welcome_deliveries`',
        'No document-list or durable-job application SQL is certified on those engines.',
    ],
    'example/.ai/jobs.md' => [
        'The executable example follows ADR 024',
        'Every lease lasts 30 seconds.',
        'At most three claimed deliveries are permitted',
        'both console commands emit one redacted result with the recorded exit and stream contract',
    ],
    'example/src/Jobs/README.md' => [
        'application-owned evidence for ADR 024',
        'fresh-time lease fencing',
    ],
    'example/src/Jobs/UserWelcomeJobEnvelope.php' => [
        "public const string TYPE = 'user.welcome';",
        'public static function fromStored(string $jobId, string $json): self',
        'hash_equals(self::idempotencyKeyForEmail($email), $idempotencyKey)',
    ],
    'example/src/Jobs/UserWelcomeJobClock.php' => [
        'interface UserWelcomeJobClock',
        'public function now(): int;',
    ],
    'example/src/Jobs/SystemUserWelcomeJobClock.php' => [
        'final readonly class SystemUserWelcomeJobClock implements UserWelcomeJobClock',
        'return time();',
    ],
    'example/src/Jobs/SqliteUserWelcomeJobWorker.php' => [
        'public function runOne(string $leaseToken): UserWelcomeJobOutcome',
        '$claimNow = $this->currentTime(0);',
        '$completionNow = $this->currentTime($handlerNow);',
        'UPDATE application_jobs',
        'AND lease_expires_at > :completion_checked_at',
        'lease_expired_after_final_attempt',
    ],
    'example/src/Jobs/RecordUserWelcomeDelivery.php' => [
        'ON CONFLICT (idempotency_key) DO NOTHING',
    ],
    'example/src/Users/CreateUser/TransactionalCreateUser.php' => [
        '$job = UserWelcomeJobEnvelope::forEmail($command->email);',
        'INSERT INTO application_jobs (',
        '$this->connection->commit();',
    ],
    'example/src/Cli/ApplicationCommands.php' => [
        'private function runOneJob(): ApplicationCommandOutcome',
        '$worker->runOne(bin2hex(random_bytes(16)))',
        'new QueryBudget(3)',
        'new QueryTrace(3)',
    ],
    'example/bin/console.php' => [
        'new SystemUserWelcomeJobClock()',
        '"{\"error\":\"command_failed\"}\n"',
    ],
    'tests/run.php' => [
        "require __DIR__ . '/jobs.php';",
        'foreach (jobTests() as $name => $test)',
        'transactional user creation publishes one job with three writes across dataset sizes',
    ],
    'tests/jobs.php' => [
        'durable job publication rolls back business event and job together',
        'durable job worker is idle and keeps three statements across queue sizes',
        'durable job samples fresh time before dispatch and skips an expired lease',
        'durable job completion samples fresh time and rejects an expired lease',
        'durable job retry backoff starts from freshly observed failure time',
        'durable job subprocess crash is fenced and safely redelivered after lease expiry',
    ],
    'tests/cli.php' => [
        'jobs run-one command handles at most one delivery in each fresh process',
        'schedule run uses explicit UTC five-minute slots and handles at most one delivery',
    ],
    'tests/job-worker-crash.php' => [
        'fwrite(STDOUT, "READY\\n")',
        'sleep(60);',
    ],
    'tools/setup-example.php' => [
        'CREATE TABLE IF NOT EXISTS application_jobs',
        'CREATE INDEX IF NOT EXISTS application_jobs_available_due_idx',
        'CREATE INDEX IF NOT EXISTS application_jobs_expired_lease_idx',
        'CREATE TABLE IF NOT EXISTS welcome_deliveries',
    ],
    'templates/application/.ai/jobs.md' => [
        '{{JOBS_ADOPTION_OR_NOT_APPLICABLE}}',
        '{{JOBS_WORKER_LIFECYCLE_OR_NOT_APPLICABLE}}',
        'PHPThis provides no core queue or worker API.',
    ],
    'templates/application/.ai/testing.md' => [
        'exact retry delays from freshly observed failure time',
        'completion rollback when handler time reaches lease expiry',
    ],
    'skeleton/.ai/jobs.md' => [
        '`NOT_APPLICABLE(JOBS)`',
        'Never claim cross-connection atomicity or exactly-once external effects.',
    ],
    'skeleton/.ai/testing.md' => [
        '`NOT_APPLICABLE(JOBS_EVIDENCE)`',
        'completion rollback when handler time reaches lease expiry',
    ],
    'skeleton/.ai/operations.md' => [
        '## Durable-job runtime',
        'fresh one-delivery processes',
    ],
    'tools/package-files.txt' => [
        'docs/decisions/024-application-owned-sqlite-durable-jobs.md',
        'docs/jobs/schema.md',
        'templates/application/.ai/jobs.md',
    ],
];

foreach ($durableJobArtifactMarkers as $relativePath => $markers) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        $failures[] = "Cannot read durable-job artifact {$relativePath}.";
        continue;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $failures[] = "Durable-job artifact marker is missing from {$relativePath}.";
        }
    }
}

foreach (['src/Jobs', 'src/Queue'] as $forbiddenCoreDirectory) {
    if (is_dir($root . '/' . $forbiddenCoreDirectory)) {
        $failures[] = "Durable-job runtime must remain application-owned outside {$forbiddenCoreDirectory}.";
    }
}

$applicationChecker = file_get_contents($root . '/verification/ApplicationChecker.php');

if (is_string($applicationChecker) && str_contains($applicationChecker, "'.ai/jobs.md',")) {
    $failures[] = 'Contract version 5 must not checker-require the optional durable-job context file.';
}

$consumerProjectProof = file_get_contents($root . '/tools/test-consumer-project.php');

if (is_string($consumerProjectProof) && str_contains($consumerProjectProof, 'proveJobsContextIsRequired')) {
    $failures[] = 'Contract version 5 must not reject an existing consumer only because .ai/jobs.md is absent.';
}

$durableJobPackageInventory = file_get_contents($root . '/tools/package-files.txt');

if (
    is_string($durableJobPackageInventory)
    && preg_match('/^src\/(?:Jobs|Queue)\//m', $durableJobPackageInventory) === 1
) {
    $failures[] = 'Application-owned durable-job runtime must remain outside the framework package API.';
}

$applicationCliArtifactMarkers = [
    '.ai/README.md' => [
        'Add or change an application command or scheduled pass',
        '`.ai/cli.md`, `.ai/jobs.md`',
        'ADR 025',
    ],
    '.ai/application-context.md' => [
        '`NOT_APPLICABLE(CLI)`',
        'installed `vendor/phpthis/framework/docs/cli.md`',
        'framework-owned check',
    ],
    '.ai/cli.md' => [
        '# Application CLI and scheduler contract',
        'PHPThis provides no core CLI command, command map, argument parser, scheduler, lock, daemon, or process manager.',
        'Reject an unknown command separately from invalid, duplicate, misplaced, oversized, or unsupported arguments before application I/O.',
        'HTTP and CLI may share immutable configuration and explicit application construction code',
        'same-host scheduled pass with one application-private nonblocking exclusive `flock`',
    ],
    '.ai/testing.md' => [
        'execute its real console in fresh subprocesses',
        'explicit-clock cadence boundaries',
        'Do not mock a generic console or scheduler',
    ],
    'docs/cli.md' => [
        '# Application CLI and scheduler',
        'PHPThis accepts one application-owned operational console pattern and provides no core command or scheduler API.',
        'php example/bin/console.php <jobs:run-one|schedule:run> [--database=/absolute/path]',
        'intdiv(epoch_seconds, 60) % 5 === 0',
        'flock(LOCK_EX | LOCK_NB)',
        '`Example\\ApplicationComposition`',
        '## Unsupported boundary',
    ],
    'docs/cli/README.md' => [
        '# Application CLI knowledge index',
        'Arguments and output',
        'Scheduling and locking',
        'Composition',
        'Testing',
    ],
    'docs/cli/arguments-output.md' => [
        '# CLI arguments and output',
        'Unknown command and invalid, duplicate, reordered, alternate, or extra arguments fail before application I/O.',
        '`command`, then `outcome`',
    ],
    'docs/cli/composition.md' => [
        '# CLI composition',
        'HTTP and CLI share only immutable application configuration and visible construction code.',
        'not a container, service locator, registry, generic factory, framework extension point, or global',
    ],
    'docs/cli/scheduling-locking.md' => [
        '# CLI scheduling and locking',
        'intdiv(epoch_seconds, 60) % 5 === 0',
        'Sequential invocations in the same due minute are not deduplicated',
    ],
    'docs/cli/testing.md' => [
        '# CLI testing',
        'For production adoption, execute the real application console in fresh subprocesses.',
        'The current example proof is intentionally narrower',
        'It does not inject lock-operation or arbitrary throwable failures.',
    ],
    'docs/consumer-contract.md' => [
        '## Optional application-owned CLI and scheduler',
        'Contract-version-5-compatible optional application clarification, not a new checker requirement',
        'Consumer Contract version 5 carries Strict Profile version 2 forward unchanged.',
    ],
    'docs/decisions/025-application-owned-explicit-cli-and-scheduler.md' => [
        'Status: accepted',
        'Consumer Contract version 5 and Strict Profile version 2 remain unchanged.',
        'PHPThis adds no core command, command interface, registry, argument parser, scheduler, clock, lock, daemon, process manager, service-container integration, or command discovery.',
        'intdiv(epoch_seconds, 60) % 5 === 0',
        'nonblocking exclusive `flock`',
        'No framework core, Consumer Contract version, Strict Profile version, diagnostic, checker rule, durable-job guarantee, or distributed-coordination claim changes.',
    ],
    'docs/decisions/README.md' => [
        '025-application-owned-explicit-cli-and-scheduler.md',
    ],
    'docs/knowledge-map.md' => [
        'Add or assess an operational application command or scheduled pass',
        '`docs/cli.md`',
        'no framework CLI or scheduler API exists',
    ],
    'README.md' => [
        'php example/bin/console.php jobs:run-one',
        'php example/bin/console.php schedule:run',
        'Application CLI and scheduler',
    ],
    'ROADMAP.md' => [
        'ADR 025 accepts one application-owned explicit console and cron-friendly scheduled pass',
        'not core CLI, scheduler, daemon, persistent slot, catch-up, process-manager, or distributed-coordination contracts',
    ],
    'example/.ai/README.md' => [
        'Change an application command, argument, exit, stream, cadence, or overlap policy',
        '`bin/console.php`, `ApplicationComposition`, `src/Cli/`',
    ],
    'example/.ai/cli.md' => [
        '# Example application CLI and scheduler context',
        'php example/bin/console.php jobs:run-one [--database=/absolute/path]',
        'php example/bin/console.php schedule:run [--database=/absolute/path]',
        'intdiv(epochSeconds, 60) % 5 === 0',
        'nonblocking exclusive `flock`',
        'No live connection, budget, trace, request, session, correlation ID, or mutable clock is shared between HTTP and CLI',
    ],
    'example/src/Cli/README.md' => [
        '# Example application CLI source',
        'application-owned evidence for ADR 025, not PHPThis core runtime code',
        '`example/bin/console.php` is the only operational entrypoint',
        'Do not add command discovery, dynamic class or service resolution, a second console, generic parser or scheduler facade, daemon, polling loop, subprocess recursion, persistent slot ledger, catch-up, or distributed-coordination claim.',
    ],
    'example/AGENTS.md' => [
        'Keep `bin/console.php` as the sole application operational console.',
        'Do not add another entrypoint, command discovery, a service container, scheduler facade, daemon, persistent slot ledger, catch-up, or distributed-coordination claim.',
    ],
    'templates/application/.ai/cli.md' => [
        '{{CLI_ADOPTION_OR_NOT_APPLICABLE}}',
        '{{CLI_COMMAND_MAP_AND_BOUNDS_OR_NOT_APPLICABLE}}',
        '{{CLI_OVERLAP_POLICY_OR_NOT_APPLICABLE}}',
        'PHPThis provides no core application CLI or scheduler API',
    ],
    'skeleton/.ai/cli.md' => [
        '`NOT_APPLICABLE(CLI)`',
        'Keep framework `phpthis` dedicated to `check`.',
        'Do not add command discovery, class-name dispatch, a service-container resolver, generic console or scheduler facade, daemon, hidden loop, or distributed-coordination claim.',
    ],
    'skeleton/.ai/rules.md' => [
        'Keep `NOT_APPLICABLE(CLI)` until one operational application console',
        'Do not add application commands to framework `phpthis`',
    ],
    'skeleton/AGENTS.md' => [
        '`NOT_APPLICABLE(CLI)`',
        'Do not add application commands to `vendor/bin/phpthis`',
    ],
    'tools/package-files.txt' => [
        'docs/cli.md',
        'docs/cli/testing.md',
        'docs/decisions/025-application-owned-explicit-cli-and-scheduler.md',
        'templates/application/.ai/cli.md',
    ],
    'example/bootstrap.php' => [
        'new ApplicationComposition($databasePath)',
        '->http()',
    ],
    'example/src/ApplicationComposition.php' => [
        'final readonly class ApplicationComposition',
        'public function http(): TerminalRequestCoordinator',
        'public function commands(UserWelcomeJobClock $clock): ApplicationCommands',
        "new LocalScheduleLock(\$this->databasePath . '.schedule.lock')",
    ],
    'example/src/ApplicationDatabasePath.php' => [
        'strlen($value) > 4_096',
        "str_ends_with(\$value, '\\\\')",
        "preg_match('/[\\x00-\\x1F\\x7F]/', \$value)",
    ],
    'example/src/Cli/ApplicationCommandName.php' => [
        "case JobsRunOne = 'jobs:run-one';",
        "case ScheduleRun = 'schedule:run';",
    ],
    'example/src/Cli/ApplicationCommandOutcome.php' => [
        "case Idle = 'idle';",
        "case Completed = 'completed';",
        "case RetryScheduled = 'retry_scheduled';",
        "case DeadLettered = 'dead_lettered';",
        "case NotDue = 'not_due';",
        "case OverlapSkipped = 'overlap_skipped';",
    ],
    'example/src/Cli/ApplicationCommandLine.php' => [
        "str_starts_with(\$arguments[1], '--')",
        'ApplicationCommandName::tryFrom($arguments[1])',
        'count($arguments) > 3',
        "str_starts_with(\$submitted, '--database=')",
        'ApplicationDatabasePath::fromString($databasePath)',
    ],
    'example/src/Cli/ApplicationCommands.php' => [
        'return match ($command)',
        'intdiv($this->clock->now(), 60)',
        '$currentMinute % 5 !== 0',
        'if (!$this->scheduleLock->acquire())',
        '$this->scheduleLock->release();',
        'private function runOneJob(): ApplicationCommandOutcome',
    ],
    'example/src/Cli/LocalScheduleLock.php' => [
        "fopen(\$this->path, 'c+b')",
        'flock($handle, LOCK_EX | LOCK_NB, $wouldBlock)',
        'if ($wouldBlock === 1)',
        'flock($handle, LOCK_UN)',
    ],
    'example/bin/console.php' => [
        'ApplicationCommandLine::fromArguments(',
        '->commands(new SystemUserWelcomeJobClock())',
        '"{\"error\":\"unknown_command\"}\n"',
        '"{\"error\":\"invalid_arguments\"}\n"',
        '"{\"error\":\"command_failed\"}\n"',
    ],
    'tests/cli.php' => [
        'application console rejects unknown commands before database work',
        'application console rejects every invalid argument shape before database work',
        'application command parser accepts exactly 4096 absolute path bytes',
        'application console reports missing databases as one redacted operational failure',
        'jobs run-one command handles at most one delivery in each fresh process',
        'schedule run uses explicit UTC five-minute slots and handles at most one delivery',
        'schedule run skips a subprocess-held same-host lock without blocking or delivering',
        'application composition keeps CLI execution outside fresh HTTP request state',
    ],
    'tests/cli-schedule-lock-holder.php' => [
        "fwrite(STDOUT, \"READY\\n\")",
        "flock(\$handle, LOCK_EX | LOCK_NB)",
        "\$databasePath . '.schedule.lock'",
    ],
];

foreach ($applicationCliArtifactMarkers as $relativePath => $markers) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        $failures[] = "Cannot read application CLI artifact {$relativePath}.";
        continue;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $failures[] = "Application CLI artifact marker is missing from {$relativePath}.";
        }
    }
}

if (is_file($root . '/example/bin/run-one-job.php')) {
    $failures[] = 'The superseded one-shot job entrypoint must not coexist with the explicit application command map.';
}

foreach (['src/Cli', 'src/Command', 'src/Commands', 'src/Scheduler'] as $forbiddenCoreDirectory) {
    if (is_dir($root . '/' . $forbiddenCoreDirectory)) {
        $failures[] = "Application CLI and schedule runtime must remain outside framework core: {$forbiddenCoreDirectory}.";
    }
}

$applicationCliPackageInventory = file_get_contents($root . '/tools/package-files.txt');

if (
    is_string($applicationCliPackageInventory)
    && preg_match('/^src\/(?:Cli|Command|Commands|Scheduler)\//m', $applicationCliPackageInventory) === 1
) {
    $failures[] = 'Application CLI and schedule runtime must remain outside the framework package API.';
}

$frameworkEntrypoint = file_get_contents($root . '/bin/phpthis');

if (is_string($frameworkEntrypoint)) {
    if (!str_contains($frameworkEntrypoint, 'Usage: phpthis check [--debug]')) {
        $failures[] = 'The framework entrypoint must retain its check-only usage contract.';
    }

    foreach (['jobs:run-one', 'schedule:run'] as $applicationCommand) {
        if (str_contains($frameworkEntrypoint, $applicationCommand)) {
            $failures[] = "The application command {$applicationCommand} must not enter bin/phpthis.";
        }
    }
}

$composerManifest = file_get_contents($root . '/composer.json');

if (is_string($composerManifest) && str_contains($composerManifest, 'example/bin/console.php')) {
    $failures[] = 'The application console must not be exported as a framework Composer binary.';
}

if (is_string($applicationChecker) && str_contains($applicationChecker, "'.ai/cli.md',")) {
    $failures[] = 'Contract version 5 must not checker-require the optional application CLI context file.';
}

if (is_string($consumerProjectProof) && str_contains($consumerProjectProof, 'proveCliContextIsRequired')) {
    $failures[] = 'Contract version 5 must not reject an existing consumer only because .ai/cli.md is absent.';
}

$applicationCliSourceFiles = [
    'example/bin/console.php',
    'example/src/Cli/ApplicationCommandExecution.php',
    'example/src/Cli/ApplicationCommandLine.php',
    'example/src/Cli/ApplicationCommandName.php',
    'example/src/Cli/ApplicationCommandOutcome.php',
    'example/src/Cli/ApplicationCommands.php',
    'example/src/Cli/InvalidApplicationCommandArguments.php',
    'example/src/Cli/LocalScheduleLock.php',
    'example/src/Cli/UnknownApplicationCommand.php',
];
$forbiddenApplicationCliMarkers = [
    'class_exists(',
    'get_declared_classes(',
    'glob(',
    'scandir(',
    'DirectoryIterator',
    'ReflectionClass',
    'ContainerInterface',
    'ServiceLocator',
    'sleep(',
    'usleep(',
];

foreach ($applicationCliSourceFiles as $relativePath) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        continue;
    }

    foreach ($forbiddenApplicationCliMarkers as $marker) {
        if (str_contains($contents, $marker)) {
            $failures[] = "Application CLI source {$relativePath} contains forbidden discovery container or daemon marker {$marker}.";
        }
    }

    foreach (token_get_all($contents) as $token) {
        if (is_array($token) && in_array($token[0], [T_FOR, T_FOREACH, T_WHILE, T_DO], true)) {
            $failures[] = "Application CLI source {$relativePath} must remain one-shot without an in-process loop.";
            break;
        }
    }
}

if (is_dir($root . '/src/Observability')) {
    $failures[] = 'Terminal request-summary types must remain application-owned outside framework core.';
}

$unknownFailureBoundary = file_get_contents($root . '/src/Http/UnknownFailureBoundary.php');

if (is_string($unknownFailureBoundary)) {
    foreach (['logAndRespond', 'error_log(', 'phpthis.request.unhandled', 'Throwable'] as $forbiddenMarker) {
        if (str_contains($unknownFailureBoundary, $forbiddenMarker)) {
            $failures[] = "UnknownFailureBoundary must not retain terminal logging marker {$forbiddenMarker}.";
        }
    }
}

foreach (
    [
        'example/src/Observability/TerminalRequestCoordinator.php',
        'skeleton/src/Observability/TerminalRequestCoordinator.php',
    ] as $coordinatorPath
) {
    $coordinator = file_get_contents($root . '/' . $coordinatorPath);

    if (
        is_string($coordinator)
        && substr_count($coordinator, '$this->summarySink->emit($summary);') !== 1
    ) {
        $failures[] = "Terminal request coordinator must retain exactly one sink invocation: {$coordinatorPath}.";
    }
}

foreach (
    [
        'example/src/Observability/CorrelationId.php',
        'skeleton/src/Observability/CorrelationId.php',
    ] as $correlationIdPath
) {
    $correlationId = file_get_contents($root . '/' . $correlationIdPath);

    if (is_string($correlationId) && str_contains($correlationId, 'fromString')) {
        $failures[] = "Correlation IDs must remain generated-only: {$correlationIdPath}.";
    }
}

$observabilityPackageInventory = file_get_contents($root . '/tools/package-files.txt');

if (is_string($observabilityPackageInventory)) {
    foreach (
        [
            '/^\.ai\/observability\.md$/m',
            '/^example\//m',
            '/^skeleton\//m',
            '/^tests\/observability\.php$/m',
            '/^src\/Observability\//m',
        ] as $forbiddenPackagePattern
    ) {
        if (preg_match($forbiddenPackagePattern, $observabilityPackageInventory) === 1) {
            $failures[] = 'Application-owned observability artifacts must remain outside the framework package inventory.';
        }
    }
}

$listDocumentsHandlerPath = $root . '/example/src/Documents/ListDocuments/ListDocumentsHandler.php';
$listDocumentsHandler = file_get_contents($listDocumentsHandlerPath);

if (!is_string($listDocumentsHandler)) {
    $failures[] = 'Cannot read the direct raw-SQL document-list handler.';
} else {
    $finiteSqlCounts = [
        "<<<'SQL'" => 8,
        '$this->connection->selectAllRows(' => 8,
        'documents.category IN (:category_1)' => 2,
        'documents.category IN (:category_1, :category_2)' => 2,
        'documents.category IN (:category_1, :category_2, :category_3)' => 2,
        'ORDER BY documents.sort_rank ASC, documents.document_key COLLATE BINARY ASC' => 4,
        'ORDER BY documents.sort_rank DESC, documents.document_key COLLATE BINARY DESC' => 4,
        "'requested_account_id' =>" => 8,
        "'resolved_tenant_account_id' =>" => 8,
        "'principal_id' =>" => 8,
        "'membership_tenant_account_id' =>" => 8,
        ':cursor_is_absent = 1' => 8,
        "'cursor_is_absent' =>" => 8,
        "'cursor_primary_sort_rank' =>" => 8,
        "'cursor_tie_sort_rank' =>" => 8,
        "'cursor_document_key' =>" => 8,
        "'category_1' =>" => 6,
        "'category_2' =>" => 4,
        "'category_3' =>" => 2,
        "'fetch_limit' =>" => 8,
    ];

    foreach ($finiteSqlCounts as $marker => $expectedCount) {
        if (substr_count($listDocumentsHandler, $marker) !== $expectedCount) {
            $failures[] = sprintf(
                'Document-list raw-SQL marker %s must occur exactly %d times.',
                $marker,
                $expectedCount,
            );
        }
    }

    foreach (
        [
            'Repository',
            'QueryBuilder',
            'Paginator',
            'Hydrator',
            'bindValue',
            'bindParam',
            'buildPlaceholders',
            'sprintf(',
            'implode(',
        ] as $forbiddenDataHelper
    ) {
        if (str_contains($listDocumentsHandler, $forbiddenDataHelper)) {
            $failures[] = "Document-list SQL must remain direct and helper-free: {$forbiddenDataHelper}.";
        }
    }
}

$packageInventory = file_get_contents($root . '/tools/package-files.txt');

if (is_string($packageInventory) && preg_match('/^example\//m', $packageInventory) === 1) {
    $failures[] = 'The application-owned example must remain excluded from the framework release inventory.';
}

$composerPath = $root . '/composer.json';
$composerContents = file_get_contents($composerPath);

if (!is_string($composerContents)) {
    $failures[] = 'Cannot read composer.json.';
} else {
    $composer = json_decode($composerContents, true);
    $scripts = is_array($composer) ? ($composer['scripts'] ?? null) : null;
    $check = is_array($scripts) ? ($scripts['check'] ?? null) : null;

    if (!is_array($scripts) || ($scripts['test:database-drivers'] ?? null) !== 'php tools/test-database-drivers.php') {
        $failures[] = 'composer.json must define the canonical database-driver certification script.';
    }

    if (!is_array($check) || !in_array('@test:database-drivers', $check, true)) {
        $failures[] = 'composer check must include database-driver certification.';
    }
}

$ciPath = $root . '/.github/workflows/ci.yml';
$ciContents = file_get_contents($ciPath);

if (!is_string($ciContents)) {
    $failures[] = 'Cannot read .github/workflows/ci.yml.';
} elseif (
    !str_contains($ciContents, 'PHPTHIS_DATABASE_TEST_DRIVERS: sqlite,mysql,pgsql')
    || !str_contains($ciContents, 'image: mysql:8.4')
    || !str_contains($ciContents, 'image: postgres:17')
    || !str_contains($ciContents, 'run: composer test:database-drivers')
    || !str_contains($ciContents, "PHPTHIS_MYSQL_DSN: 'mysql:")
    || !str_contains($ciContents, "PHPTHIS_PGSQL_DSN: 'pgsql:")
) {
    $failures[] = 'CI must preserve SQLite, MySQL, and PostgreSQL PDO transport certification.';
}

$consumerContractPath = $root . '/docs/consumer-contract.md';

if (is_file($consumerContractPath)) {
    $consumerContract = file_get_contents($consumerContractPath);

    if (!is_string($consumerContract)) {
        $failures[] = 'Cannot read docs/consumer-contract.md.';
    } else {
        if (preg_match('/^Contract version: 5$/m', $consumerContract) !== 1) {
            $failures[] = 'docs/consumer-contract.md must declare contract version 5.';
        }

        if (!str_contains($consumerContract, '## AI authoring and human accountability')) {
            $failures[] = 'docs/consumer-contract.md must define the AI authoring and human accountability contract.';
        }

        if (!str_contains($consumerContract, 'docs/knowledge-map.md')) {
            $failures[] = 'docs/consumer-contract.md must route framework questions through docs/knowledge-map.md.';
        }

        if (!str_contains($consumerContract, '`PHT006`')) {
            $failures[] = 'docs/consumer-contract.md must preserve finite SQL enforcement through PHT006.';
        }
    }
}

$securityGuidePath = $root . '/docs/security.md';

if (is_file($securityGuidePath)) {
    $securityGuide = file_get_contents($securityGuidePath);

    if (!is_string($securityGuide)) {
        $failures[] = 'Cannot read docs/security.md.';
    } elseif (
        !str_contains($securityGuide, 'Separate SQL data from SQL structure.')
        || !str_contains($securityGuide, '## Database authority')
        || !str_contains($securityGuide, '## Proof limits')
    ) {
        $failures[] = 'docs/security.md must preserve SQL separation, database authority, and proof limits.';
    }
}

$applicationDataTemplatePath = $root . '/templates/application/.ai/data.md';
$applicationTestingTemplatePath = $root . '/templates/application/.ai/testing.md';

if (is_file($applicationDataTemplatePath) && is_file($applicationTestingTemplatePath)) {
    $applicationDataTemplate = file_get_contents($applicationDataTemplatePath);
    $applicationTestingTemplate = file_get_contents($applicationTestingTemplatePath);

    if (!is_string($applicationDataTemplate) || !is_string($applicationTestingTemplate)) {
        $failures[] = 'Cannot read the application SQL-safety context templates.';
    } elseif (
        !str_contains($applicationDataTemplate, '## SQL structure and bounded-input policy')
        || !str_contains($applicationDataTemplate, '## Runtime and migration authority')
        || !str_contains($applicationTestingTemplate, 'before the query budget or trace changes')
    ) {
        $failures[] = 'Application context templates must preserve SQL structure, authority, and adversarial evidence.';
    }
}

$crudGuidePath = $root . '/docs/crud.md';

if (is_file($crudGuidePath)) {
    $crudGuide = file_get_contents($crudGuidePath);

    if (!is_string($crudGuide)) {
        $failures[] = 'Cannot read docs/crud.md.';
    } elseif (!str_contains(
        $crudGuide,
        'The CRUD reference profile is optional application structure. The PHPThis consumer contract and Strict Profile remain mandatory.',
    )) {
        $failures[] = 'docs/crud.md must preserve the optional CRUD-profile and mandatory consumer-contract boundary.';
    }
}

foreach (['templates/application/.ai/README.md', 'skeleton/.ai/README.md'] as $applicationContextIndex) {
    $applicationContextIndexContents = file_get_contents($root . '/' . $applicationContextIndex);

    if (!is_string($applicationContextIndexContents)) {
        $failures[] = "Cannot read {$applicationContextIndex}.";
    } elseif (!str_contains($applicationContextIndexContents, 'vendor/phpthis/framework/docs/crud.md')) {
        $failures[] = "{$applicationContextIndex} must route CRUD work through the installed framework guide.";
    }
}

$visionPath = $root . '/VISION.md';

if (is_file($visionPath)) {
    $vision = file_get_contents($visionPath);

    if (!is_string($vision)) {
        $failures[] = 'Cannot read VISION.md.';
    } elseif (!str_contains($vision, 'AI-first authoring with human accountability')) {
        $failures[] = 'VISION.md must preserve AI-first authoring with human accountability as the north star.';
    }
}

$strictProfilePath = $root . '/docs/strict-profile.md';

if (is_file($strictProfilePath)) {
    $strictProfile = file_get_contents($strictProfilePath);

    if (!is_string($strictProfile)) {
        $failures[] = 'Cannot read docs/strict-profile.md.';
    } elseif (preg_match('/^Profile version: 2$/m', $strictProfile) !== 1) {
        $failures[] = 'docs/strict-profile.md must declare profile version 2.';
    }
}

$applicationAgentInstructionsPath = $root . '/templates/application/AGENTS.md';

if (is_file($applicationAgentInstructionsPath)) {
    $applicationAgentInstructions = file_get_contents($applicationAgentInstructionsPath);

    if (!is_string($applicationAgentInstructions)) {
        $failures[] = 'Cannot read templates/application/AGENTS.md.';
    } else {
        if (!str_contains(
            $applicationAgentInstructions,
            'vendor/phpthis/framework/docs/consumer-contract.md',
        )) {
            $failures[] = 'Application AGENTS.md must point to the installed PHPThis consumer contract.';
        }

        if (!str_contains(
            $applicationAgentInstructions,
            'vendor/phpthis/framework/docs/knowledge-map.md',
        )) {
            $failures[] = 'Application AGENTS.md must point to the installed PHPThis knowledge map.';
        }

        if (!str_contains($applicationAgentInstructions, 'primary code author and knowledge interface')) {
            $failures[] = 'Application AGENTS.md must define the AI authoring role.';
        }

        if (!str_contains($applicationAgentInstructions, 'explicit approval from an accountable human')) {
            $failures[] = 'Application AGENTS.md must preserve human acceptance of consequential decisions.';
        }
    }
}

$skeletonAgentInstructionsPath = $root . '/skeleton/AGENTS.md';

if (is_file($skeletonAgentInstructionsPath)) {
    $skeletonAgentInstructions = file_get_contents($skeletonAgentInstructionsPath);

    if (!is_string($skeletonAgentInstructions)) {
        $failures[] = 'Cannot read skeleton/AGENTS.md.';
    } elseif (
        !str_contains($skeletonAgentInstructions, 'vendor/phpthis/framework/docs/knowledge-map.md')
        || !str_contains($skeletonAgentInstructions, 'primary code author and knowledge interface')
        || !str_contains($skeletonAgentInstructions, 'explicit approval from an accountable human')
    ) {
        $failures[] = 'Skeleton AGENTS.md must preserve the installed knowledge route, AI authoring role, and human decision boundary.';
    }
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
);

foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile()) {
        continue;
    }

    $path = $file->getPathname();
    $relativePath = substr($path, strlen($root) + 1);

    if (str_starts_with($relativePath, 'vendor/') || str_starts_with($relativePath, 'tmp/')) {
        continue;
    }

    $normalizedBasename = strtolower($file->getBasename());

    if (
        $relativePath !== 'phpstan.neon'
        && (
            preg_match('/\Aphpstan[a-z0-9._-]*\.neon(?:\.dist)?\z/', $normalizedBasename) === 1
            || preg_match('/\Aphpstan[a-z0-9._-]*baseline[a-z0-9._-]*\.php\z/', $normalizedBasename) === 1
        )
    ) {
        $failures[] = "PHT004 alternate PHPStan configuration is forbidden: {$relativePath}.";
    }

    if ($file->getExtension() === 'php' || $relativePath === 'bin/phpthis') {
        $phpFiles[$relativePath] = $path;
    }

    if ($file->getExtension() === 'md') {
        $markdownFiles[$relativePath] = $path;
    }
}

foreach ($phpFiles as $relativePath => $path) {
    $contents = file_get_contents($path);

    if (!is_string($contents)) {
        $failures[] = "Cannot read {$relativePath}.";
        continue;
    }

    $strictTypesPattern = $relativePath === 'bin/phpthis'
        ? '/^#!\/usr\/bin\/env php\R<\\?php\\s+declare\\(strict_types=1\\);/'
        : '/^<\\?php\\s+declare\\(strict_types=1\\);/';

    if (preg_match($strictTypesPattern, $contents) !== 1) {
        $failures[] = "{$relativePath} must declare strict types immediately after <?php.";
    }

    if (preg_match('/\\beval\\s*\\(/', $contents) === 1) {
        $failures[] = "{$relativePath} uses eval.";
    }

    if (preg_match('/\\$\\$[A-Za-z_{]/', $contents) === 1) {
        $failures[] = "{$relativePath} uses a variable variable.";
    }

    foreach (SyntaxProfile::failures($contents, $relativePath) as $profileFailure) {
        $failures[] = $profileFailure;
    }

    if ($relativePath === 'src/Routing/Router.php') {
        foreach (routingLookupFailures($contents, $relativePath) as $routingFailure) {
            $failures[] = $routingFailure;
        }
    }

    $tokens = token_get_all($contents);
    $functionImportPending = false;
    $insideFunctionImport = false;

    foreach ($tokens as $index => $token) {
        $tokenId = is_array($token) ? $token[0] : null;
        $tokenText = is_array($token) ? $token[1] : $token;
        $isSignificant = !is_array($token)
            || !in_array($tokenId, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);

        if ($functionImportPending && $isSignificant) {
            $insideFunctionImport = $tokenId === T_FUNCTION;
            $functionImportPending = false;
        }

        if ($tokenId === T_USE) {
            $functionImportPending = true;
        } elseif ($insideFunctionImport && $tokenText === ';') {
            $insideFunctionImport = false;
        }

        if ($tokenId === T_VARIABLE) {
            $isCanonicalSessionState = $relativePath === 'src/Session/SessionLifecycle.php'
                && $tokenText === '$_SESSION';
            $isFrontControllerInput = in_array(
                $relativePath,
                ['example/public/index.php', 'skeleton/public/index.php'],
                true,
            ) && $tokenText !== '$_SESSION';

            if (
                !$isCanonicalSessionState
                && !$isFrontControllerInput
                && in_array(
                    $tokenText,
                    ['$_SERVER', '$_GET', '$_POST', '$_COOKIE', '$_FILES', '$_SESSION', '$_ENV', '$_REQUEST'],
                    true,
                )
            ) {
                $boundary = $tokenText === '$_SESSION'
                    ? 'the canonical session boundary'
                    : 'the front controller';
                $failures[] = sprintf(
                    '%s:%d reads a PHP superglobal outside %s.',
                    $relativePath,
                    $token[2],
                    $boundary,
                );
            }
        }

        $nativeSessionFunction = strtolower(ltrim($tokenText, '\\'));

        if (
            $relativePath !== 'src/Session/SessionLifecycle.php'
            && in_array($tokenId, [T_STRING, T_NAME_FULLY_QUALIFIED], true)
            && in_array($nativeSessionFunction, $nativeSessionFunctions, true)
        ) {
            $nextSignificantToken = null;

            for ($next = $index + 1, $count = count($tokens); $next < $count; $next++) {
                $candidate = $tokens[$next];

                if (is_array($candidate) && in_array($candidate[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                $nextSignificantToken = $candidate;
                break;
            }

            $previousSignificantToken = null;

            for ($previous = $index - 1; $previous >= 0; $previous--) {
                $candidate = $tokens[$previous];

                if (is_array($candidate) && in_array($candidate[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                $previousSignificantToken = $candidate;
                break;
            }

            $previousTokenId = is_array($previousSignificantToken) ? $previousSignificantToken[0] : null;

            if (
                ($nextSignificantToken === '(' || $insideFunctionImport)
                && !in_array(
                    $previousTokenId,
                    [T_FUNCTION, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON],
                    true,
                )
            ) {
                $failures[] = sprintf(
                    '%s:%d %s native session function %s outside the canonical session boundary.',
                    $relativePath,
                    $token[2],
                    $insideFunctionImport ? 'imports' : 'calls',
                    $nativeSessionFunction,
                );
            }
        }

        if (
            !in_array($relativePath, ['tools/guardrails.php', 'verification/ApplicationChecker.php'], true)
            && $tokenId === T_CONSTANT_ENCAPSED_STRING
            && strlen($tokenText) >= 2
        ) {
            $literalFunction = strtolower(ltrim(stripcslashes(substr($tokenText, 1, -1)), '\\'));

            if (in_array($literalFunction, $nativeSessionFunctions, true)) {
                $failures[] = sprintf(
                    '%s:%d references native session function %s indirectly outside the canonical session boundary.',
                    $relativePath,
                    $token[2],
                    $literalFunction,
                );
            }
        }

        if (
            in_array($tokenId, [T_COMMENT, T_DOC_COMMENT], true)
            && preg_match('/@phpstan-ignore[A-Za-z0-9_-]*/i', $tokenText) === 1
        ) {
            $failures[] = sprintf(
                'PHT004 %s:%d PHPStan comment suppressions are forbidden.',
                $relativePath,
                $token[2],
            );
        }

    }
}

if (count($markdownFiles) <= count($phpFiles)) {
    $failures[] = sprintf(
        'Markdown files (%d) must outnumber PHP files (%d).',
        count($markdownFiles),
        count($phpFiles),
    );
}

$coreLines = 0;

foreach ($phpFiles as $relativePath => $path) {
    if (!str_starts_with($relativePath, 'src/')) {
        continue;
    }

    $lines = file($path);
    $coreLines += is_array($lines) ? count($lines) : 0;
}

if ($coreLines > 2_300) {
    $failures[] = "Core source has {$coreLines} physical lines; the Alpha 2 limit is 2300.";
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL {$failure}\n");
    }

    exit(1);
}

fwrite(
    STDOUT,
    sprintf(
        "PASS guardrails: %d Markdown files, %d PHP files, %d core lines\n",
        count($markdownFiles),
        count($phpFiles),
        $coreLines,
    ),
);
