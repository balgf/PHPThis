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
    '.ai/crud.md',
    '.ai/database.md',
    '.ai/http.md',
    '.ai/routing.md',
    '.ai/session.md',
    'docs/consumer-contract.md',
    'docs/caching.md',
    'docs/crud.md',
    'docs/getting-started.md',
    'docs/knowledge-map.md',
    'docs/releases/0.1.0-alpha.1.md',
    'docs/security.md',
    'docs/sessions.md',
    'docs/decisions/011-ai-first-authoring.md',
    'docs/decisions/012-pdo-transport-application-owned-dialects.md',
    'docs/decisions/013-optional-crud-reference-profile.md',
    'docs/decisions/014-sql-data-and-finite-structure.md',
    'docs/decisions/015-explicit-native-session-lifecycle.md',
    'docs/decisions/016-cache-policy-before-cache-mechanism.md',
    'docs/decisions/017-bounded-trailing-positive-integer-routes.md',
    'docs/decisions/018-bounded-alpha-1-release-scope.md',
    'docs/decisions/019-bounded-multiple-typed-routes.md',
    'example/src/Documents/DocumentRoutes.php',
    'example/src/Documents/GetDocument/AccountId.php',
    'example/src/Documents/GetDocument/DocumentKey.php',
    'example/src/Documents/GetDocument/GetDocumentHandler.php',
    'example/src/Users/GetUser/GetUserHandler.php',
    'example/src/Users/GetUser/UserDetails.php',
    'example/src/Users/GetUser/UserId.php',
    'example/src/Users/UserRoutes.php',
    'templates/application/AGENTS.md',
    'templates/application/.ai/README.md',
    'templates/application/.ai/architecture.md',
    'templates/application/.ai/change-workflow.md',
    'templates/application/.ai/data.md',
    'templates/application/.ai/integrations.md',
    'templates/application/.ai/operations.md',
    'templates/application/.ai/project.md',
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
    'skeleton/.ai/data.md',
    'skeleton/.ai/integrations.md',
    'skeleton/.ai/operations.md',
    'skeleton/.ai/project.md',
    'skeleton/.ai/rules.md',
    'skeleton/.ai/testing.md',
    'skeleton/bootstrap.php',
    'skeleton/composer.json',
    'skeleton/docs/decisions/README.md',
    'skeleton/public/index.php',
    'skeleton/src/HealthHandler.php',
    'skeleton/src/HealthRoutes.php',
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
    'tests/fixtures/routing-construction-traversal.php.fixture',
    'tests/fixtures/routing-lookup-index-loop.php.fixture',
    'tests/fixtures/routing-lookup-helper-loop.php.fixture',
    'tests/fixtures/routing-path-segment-traversal.php.fixture',
    'tests/fixtures/routing-lookup-traversal.php.fixture',
    'tools/package-files.txt',
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
        "'Cache-Control' => 'no-store'",
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
        if (preg_match('/^Contract version: 4$/m', $consumerContract) !== 1) {
            $failures[] = 'docs/consumer-contract.md must declare contract version 4.';
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
