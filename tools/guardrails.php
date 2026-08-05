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

function frameworkMechanismPathIsForbidden(string $relativePath): bool
{
    if (!str_starts_with($relativePath, 'src/')) {
        return false;
    }

    foreach (explode('/', substr($relativePath, 4)) as $segment) {
        $name = str_ends_with(strtolower($segment), '.php')
            ? substr($segment, 0, -4)
            : $segment;

        $exactMechanismSegment = preg_match(
            '/\A(?:orm|models?|repositor(?:y|ies)|facades?|discovery|observers?|scopes?|containers?|middlewares?|pipelines?|decorators?|query[-_]?builders?|binding[-_]?helpers?|placeholder[-_]?helpers?|auto[-_]?wir(?:e|ing)|configs?|configurations?|(?:application|deployment|runtime)[-_]?config(?:uration)?s?|config(?:uration)?[-_]?(?:bags?|repositories|services|helpers|facades|providers|readers)|secret[-_]?managers?|dotenv[-_]?(?:loaders?)?)\z/i',
            $name,
        ) === 1;
        $camelCaseMechanismSuffix = preg_match(
            '/(?:\A|(?<=[A-Za-z0-9]))(?:ORM|Orm|Models?|Repositories|Repository|Facades?|Discovery|Observers?|Scopes?|Containers?|Middleware|Pipeline|Decorator)(?:Interface|Provider|Factory)?\z/',
            $name,
        ) === 1;
        $explicitHiddenMechanismSuffix = preg_match(
            '/(?:\A|(?<=[A-Za-z0-9]))(?:QueryBuilder|BindingHelper|PlaceholderHelper|AutoWire|Autowire|ConfigurationBag|ConfigBag|ConfigurationRepository|ConfigRepository|ConfigurationService|ConfigService|ConfigurationHelper|ConfigHelper|ConfigurationFacade|ConfigFacade|ConfigurationReader|ConfigReader|ApplicationEnvironment|EnvironmentReader|EnvironmentConfiguration|ApplicationConfig|ApplicationConfiguration|DeploymentConfig|DeploymentConfiguration|RuntimeConfig|RuntimeConfiguration|SecretManager|DotenvLoader)(?:Interface|Provider|Factory)?\z/',
            $name,
        ) === 1;

        if ($exactMechanismSegment || $camelCaseMechanismSuffix || $explicitHiddenMechanismSuffix) {
            return true;
        }
    }

    return false;
}

function workbenchRuntimePathIsForbidden(string $relativePath): bool
{
    if (!str_starts_with($relativePath, 'src/')) {
        return false;
    }

    foreach (explode('/', substr($relativePath, 4)) as $segment) {
        $name = preg_replace('/\.php\z/i', '', $segment);

        if (!is_string($name)) {
            continue;
        }

        $compactName = preg_replace('/[^A-Za-z0-9]+/', '', strtolower($name));

        if (
            is_string($compactName)
            && in_array(
                $compactName,
                ['workbench', 'workbenches', 'repl', 'repls', 'interactiveshell', 'interactiveshells'],
                true,
            )
        ) {
            return true;
        }

        $tokenizableName = str_replace('REPLs', 'Repls', $name);
        $wordSeparatedName = preg_replace(
            [
                '/(?<=[a-z0-9])(?=[A-Z])/',
                '/(?<=[A-Z])(?=[A-Z][a-z])/',
                '/(?<=[A-Za-z])(?=[0-9])/',
                '/(?<=[0-9])(?=[A-Za-z])/',
            ],
            '-',
            $tokenizableName,
        );

        if (!is_string($wordSeparatedName)) {
            continue;
        }

        $words = preg_split('/[^A-Za-z0-9]+/', strtolower($wordSeparatedName), -1, PREG_SPLIT_NO_EMPTY);

        if (!is_array($words)) {
            continue;
        }

        foreach ($words as $index => $word) {
            if (in_array($word, ['workbench', 'workbenches', 'repl', 'repls'], true)) {
                return true;
            }

            if (
                $word === 'interactive'
                && in_array($words[$index + 1] ?? null, ['shell', 'shells'], true)
            ) {
                return true;
            }
        }
    }

    return false;
}

/** @param list<string> $markers */
function mutableReleaseStateClaim(string $contents, array $markers): ?string
{
    $plainContents = preg_replace('/\[([^\]\r\n]+)\]\([^\)\r\n]+\)/', '$1', strtolower($contents));

    if (!is_string($plainContents)) {
        return null;
    }

    $plainContents = str_replace(['*', '_', '`', '~'], '', $plainContents);
    $normalizedContents = preg_replace('/\s+/', ' ', $plainContents);

    if (!is_string($normalizedContents)) {
        return null;
    }

    $alpha5Subject = '(?:the\s+)?(?:public\s+)?(?:alpha[ -]?5|v?0\.1\.0-alpha\.5)(?:\s+packages?|\s+installation\s+path)?';
    $publicationPredicate = '(?:(?:is|are)\s+(?:now\s+)?(?:publicly\s+)?(?:available|published|released)|(?:has|have)\s+(?:now\s+)?been\s+(?:publicly\s+)?(?:published|released))';

    if (preg_match('/\b' . $alpha5Subject . '\s+' . $publicationPredicate . '\b/', $normalizedContents) === 1) {
        return 'normalized Alpha 5 publication claim';
    }

    foreach ($markers as $marker) {
        $normalizedMarker = preg_replace('/\s+/', ' ', strtolower($marker));

        if (is_string($normalizedMarker) && str_contains($normalizedContents, $normalizedMarker)) {
            return $marker;
        }
    }

    return null;
}

$root = dirname(__DIR__);
$phpFiles = [];
$markdownFiles = [];
$failures = [];
$environmentIgnoreArtifacts = [
    '.gitignore',
    'skeleton/.gitignore',
];

foreach ($environmentIgnoreArtifacts as $relativePath) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        $failures[] = "Cannot read environment-ignore artifact {$relativePath}.";
        continue;
    }

    $lines = preg_split('/\R/', $contents);

    if (!is_array($lines)) {
        $failures[] = "Cannot parse environment-ignore artifact {$relativePath}.";
        continue;
    }

    foreach (['.env', '.env.*', '!.env.example'] as $requiredLine) {
        if (!in_array($requiredLine, $lines, true)) {
            $failures[] = "{$relativePath} must contain the exact environment-ignore rule {$requiredLine}.";
        }
    }
}

$forbiddenFrameworkMechanismFixtures = [
    'src/Orm/IdentityMap.php',
    'src/Domain/UserModel.php',
    'src/Domain/UserRepository.php',
    'src/Domain/UserRepositoryInterface.php',
    'src/repository/SqlAccess.php',
    'src/Domain/AccountFacade.php',
    'src/Domain/AccountFacadeProvider.php',
    'src/Discovery/AttributeScanner.php',
    'src/Domain/UserObserver.php',
    'src/Domain/GlobalScope.php',
    'src/Domain/ServiceContainer.php',
    'src/Http/Middleware.php',
    'src/Http/RequestMiddlewareInterface.php',
    'src/Http/RequestPipeline.php',
    'src/Http/HandlerDecorator.php',
    'src/Domain/UserQueryBuilder.php',
    'src/Domain/UserQueryBuilderInterface.php',
    'src/Database/QueryBuilders/SqlSelect.php',
    'src/Database/SqlBindingHelper.php',
    'src/Database/SqlBindingHelperFactory.php',
    'src/Composition/AutoWire.php',
    'src/Composition/AutoWireProvider.php',
    'src/Configuration/ApplicationEnvironment.php',
    'src/Support/ConfigurationBag.php',
    'src/Support/ConfigRepository.php',
    'src/Support/ConfigurationHelper.php',
    'src/Support/ApplicationEnvironment.php',
    'src/Support/SecretManager.php',
    'src/Support/DotenvLoader.php',
    'src/ApplicationConfiguration.php',
    'src/Support/DeploymentConfiguration.php',
    'src/Composition/RuntimeConfigurationFactory.php',
    'src/application-configuration/Values.php',
    'src/Support/deployment_config.php',
    'src/RuntimeConfigs/Value.php',
    'src/Http/HttpRuntimeConfiguration.php',
];
$allowedFrameworkMechanismFixtures = [
    'src/Application.php',
    'src/Database/Connection.php',
    'src/Http/Request.php',
    'src/Session/SessionConfiguration.php',
    'src/Support/Transform.php',
    'src/Observability/Telescope.php',
    'example/src/Domain/UserRepository.php',
    'docs/repository-boundary.md',
];

foreach ($forbiddenFrameworkMechanismFixtures as $fixture) {
    if (!frameworkMechanismPathIsForbidden($fixture)) {
        $failures[] = "Permanent framework-boundary fixture must fail: {$fixture}.";
    }
}

foreach ($allowedFrameworkMechanismFixtures as $fixture) {
    if (frameworkMechanismPathIsForbidden($fixture)) {
        $failures[] = "Permanent framework-boundary fixture must remain allowed: {$fixture}.";
    }
}
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
    '.gitattributes',
    '.github/assets/phpthis-readme-banner.png',
    '.github/workflows/ci.yml',
    'phpunit.xml.dist',
    'RELEASING.md',
    '.ai/cache.md',
    '.ai/cli.md',
    '.ai/consumer-profile.md',
    '.ai/crud.md',
    '.ai/database.md',
    '.ai/file-transfers.md',
    '.ai/http.md',
    '.ai/jobs.md',
    '.ai/migrations.md',
    '.ai/observability.md',
    '.ai/request-policy.md',
    '.ai/routing.md',
    '.ai/session.md',
    '.ai/websockets.md',
    '.ai/workbench.md',
    'docs/consumer-contract.md',
    'docs/consumer-profile.md',
    'docs/configuration.md',
    'docs/caching.md',
    'docs/cli.md',
    'docs/cli/README.md',
    'docs/cli/arguments-output.md',
    'docs/cli/composition.md',
    'docs/cli/scheduling-locking.md',
    'docs/cli/testing.md',
    'docs/crud.md',
    'docs/file-transfers/README.md',
    'docs/file-transfers/deployment.md',
    'docs/file-transfers/emission.md',
    'docs/file-transfers/exclusions.md',
    'docs/file-transfers/failures.md',
    'docs/file-transfers/local-file-response.md',
    'docs/file-transfers/metadata-trust.md',
    'docs/file-transfers/range-policy.md',
    'docs/file-transfers/request-ingestion.md',
    'docs/file-transfers/security.md',
    'docs/file-transfers/storage-ownership.md',
    'docs/file-transfers/testing.md',
    'docs/file-transfers/upload-errors.md',
    'docs/file-transfers/upload-value.md',
    'docs/getting-started.md',
    'docs/jobs.md',
    'docs/jobs/README.md',
    'docs/jobs/envelope.md',
    'docs/jobs/lifecycle.md',
    'docs/jobs/operations.md',
    'docs/jobs/schema.md',
    'docs/jobs/testing.md',
    'docs/knowledge-map.md',
    'docs/migrations.md',
    'docs/observability/README.md',
    'docs/observability/correlation-id.md',
    'docs/observability/database-evidence.md',
    'docs/observability/event-schema.md',
    'docs/observability/sink-failure.md',
    'docs/observability/testing.md',
    'docs/request-policy.md',
    'docs/redis-coordination.md',
    'docs/redis/README.md',
    'docs/redis/adoption.md',
    'docs/redis/cache-failures.md',
    'docs/redis/cache-key.md',
    'docs/redis/cache-value.md',
    'docs/redis/invalidation.md',
    'docs/redis/lease-failures.md',
    'docs/redis/lease-lifecycle.md',
    'docs/redis/observability.md',
    'docs/redis/stale-refill.md',
    'docs/redis/testing.md',
    'docs/redis/topology.md',
    'docs/releases/0.1.0-alpha.1.md',
    'docs/releases/0.1.0-alpha.2.md',
    'docs/releases/0.1.0-alpha.3.md',
    'docs/releases/0.1.0-alpha.4.md',
    'docs/releases/0.1.0-alpha.5.md',
    'docs/security.md',
    'docs/sessions.md',
    'docs/vocabulary.md',
    'docs/websockets.md',
    'docs/workbench.md',
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
    'docs/decisions/026-bounded-file-transfers.md',
    'docs/decisions/027-application-owned-explicit-sqlite-migrations.md',
    'docs/decisions/028-application-owned-redis-cache-and-schedule-lease.md',
    'docs/decisions/029-alpha-2-consumer-profile-rollup.md',
    'docs/decisions/030-report-only-consumer-duplication-advisory.md',
    'docs/decisions/031-bounded-alpha-3-release-scope.md',
    'docs/decisions/032-explicit-uuid-and-ulid-route-types.md',
    'docs/decisions/033-application-owned-request-handler-decorators.md',
    'docs/decisions/034-application-owned-websocket-integration.md',
    'docs/decisions/035-bounded-alpha-4-release-scope.md',
    'docs/decisions/036-one-typed-application-configuration-boundary.md',
    'docs/decisions/037-database-setup-scope-gate.md',
    'docs/decisions/038-application-owned-database-authority-lifecycle.md',
    'docs/decisions/039-recommended-database-migration-structure.md',
    'docs/decisions/040-bounded-alpha-5-release-scope.md',
    'docs/decisions/041-optional-development-workbench.md',
    'example/AGENTS.md',
    'example/.ai/README.md',
    'example/.ai/cache.md',
    'example/.ai/cli.md',
    'example/.ai/configuration.md',
    'example/.ai/data.md',
    'example/.ai/file-transfers.md',
    'example/.ai/jobs.md',
    'example/.ai/migrations.md',
    'example/.ai/observability.md',
    'example/bin/console.php',
    'example/src/ApplicationComposition.php',
    'example/src/ApplicationDatabasePath.php',
    'example/src/InvalidApplicationDatabasePath.php',
    'example/src/Database/Migrations/ApplicationMigrationFailed.php',
    'example/src/Database/Migrations/ApplicationMigrationFailureReason.php',
    'example/src/Database/Migrations/ApplicationMigrationOutcome.php',
    'example/src/Database/Migrations/LocalMigrationLock.php',
    'example/src/Database/Migrations/MigrationHistory.php',
    'example/src/Database/Migrations/SqliteApplicationMigrations.php',
    'example/src/Database/Migrations/SqliteMigrationLedger.php',
    'example/src/Cli/ApplicationCommandExecution.php',
    'example/src/Cli/ApplicationCommandLine.php',
    'example/src/Cli/ApplicationCommandName.php',
    'example/src/Cli/ApplicationCommandOutcome.php',
    'example/src/Cli/ApplicationCommands.php',
    'example/src/Cli/InvalidApplicationCommandArguments.php',
    'example/src/Cli/README.md',
    'example/src/Cli/UnknownApplicationCommand.php',
    'example/src/Coordination/RedisScheduleRunLease.php',
    'example/src/Coordination/RedisScheduleRunLeaseAcquireOutcome.php',
    'example/src/Coordination/RedisScheduleRunLeaseReleaseOutcome.php',
    'example/src/Coordination/RedisScheduleRunLeaseRenewOutcome.php',
    'example/src/Coordination/RedisScheduleRunLeaseTrace.php',
    'example/src/Coordination/RedisScheduleRunLeaseTraceOutcome.php',
    'example/src/Coordination/RedisScheduleRunLeaseUnavailable.php',
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
    'example/src/Accounts/AccountId.php',
    'example/src/Accounts/AuthenticateAccountRequest.php',
    'example/src/Accounts/AuthenticatedPrincipal.php',
    'example/src/Accounts/CrossTenant.php',
    'example/src/Accounts/DenyAllAccountAuthentication.php',
    'example/src/Accounts/DenyAllAccountAuthorization.php',
    'example/src/Accounts/DenyAllAccountTenantResolution.php',
    'example/src/Accounts/Forbidden.php',
    'example/src/Accounts/ResolveAccountTenant.php',
    'example/src/Accounts/ResolvedTenant.php',
    'example/src/Accounts/Unauthenticated.php',
    'example/src/Documents/DocumentRoutes.php',
    'example/src/Documents/DocumentKey.php',
    'example/src/Documents/GetDocument/AuthorizeGetDocument.php',
    'example/src/Documents/GetDocument/DocumentDetails.php',
    'example/src/Documents/GetDocument/DocumentDetailsCacheReadOutcome.php',
    'example/src/Documents/GetDocument/DocumentDetailsCacheTrace.php',
    'example/src/Documents/GetDocument/DocumentDetailsCacheWriteOutcome.php',
    'example/src/Documents/GetDocument/GetDocumentHandler.php',
    'example/src/Documents/GetDocument/RedisDocumentDetailsCache.php',
    'example/src/Documents/GetDocument/RedisDocumentDetailsInvalidationOutcome.php',
    'example/src/Documents/GetDocument/RetrieveAuthorizedDocument.php',
    'example/src/Documents/GetDocument/SelectAuthorizedDocument.php',
    'example/src/Documents/ListDocuments/AuthorizeListDocuments.php',
    'example/src/Documents/ListDocuments/ListDocumentsPageRequest.php',
    'example/src/Documents/ListDocuments/DocumentSummary.php',
    'example/src/Documents/ListDocuments/ListDocumentsHandler.php',
    'example/src/Documents/UpdateDocumentTitle/DocumentTitleUpdateOutcome.php',
    'example/src/Documents/UpdateDocumentTitle/RedisInvalidatingDocumentTitleUpdate.php',
    'example/src/Documents/UpdateDocumentTitle/RedisInvalidatingDocumentTitleUpdateResult.php',
    'example/src/Users/CreateUser/AuthorizeCreateUser.php',
    'example/src/DocumentFiles/DocumentFileId.php',
    'example/src/DocumentFiles/DocumentFileNotFound.php',
    'example/src/DocumentFiles/DocumentFileRoutes.php',
    'example/src/DocumentFiles/DocumentFileUnavailable.php',
    'example/src/DocumentFiles/DownloadDocumentFileHandler.php',
    'example/src/DocumentFiles/LocalDocumentFiles.php',
    'example/src/DocumentFiles/PendingDocumentUpload.php',
    'example/src/DocumentFiles/UploadDocumentFileHandler.php',
    'example/src/Users/GetUser/GetUserHandler.php',
    'example/src/Users/GetUser/UserDetails.php',
    'example/src/Users/GetUser/UserId.php',
    'example/src/Users/CreateUser/CreateUserOperation.php',
    'example/src/Users/CreateUser/TransactionalCreateUser.php',
    'example/src/Users/CreateUser/UnacceptableCreateUserValues.php',
    'example/src/Users/UserRoutes.php',
    'templates/application/AGENTS.md',
    'templates/application/.ai/README.md',
    'templates/application/.ai/architecture.md',
    'templates/application/.ai/change-workflow.md',
    'templates/application/.ai/cli.md',
    'templates/application/.ai/configuration.md',
    'templates/application/.ai/data.md',
    'templates/application/.ai/file-transfers.md',
    'templates/application/.ai/integrations.md',
    'templates/application/.ai/jobs.md',
    'templates/application/.ai/migrations.md',
    'templates/application/.ai/observability.md',
    'templates/application/.ai/operations.md',
    'templates/application/.ai/project.md',
    'templates/application/.ai/request-policy.md',
    'templates/application/.ai/rules.md',
    'templates/application/.ai/testing.md',
    'templates/application/.ai/websockets.md',
    'templates/application/.ai/workbench.md',
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
    'skeleton/.ai/configuration.md',
    'skeleton/.ai/data.md',
    'skeleton/.ai/file-transfers.md',
    'skeleton/.ai/integrations.md',
    'skeleton/.ai/jobs.md',
    'skeleton/.ai/migrations.md',
    'skeleton/.ai/observability.md',
    'skeleton/.ai/operations.md',
    'skeleton/.ai/project.md',
    'skeleton/.ai/request-policy.md',
    'skeleton/.ai/rules.md',
    'skeleton/.ai/testing.md',
    'skeleton/.ai/websockets.md',
    'skeleton/.ai/workbench.md',
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
    'verification/ApplicationDuplicationScanner.php',
    'verification/EnvironmentAccessProfile.php',
    'verification/SyntaxProfile.php',
    'verification/phpstan/ConnectionCallableArrayRule.php',
    'verification/phpstan/ConnectionMethodCallableRule.php',
    'verification/phpstan/ConnectionSqlRuleSupport.php',
    'verification/phpstan/ConstantSqlStringRule.php',
    'verification/phpstan/DirectPdoConstructionRule.php',
    'verification/phpstan/MixedScalarCoercionRule.php',
    'verification/phpstan/extension.php',
    'src/Http/CookieSameSite.php',
    'src/Http/LocalFileBody.php',
    'src/Http/RequestUpload.php',
    'src/Http/RequestUploadError.php',
    'src/Http/ResponseCookie.php',
    'src/Http/ResponseEmissionFailed.php',
    'src/Http/ResponseEmitter.php',
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
    'tests/FrameworkBehaviorTest.php',
    'tests/behavior-names.txt',
    'tests/document-files.php',
    'tests/large-file-emitter.php',
    'tests/observability.php',
    'tests/response-emitter.php',
    'tests/upload-request-boundary.php',
    'tests/jobs.php',
    'tests/migrations.php',
    'tests/cli.php',
    'tests/cli-migration-lock-holder.php',
    'tests/cache.php',
    'tests/consumer-profile.php',
    'tests/redis-coordination.php',
    'tests/redis-schedule-lease-holder.php',
    'tests/job-worker-crash.php',
    'tests/handler-decorator.php',
    'tests/request-policy.php',
    'tests/run.php',
    'tests/fixtures/routing-construction-traversal.php.fixture',
    'tests/fixtures/routing-lookup-index-loop.php.fixture',
    'tests/fixtures/routing-lookup-helper-loop.php.fixture',
    'tests/fixtures/routing-path-segment-traversal.php.fixture',
    'tests/fixtures/routing-lookup-traversal.php.fixture',
    'tools/package-files.txt',
    'tools/test-application-duplication.php',
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

$duplicationAdvisoryArtifactMarkers = [
    '.ai/application-context.md' => 'report-only review signal over the same application manifest',
    '.ai/static-analysis.md' => '48-token minimum',
    '.ai/testing.md' => 'The duplication advisory requires a fast direct scanner suite',
    'bin/phpthis' => "verification/ApplicationDuplicationScanner.php",
    'composer.json' => '"test:duplication": "php tools/test-application-duplication.php"',
    'docs/consumer-contract.md' => 'The duplication scan is an advisory review signal, not program validity.',
    'docs/decisions/030-report-only-consumer-duplication-advisory.md' => 'Status: accepted',
    'docs/decisions/README.md' => '030-report-only-consumer-duplication-advisory.md',
    'docs/guardrails.md' => '`php tools/test-application-duplication.php`',
    'docs/knowledge-map.md' => 'Review a possible duplication advisory',
    'docs/static-analysis.md' => '## Report-only duplication review',
    'docs/strict-profile.md' => 'possible-duplication output is deliberately absent from this catalogue',
    'tools/package-files.txt' => 'verification/ApplicationDuplicationScanner.php',
    'tools/test-application-duplication.php' => 'comments and whitespace do not hide an exact-threshold clone',
    'tools/test-consumer-project.php' => 'proveDuplicationAdvisoryIsReportOnly(',
    'verification/ApplicationChecker.php' => '$duplicationScanner->write($debug);',
    'verification/ApplicationDuplicationScanner.php' => 'private const MINIMUM_TOKENS = 48;',
];

foreach ($duplicationAdvisoryArtifactMarkers as $relativePath => $marker) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents) || !str_contains($contents, $marker)) {
        $failures[] = "The report-only consumer-duplication advisory is incomplete in {$relativePath}.";
    }
}

$duplicationScanner = file_get_contents($root . '/verification/ApplicationDuplicationScanner.php');

if (is_string($duplicationScanner) && str_contains($duplicationScanner, 'PHT')) {
    $failures[] = 'The report-only duplication scanner must not create a PHT diagnostic.';
}

$duplicationComposerContents = file_get_contents($root . '/composer.json');
$duplicationComposer = is_string($duplicationComposerContents)
    ? json_decode($duplicationComposerContents, true)
    : null;
$duplicationScripts = is_array($duplicationComposer) ? ($duplicationComposer['scripts'] ?? null) : null;
$duplicationCheck = is_array($duplicationScripts) ? ($duplicationScripts['check'] ?? null) : null;
$duplicationStage = is_array($duplicationCheck)
    ? array_search('@test:duplication', $duplicationCheck, true)
    : false;

if ($duplicationStage === false) {
    $failures[] = 'The canonical framework check must execute the direct duplication-advisory suite.';
}

$alpha5ReleaseContractMarkers = [
    '.ai/README.md' => 'Prepare, assess, or publish a release',
    '.ai/application-context.md' => 'Keep Alpha scope approval separate from authorization to create tags',
    '.ai/testing.md' => 'The Git export comparison requires a clean worktree',
    'README.md' => 'The bounded [Alpha 5 scope]',
    'RELEASING.md' => '## Alpha 5 release gate',
    'ROADMAP.md' => 'Alpha 5 publication state is external',
    'SECURITY.md' => 'An Alpha 5 release may be announced only after the public-artifact gate',
    'docs/getting-started.md' => 'The bounded Alpha 5 scope is accepted',
    'docs/knowledge-map.md' => '`docs/releases/0.1.0-alpha.5.md`, ADR 040, ADR 036 through ADR 039',
    'docs/decisions/029-alpha-2-consumer-profile-rollup.md' => 'No capability has an overall `defer` exit.',
    'docs/decisions/030-report-only-consumer-duplication-advisory.md' => 'This advisory has no `PHT` identifier',
    'docs/decisions/031-bounded-alpha-3-release-scope.md' => 'Alpha 3 is accepted as a tooling-only release',
    'docs/decisions/032-explicit-uuid-and-ulid-route-types.md' => 'Consumer Contract version 8',
    'docs/decisions/033-application-owned-request-handler-decorators.md' => 'Consumer Contract version 9',
    'docs/decisions/034-application-owned-websocket-integration.md' => 'Status: accepted',
    'docs/decisions/035-bounded-alpha-4-release-scope.md' => 'Alpha 4 is accepted as the bounded rollup of the changes after Alpha 3',
    'docs/decisions/036-one-typed-application-configuration-boundary.md' => 'Consumer Contract version 10 and Strict Profile version 3 add permanent structural rule `PHT007`.',
    'docs/decisions/037-database-setup-scope-gate.md' => 'Status: accepted',
    'docs/decisions/038-application-owned-database-authority-lifecycle.md' => 'Status: accepted',
    'docs/decisions/039-recommended-database-migration-structure.md' => 'Status: accepted',
    'docs/decisions/040-bounded-alpha-5-release-scope.md' => 'Alpha 5 is accepted as the bounded rollup of exactly these changes after Alpha 4',
    'docs/releases/0.1.0-alpha.5.md' => 'Release identity: `0.1.0-alpha.5`. Publication state is external',
    'tools/package-files.txt' => 'docs/decisions/040-bounded-alpha-5-release-scope.md',
];

foreach ($alpha5ReleaseContractMarkers as $relativePath => $marker) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents) || !str_contains($contents, $marker)) {
        $failures[] = "The accepted bounded Alpha 5 release contract is missing from {$relativePath}.";
    }
}

$historicalAlpha1IdentityArtifactMarkers = [
    'RELEASING.md' => [
        '## Approved Alpha 1 identity',
        'Composer version: `0.1.0-alpha.1`',
        'Framework tag: `v0.1.0-alpha.1`',
        'Skeleton tag: `v0.1.0-alpha.1`',
        'The exact candidate commit, release date, and accountable-human publication authorization belong in the external release evidence',
        'That approval did not itself authorize creation of either tag, either package-host entry, either GitHub release, or the announcement.',
        'Alpha 1 remained subject to the complete gate recorded by its tagged source',
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

foreach ($historicalAlpha1IdentityArtifactMarkers as $relativePath => $markers) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        $failures[] = "Cannot read historical Alpha 1 identity artifact {$relativePath}.";
        continue;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $failures[] = "The historical Alpha 1 identity marker is missing from {$relativePath}.";
        }
    }
}

$alpha2ReleaseIdentityArtifactMarkers = [
    'RELEASING.md' => [
        '## Approved Alpha 2 identity',
        'Composer version: `0.1.0-alpha.2`',
        'Framework tag: `v0.1.0-alpha.2`',
        'Skeleton tag: `v0.1.0-alpha.2`',
        'The accountable human approved the following release identity and gated publication sequence on 2026-07-21',
        'This approves the exact version and tag names and authorizes the following operations only after their preceding gates pass',
        'If any mandatory check fails, the next external operation remains unauthorized until a new candidate passes.',
    ],
    'docs/releases/0.1.0-alpha.2.md' => [
        'Release identity: `0.1.0-alpha.2`. Publication state is external',
        'Identity and gated publication authorization do not announce either tag, either package, or the public installation path.',
        'It is not production-ready and makes no backward-compatibility promise across prereleases.',
        'external release evidence recorded through `RELEASING.md`',
        'composer create-project --stability=alpha phpthis/skeleton',
    ],
    'docs/decisions/029-alpha-2-consumer-profile-rollup.md' => [
        'Status: accepted',
        'Framework core and release inventory must continue rejecting runtime namespaces or files that present an ORM',
    ],
    'tools/package-files.txt' => [
        'docs/releases/0.1.0-alpha.2.md',
    ],
];

foreach ($alpha2ReleaseIdentityArtifactMarkers as $relativePath => $markers) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        $failures[] = "Cannot read approved Alpha 2 identity artifact {$relativePath}.";
        continue;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $failures[] = "The approved Alpha 2 identity marker is missing from {$relativePath}.";
        }
    }
}

$alpha3ReleaseIdentityArtifactMarkers = [
    '.gitattributes' => [
        '/.DS_Store export-ignore',
    ],
    'README.md' => [
        'https://raw.githubusercontent.com/balgf/PHPThis/main/.github/assets/phpthis-readme-banner.png',
    ],
    'RELEASING.md' => [
        '## Approved Alpha 3 identity',
        'Composer version: `0.1.0-alpha.3`',
        'Framework tag: `v0.1.0-alpha.3`',
        'Skeleton tag: `v0.1.0-alpha.3`',
        'The accountable human approved the following release identity and gated publication sequence on 2026-07-21',
        'prove the clean public installation path; create both GitHub prereleases; and announce Alpha 3.',
        'The exact candidate commits, release date, artifact references, and gate evidence belong in the external release evidence',
        'If any mandatory check fails, the next external operation remains unauthorized until a new candidate passes.',
    ],
    'docs/releases/0.1.0-alpha.3.md' => [
        'Release identity: `0.1.0-alpha.3`. Publication state is external',
        'Identity and gated publication authorization do not announce either tag, either package, or the public installation path.',
        'It is not production-ready and makes no backward-compatibility promise across prereleases.',
        'external release evidence recorded through `RELEASING.md`',
        'composer create-project --stability=alpha phpthis/skeleton',
    ],
    'docs/decisions/031-bounded-alpha-3-release-scope.md' => [
        'Status: accepted',
        'Alpha 3 is accepted as a tooling-only release',
        'Publication state is external.',
        'Consumer Contract version 7, Strict Profile version 2, diagnostics `PHT001` through `PHT006`',
    ],
    'docs/decisions/README.md' => [
        '`031-bounded-alpha-3-release-scope.md`',
    ],
    'composer.json' => [
        '"/.DS_Store"',
    ],
    'tools/package-files.txt' => [
        'docs/releases/0.1.0-alpha.3.md',
        'docs/decisions/031-bounded-alpha-3-release-scope.md',
    ],
];

foreach ($alpha3ReleaseIdentityArtifactMarkers as $relativePath => $markers) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        $failures[] = "Cannot read approved Alpha 3 identity artifact {$relativePath}.";
        continue;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $failures[] = "The approved Alpha 3 identity marker is missing from {$relativePath}.";
        }
    }
}

$alpha4ReleaseIdentityArtifactMarkers = [
    'RELEASING.md' => [
        '## Approved Alpha 4 identity',
        'Composer version: `0.1.0-alpha.4`',
        'Framework tag: `v0.1.0-alpha.4`',
        'Skeleton tag: `v0.1.0-alpha.4`',
        'The accountable human approved the following release identity and gated publication sequence on 2026-07-23',
        'prove the clean public installation path; create both GitHub prereleases; and announce Alpha 4.',
        'If any mandatory check fails, the next external operation remains unauthorized until a new candidate passes.',
    ],
    'docs/releases/0.1.0-alpha.4.md' => [
        'Release identity: `0.1.0-alpha.4`. Publication state is external',
        'Identity and gated publication authorization do not announce either tag, either package, or the public installation path.',
        'It is not production-ready and makes no backward-compatibility promise across prereleases.',
        'Consumer Contract version 7 to version 9',
        'At the Alpha 4 tag, `docs/consumer-contract.md` defined Consumer Contract version 9',
        'composer create-project --stability=alpha phpthis/skeleton',
    ],
    'docs/decisions/035-bounded-alpha-4-release-scope.md' => [
        'Status: accepted',
        'Alpha 4 is accepted as the bounded rollup of the changes after Alpha 3',
        'Composer version: `0.1.0-alpha.4`',
        'framework tag: `v0.1.0-alpha.4`',
        'skeleton tag: `v0.1.0-alpha.4`',
        'Strict Profile version 2 and permanent diagnostics `PHT001` through `PHT006`',
        'Alpha 4 does not add or permit an ORM',
    ],
    'docs/decisions/README.md' => [
        '`035-bounded-alpha-4-release-scope.md`',
    ],
    'composer.json' => [
        '--memory-limit=512M',
    ],
    'tools/package-files.txt' => [
        'docs/releases/0.1.0-alpha.4.md',
        'docs/decisions/033-application-owned-request-handler-decorators.md',
        'docs/decisions/035-bounded-alpha-4-release-scope.md',
    ],
];

foreach ($alpha4ReleaseIdentityArtifactMarkers as $relativePath => $markers) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        $failures[] = "Cannot read approved Alpha 4 identity artifact {$relativePath}.";
        continue;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $failures[] = "The approved Alpha 4 identity marker is missing from {$relativePath}.";
        }
    }
}

$alpha5ReleaseIdentityArtifactMarkers = [
    '.ai/README.md' => [
        '`docs/releases/0.1.0-alpha.5.md`',
        'ADR 040, ADR 036 through ADR 039 for the Alpha 5 rollup',
    ],
    '.ai/application-context.md' => [
        'The accepted Alpha 5 authority is ADR 040.',
        'ADR 015 through ADR 035 forward and rolls accepted ADR 036 through ADR 039',
    ],
    '.ai/testing.md' => [
        'exactly 177 named behaviors',
        'PHPUnit belongs only to this repository\'s root `require-dev`',
    ],
    'README.md' => [
        'The bounded [Alpha 5 scope]',
        'Consumer Contract version 10, Strict Profile version 3, and permanent diagnostic `PHT007`',
        'adds no configuration runtime, ORM, repository, binding helper, permission helper, migration framework, generic middleware, or native WebSocket runtime',
    ],
    'RELEASING.md' => [
        '## Approved Alpha 5 identity',
        'Composer version: `0.1.0-alpha.5`',
        'Framework tag: `v0.1.0-alpha.5`',
        'Skeleton tag: `v0.1.0-alpha.5`',
        'The accountable human approved preparation of the following bounded release scope and exact identity on 2026-08-01',
        'This approval authorizes source preparation and local verification only.',
        'Those external operations require later explicit accountable-human authorization after the candidate evidence is reviewed.',
        'If any mandatory check fails, the next external operation remains unauthorized until a new candidate passes.',
    ],
    'ROADMAP.md' => [
        '## Phase 5: Alpha 5 maintainer tooling, onboarding, and database hardening',
        'ADR 040 accepts the bounded Alpha 5 source scope and exact identity',
        'Alpha 5 publication state is external',
    ],
    'SECURITY.md' => [
        'The accepted Alpha 5 scope',
        'An Alpha 5 release may be announced only after the public-artifact gate',
    ],
    'docs/getting-started.md' => [
        'The bounded Alpha 5 scope is accepted',
        'Consumer Contract version 10 and Strict Profile version 3',
        'Package availability is an external fact',
    ],
    'docs/guardrails.md' => [
        "Alpha 5's Consumer Contract version 10 and Strict Profile version 3 authority",
    ],
    'docs/knowledge-map.md' => [
        '`docs/releases/0.1.0-alpha.5.md`',
        'ADR 040, ADR 036 through ADR 039 for the Alpha 5 rollup',
        "Alpha 5's Consumer Contract version 10, Strict Profile version 3, PHT007, database setup scope gate, application-owned database authority lifecycle, and recommended migration placement",
    ],
    'docs/releases/0.1.0-alpha.5.md' => [
        'Release identity: `0.1.0-alpha.5`. Publication state is external',
        'Identity and candidate-preparation approval do not announce or authorize either tag, either package, the dedicated-skeleton update, a GitHub release, or the public installation path.',
        'It is not production-ready and makes no backward-compatibility promise across prereleases.',
        'Alpha 4 consumers move from Consumer Contract version 9 to version 10 and Strict Profile version 2 to version 3',
        'At the Alpha 4 tag, `docs/consumer-contract.md` defined Consumer Contract version 9 and Strict Profile version 2.',
        'composer create-project --stability=alpha phpthis/skeleton',
    ],
    'docs/decisions/040-bounded-alpha-5-release-scope.md' => [
        'Status: accepted',
        'Alpha 5 is accepted as the bounded rollup of exactly these changes after Alpha 4',
        'Composer version: `0.1.0-alpha.5`',
        'framework tag: `v0.1.0-alpha.5`',
        'skeleton tag: `v0.1.0-alpha.5`',
        'Strict Profile version 2 to version 3 with permanent diagnostic `PHT007`',
        'Alpha 5 does not add or permit an ORM',
        'Publication state is external.',
    ],
    'docs/decisions/README.md' => [
        '`040-bounded-alpha-5-release-scope.md`',
    ],
    'composer.json' => [
        '"phpunit/phpunit": "^13.0"',
    ],
    'tools/package-files.txt' => [
        'docs/releases/0.1.0-alpha.5.md',
        'docs/decisions/036-one-typed-application-configuration-boundary.md',
        'docs/decisions/040-bounded-alpha-5-release-scope.md',
    ],
];

foreach ($alpha5ReleaseIdentityArtifactMarkers as $relativePath => $markers) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        $failures[] = "Cannot read approved Alpha 5 identity artifact {$relativePath}.";
        continue;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $failures[] = "The approved Alpha 5 identity marker is missing from {$relativePath}.";
        }
    }
}

$currentConsumerContractVersionMarkers = [
    'docs/consumer-contract.md' => 'Contract version: 10',
    'docs/getting-started.md' => 'contract-version-10 Composer scripts',
    'skeleton/.ai/README.md' => 'Consumer Contract v10 and Strict Profile v3 remain mandatory.',
    'skeleton/.ai/rules.md' => 'These rules supplement installed PHPThis Consumer Contract v10 and Strict Profile v3',
    'templates/application/.ai/README.md' => 'Consumer Contract v10 and Strict Profile v3 remain mandatory.',
    'templates/application/.ai/rules.md' => 'These rules supplement installed PHPThis Consumer Contract v10 and Strict Profile v3',
];

foreach ($currentConsumerContractVersionMarkers as $relativePath => $marker) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents) || !str_contains($contents, $marker)) {
        $failures[] = "The current Consumer Contract version marker is missing from {$relativePath}.";
    }
}

$configurationArtifactMarkers = [
    'docs/decisions/036-one-typed-application-configuration-boundary.md' => [
        'Status: accepted',
        'Consumer Contract version 10 and Strict Profile version 3 add permanent structural rule `PHT007`.',
        'No application or deployment configuration runtime or class enters framework `src/`, and no runtime dependency is added.',
        'Adopted migration or administrative configuration never falls back to runtime configuration.',
    ],
    'docs/configuration.md' => [
        '# Application-owned configuration',
        'every read in the Composer project must occur in one PHP file',
        "\\getenv('APP_RUNTIME_DATABASE_DSN')",
        'private static function required(#[\\SensitiveParameter] string|false $value, int $maximumBytes): string',
        '->handle($_SERVER, $_GET, $_POST, $_FILES)',
        'HTTP calls only `forHttp()`.',
        'When migrations are adopted, their command calls only `forMigrations()`',
        'PHPThis does not load it',
        '#[\\SensitiveParameter]',
        '### Copyable child-process configuration evidence',
        'function runConfigurationProcess(',
        'function requireConfigurationOutputExcludes(',
        "'' => 'APP_RUNTIME_MODE='",
        "fifth `proc_open` environment argument",
        'It treats an empty-string array key as a raw environment entry',
        'This is pinned PHP 8.4 implementation behavior rather than a general environment-array convention',
        'absence of deliberate parent-configuration inheritance',
        'Do not grow this configuration example into a general process runner, worker, or supervisor.',
    ],
    'docs/consumer-contract.md' => [
        '## Application configuration',
        'PHT007',
        'For each adopted process profile, keep its runtime, worker, migration, or administrative input names, factories, and output types separate.',
        'non-secret configuration reference',
        'A configuration-free application records `NOT_APPLICABLE(CONFIGURATION)`',
    ],
    'docs/decisions/README.md' => [
        "`035-bounded-alpha-4-release-scope.md`\n- `036-one-typed-application-configuration-boundary.md`",
    ],
    'docs/strict-profile.md' => [
        'Profile version: 3',
        '`PHT007`',
        'one application-owned PHP file',
    ],
    'templates/application/.ai/configuration.md' => [
        '{{CONFIGURATION_BOUNDARY_PATH_OR_NOT_APPLICABLE}}',
        '{{CONFIGURATION_AUTHORITY_SEPARATION_OR_NOT_APPLICABLE}}',
        '{{CONFIGURATION_REDACTION_EVIDENCE_OR_NOT_APPLICABLE}}',
    ],
    'skeleton/.ai/configuration.md' => [
        '`NOT_APPLICABLE(CONFIGURATION)`',
        'The health-only skeleton reads no process environment',
    ],
    'example/.ai/configuration.md' => [
        '# Example application configuration context',
        'not the standalone skeleton consumer checked by `ApplicationChecker`',
        '`NOT_APPLICABLE(PROCESS_ENVIRONMENT)`',
        'HTTP reaches only `http()`',
        'does not prove production operating-system identities or database grants',
    ],
    'verification/EnvironmentAccessProfile.php' => [
        'final class EnvironmentAccessProfile',
        'public static function inspect(string $contents, string $relativePath): array',
        'public static function boundaryFailures(array $readsByFile): array',
        'private static function isLiteralCallableReference(',
        'private static function isConstantLookupArgument(array $tokens, int $index): bool',
        'private static function isCanonicalServerTransportHandoff(',
        'PHT007',
    ],
    'verification/ApplicationChecker.php' => [
        "'.ai/configuration.md'",
        'EnvironmentAccessProfile::inspect(',
        'EnvironmentAccessProfile::boundaryFailures($environmentReads)',
    ],
    'bin/phpthis' => [
        "require_once dirname(__DIR__) . '/verification/EnvironmentAccessProfile.php';",
    ],
    'src/Database/Connection.php' => [
        '#[\\SensitiveParameter]',
    ],
    'tests/run.php' => [
        'connection marks only its password argument as sensitive',
    ],
    'tools/test-strict-profile.php' => [
        "'PHT007'",
        'PHT007 invalid-access fixture diagnostics changed.',
    ],
    'tools/test-consumer-project.php' => [
        'proveInstalledTypedConfiguration($project, $profileCommand, $environment);',
        'proveInstalledConfigurationEvidenceReference(',
        'proveConfigurationContextIsRequired($project, $profileCommand, $environment);',
        'proveEnvironmentAccessIsRejected($project, $profileCommand, $environment);',
        "selectOneRow('SELECT 1 AS configured')",
        'requireExactProcessResult(',
        'requireExactFailureLines(',
        'PASS installed runtime typed configuration delivery',
        'PASS installed migration typed configuration delivery',
        'The installed configuration evidence reference is missing.',
        'PASS child-process configuration evidence',
        'PASS installed empty configuration delivery',
        'environmentWithEmptyValue(',
        'final class ReferenceEmptyRuntimeMode extends InvalidArgumentException',
        'catch (ReferenceEmptyRuntimeMode)',
        'The installed missing runtime mode was misclassified as empty.',
    ],
    'docs/guardrails.md' => [
        'extracts the exact application-owned child-process reference from installed `docs/configuration.md`',
        "PHP 8.4's empty-string-key raw `NAME=` environment-entry form",
        'invokes the matching factory and proves that this raw form reaches its exact empty-value validation branch',
        'a paired run with the same variable omitted proves that the missing-value branch remains distinct',
        'an explicit synthetic application environment through the fifth `proc_open` argument instead of null inheritance',
        'explicit binary pipe descriptors and working directory',
        'the application test runner or CI job owns its hard outer timeout',
        'does not claim that the host, executable, or PHP runtime adds no required environment entries',
        'does not prove application-specific validation, deployment safety, or redaction outside the captured streams',
    ],
    'tools/package-files.txt' => [
        'docs/configuration.md',
        'docs/decisions/036-one-typed-application-configuration-boundary.md',
        'templates/application/.ai/configuration.md',
        'verification/EnvironmentAccessProfile.php',
    ],
];

foreach ($configurationArtifactMarkers as $relativePath => $markers) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        $failures[] = "Cannot read configuration-boundary artifact {$relativePath}.";
        continue;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $failures[] = "Configuration-boundary artifact marker is missing from {$relativePath}: {$marker}";
        }
    }
}

$startupProbeSemanticsArtifactMarkers = [
    '.ai/README.md' => [
        'Define, change, or review startup, liveness, dependency health, or readiness semantics',
        '`.ai/database.md` only when a database dependency is involved',
    ],
    '.ai/application-context.md' => [
        'That sink\'s destination may itself involve network or remote-filesystem I/O.',
        'Until its destination and latency are verified, describe the starter only as the current liveness route and HTTP composition proof, not as external-service-independent liveness.',
        'Do not add a framework probe API, lazy connection, hidden bypass, second HTTP execution path, universal readiness definition, or checker diagnostic for operational semantics.',
    ],
    'src/Database/Connection.php' => [
        'new PDO($dsn, $username, $password, $defaults + $options),',
    ],
    'docs/configuration.md' => [
        '### Eager composition and probe semantics',
        '`Connection::connect()` constructs native `PDO` immediately rather than returning a deferred handle.',
        'Depending on the selected driver and DSN, construction may perform database, filesystem, or network I/O and may fail during composition.',
        'Successful connection construction is also not evidence of schema compatibility, migration completion, capacity, per-operation database authority, or complete application readiness.',
        'Failure isolation that preserves a selected response does not by itself bound a synchronous sink\'s latency or make that probe external-service-independent.',
        'Do not disguise a dependency bypass as the ordinary application bootstrap or add a second hidden HTTP execution path.',
    ],
    'docs/knowledge-map.md' => [
        'Define, change, or review startup, liveness, dependency health, or readiness semantics',
        'verify that no framework probe API, lazy connection, hidden bypass, or second HTTP execution path was introduced',
    ],
    'docs/vocabulary.md' => [
        '| external-service-independent liveness |',
        '| readiness | application-owned operational claim that its recorded conditions for receiving traffic are satisfied |',
    ],
    'docs/guardrails.md' => [
        'A separate installed distribution proof checks the eager-composition and probe-semantics clarification',
        'the current starter does not claim external-service independence while its deployment-configured `error_log` destination and latency remain unverified',
        'does not connect to a service, prove that a deployment classified a probe correctly, establish dependency availability or traffic readiness',
    ],
    'templates/application/.ai/README.md' => [
        'Change runtime, logging, deployment, liveness, or readiness behavior',
        'exact probe claim, inherited dependencies, bounds, failure behavior, local or deployment operations owner, evidence',
    ],
    'templates/application/.ai/operations.md' => [
        '{{HEALTH_AND_READINESS_PATHS}}',
        '`Connection::connect()` constructs PDO eagerly and, depending on the selected driver and DSN, may perform I/O or fail during composition.',
        'must not be described as external-service-independent liveness.',
    ],
    'templates/application/.ai/testing.md' => [
        'Every adopted health, readiness, or non-HTTP probe proves the exact claim recorded in `.ai/operations.md`',
        'A caught sink failure proves response isolation, not a latency bound or independence from that sink\'s destination.',
        'Connection construction alone is not exact-statement database-authority or complete-readiness evidence.',
    ],
    'skeleton/.ai/README.md' => [
        'Change runtime, logging, liveness, or readiness behavior',
        'exact probe claim, inherited dependencies, bounds, failure behavior, local or deployment operations owner, and evidence',
    ],
    'skeleton/.ai/operations.md' => [
        '`GET /health` is the starter liveness route; no readiness route exists.',
        'It does not establish external-service-independent liveness because the deployment-configured `error_log` destination and its latency are unverified.',
        'covering success, mapped failure, unknown failure, captured summaries, throwing-sink isolation, and the real front controller.',
        '`Connection::connect()` constructs PDO eagerly and may fail during composition',
        'Do not preserve a liveness claim through a hidden bypass or second HTTP execution path.',
    ],
    'skeleton/.ai/observability.md' => [
        'calls deployment-configured `error_log` synchronously before the coordinator returns',
        'throwing-sink response isolation',
    ],
    'skeleton/.ai/testing.md' => [
        'This proves the current HTTP composition and response path, not external-service-independent liveness',
        'the coordinator invokes deployment-configured `error_log` synchronously and no destination or latency bound is recorded.',
        'do not treat connection construction as database-authority or complete-readiness evidence.',
    ],
    'tools/test-consumer-project.php' => [
        'proveInstalledStartupProbeGuidanceDistribution($project, $installedFramework);',
        'function proveInstalledStartupProbeGuidanceDistribution(string $project, string $installedFramework): void',
        'PASS installed startup and probe guidance distribution',
    ],
];

foreach ($startupProbeSemanticsArtifactMarkers as $relativePath => $markers) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        $failures[] = "Cannot read startup and probe semantics artifact {$relativePath}.";
        continue;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $failures[] = "Startup and probe semantics artifact marker is missing from {$relativePath}: {$marker}";
        }
    }
}

$databaseSetupScopeArtifactMarkers = [
    'AGENTS.md' => [
        'invent elevated authority for a deferred path.',
        'configuration-only scope instead records connection composition as deferred and proves its parser in a child process.',
    ],
    'docs/decisions/037-database-setup-scope-gate.md' => [
        'Status: accepted',
        '> Please setup PostgreSQL as our main DB.',
        'database scope: add configuration structure only, connect to an existing server, or provision a project-local server',
        'schema scope: defer migrations or add an application-owned migration foundation',
        'This is an AI-authoring workflow clarification.',
    ],
    'docs/decisions/README.md' => [
        '`037-database-setup-scope-gate.md`',
    ],
    'docs/consumer-contract.md' => [
        'For an ambiguous database setup request, inspect the prompt and existing project state first.',
        'Ask all unresolved choices in one concise message',
        'Do not perform external database I/O, provision or mutate a server',
        'ADR 037 adds the early database setup scope gate as an AI-authoring workflow clarification',
    ],
    'docs/configuration.md' => [
        '## Scope database setup before implementation',
        '> Please setup PostgreSQL as our main DB.',
        'should I only add PostgreSQL configuration, connect this project to an existing PostgreSQL server, or provision a project-local PostgreSQL server?',
        'Record the non-secret input contract and add its typed parser or factory with parsing, failure, redaction, and child-process evidence.',
        'Configuration-only scope records infrastructure injection and connection evidence as deferred and does not create dead wiring.',
        'For PostgreSQL or another engine, first record an engine-specific application decision',
        'when migrations are deferred, omit the migration inputs, type, factory, entrypoint, and tests',
        'Provisioning and production evidence is required only for an explicitly selected scope.',
    ],
    'docs/evaluation.md' => [
        '## Database setup scope-gate evaluation',
        'A starter not-applicable marker does not answer that adoption question.',
        'no connection attempt or other external database I/O',
        'they do not prove that a particular model follows them or meets a duration target',
    ],
    'docs/knowledge-map.md' => [
        '| Select or set up a database engine |',
        'load and prove only the selected slice',
        'ADR 040, ADR 036 through ADR 039 for the Alpha 5 rollup',
    ],
    'docs/guardrails.md' => [
        "accepted ADR 037, its early application scope gate, configuration-only typed-boundary meaning, external-I/O prohibition before approval, conditional process profiles, package inventory, and installed-consumer guidance-distribution evidence remain present",
        'the guard also rejects the reviewed unconditional composition, elevated-profile, and template-placeholder wording',
        "It also verifies that the local skeleton and installed framework distribute ADR 037's database setup guidance.",
        'This distribution proof does not establish that an AI asks the scope question, avoids external database I/O, or meets a duration target',
    ],
    '.ai/application-context.md' => [
        'Keep the ADR 037 database setup scope gate in both application `AGENTS.md` entrypoints and change workflows.',
        'It records injection sites when process or infrastructure composition is selected, or explicitly deferred connection composition for configuration-only scope.',
    ],
    '.ai/database.md' => [
        'Each adopted runtime, migration, or administrative factory uses a distinct name and never falls back.',
        'configuration-only scope records connection composition as deferred.',
    ],
    'templates/application/AGENTS.md' => [
        '## Early database setup gate',
        'Apply this gate before the full task read order',
        'Local development is context, not authorization to connect to or probe a server, install, provision, or mutate anything.',
        'A current `NOT_APPLICABLE` marker describes present behavior and does not resolve intent for a new adoption request.',
        'Record isolated migration or administrative authority only when that elevated path is adopted.',
        'for configuration-only scope, record connection composition as deferred and prove the parser in a child process.',
    ],
    'skeleton/AGENTS.md' => [
        '## Early database setup gate',
        'Apply this gate before the full task read order',
        'Local development is context, not authorization to connect to or probe a server, install, provision, or mutate anything.',
        'A current `NOT_APPLICABLE` marker describes present behavior and does not resolve intent for a new adoption request.',
        'Record isolated migration or administrative authority only when that elevated path is adopted.',
        'Record visible injection sites for adopted infrastructure, or explicitly defer connection composition when configuration-only scope stops before it.',
    ],
    'templates/application/.ai/README.md' => [
        '| Select or set up a database engine |',
        'no external database I/O or mutation before unresolved scope is clarified',
        'visible adopted composition or explicit connection-composition deferral',
    ],
    'skeleton/.ai/README.md' => [
        '| Select or set up a database engine |',
        'no external database I/O or mutation before unresolved scope is clarified',
        'visible adopted composition or explicit connection-composition deferral',
    ],
    'templates/application/.ai/change-workflow.md' => [
        '## Ambiguous database setup scope',
        '> Please setup PostgreSQL as our main DB.',
        'Treat a current `NOT_APPLICABLE` marker as present-state evidence',
    ],
    'skeleton/.ai/change-workflow.md' => [
        '## Ambiguous database setup scope',
        '> Please setup PostgreSQL as our main DB.',
        'Treat a current `NOT_APPLICABLE` marker as present-state evidence',
    ],
    'templates/application/.ai/configuration.md' => [
        'Record only adopted external input contracts.',
        'do not store task scope or task history here',
        'Composition injection sites, or deferred connection composition for configuration-only scope',
    ],
    'skeleton/.ai/configuration.md' => [
        'Database-engine selection does not authorize a connection attempt, server provisioning, or migration adoption.',
        'one separately named factory and final readonly output type for each adopted process profile',
        'child-process parser or adopted-entrypoint evidence',
    ],
    'templates/application/.ai/data.md' => [
        'Record a separate migration or administrative identity only when that path is adopted',
        '{{ELEVATED_DATABASE_IDENTITY_REFERENCE_OR_NOT_APPLICABLE}}',
        '{{ELEVATED_DATABASE_AUTHORITY_ISOLATION_OR_NOT_APPLICABLE}}',
    ],
    'skeleton/.ai/data.md' => [
        'a separate migration or administrative identity only when that elevated path is adopted',
    ],
    'templates/application/.ai/testing.md' => [
        'Provisioning and production evidence is required only for explicitly selected scopes.',
    ],
    'skeleton/.ai/testing.md' => [
        'Provisioning and production evidence is required only for explicitly selected scopes.',
    ],
    'tools/test-consumer-project.php' => [
        'proveInstalledDatabaseSetupGuidanceDistribution($project, $installedFramework);',
        'PASS installed database setup guidance distribution',
    ],
    'tools/package-files.txt' => [
        'docs/decisions/037-database-setup-scope-gate.md',
    ],
];

foreach ($databaseSetupScopeArtifactMarkers as $relativePath => $markers) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        $failures[] = "Cannot read database setup scope artifact {$relativePath}.";
        continue;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $failures[] = "Database setup scope artifact marker is missing from {$relativePath}: {$marker}";
        }
    }
}

$databaseSetupScopeForbiddenArtifactMarkers = [
    '.ai/database.md' => [
        'Runtime, migration, and administrative factories use distinct names and never fall back.',
        'Inject only the runtime type into visible HTTP `Connection::connect` construction;',
    ],
    'templates/application/.ai/README.md' => [
        'authority separation, explicit composition, rotation/restart',
    ],
    'skeleton/.ai/README.md' => [
        'authority separation, explicit composition, rotation/restart',
    ],
    'templates/application/.ai/data.md' => [
        '{{CONNECTION_1_MIGRATION_IDENTITY_REFERENCE}}',
        '{{CONNECTION_1_AUTHORITY_ISOLATION_MECHANISM}}',
    ],
];

foreach ($databaseSetupScopeForbiddenArtifactMarkers as $relativePath => $markers) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        $failures[] = "Cannot read database setup scope artifact {$relativePath}.";
        continue;
    }

    foreach ($markers as $marker) {
        if (str_contains($contents, $marker)) {
            $failures[] = "Forbidden database setup scope artifact marker remains in {$relativePath}: {$marker}";
        }
    }
}

$databaseAuthorityLifecycleArtifactMarkers = [
    'AGENTS.md' => [
        'Keep the database and object definition source, database/catalog/schema/attachment namespace selection and qualification as supported, namespace and object control-or-ownership model (`NOT_APPLICABLE` when the engine has no ownership concept), and active database authority as separate application facts.',
        'record how migration, authority activation, verification, rollout, traffic, later deactivation, and failure recovery are ordered.',
        'before dependent code receives traffic, execute its exact statements under the runtime identity and safely verify selected prohibited actions against the recorded engine and version.',
    ],
    'docs/decisions/038-application-owned-database-authority-lifecycle.md' => [
        'Status: accepted',
        'Configuration and source presence do not activate database authority.',
        'Withholding all runtime object access is valid before a named application operation exists.',
        'The installed application checker adds one deliberately narrow context-consistency check',
        'No framework runtime type or dependency is added.',
    ],
    'docs/decisions/README.md' => [
        '`038-application-owned-database-authority-lifecycle.md`',
    ],
    'docs/consumer-contract.md' => [
        'treat zero runtime object access as valid before a named application operation exists',
        'record how effective authority resolves under the selected engine, using only applicable direct, role or inherited, public or default, database or global, ownership-chain, IAM, filesystem or process, or other engine-specific sources',
        'record the application-owned ordering among migration, authority activation, exact-engine authority verification, application rollout, and traffic enablement',
        'Configuration parsing, successful connectivity, `SELECT 1`, object existence, and migration success do not prove usable runtime authority.',
        'adds one ordinary context-consistency failure without a `PHT` diagnostic',
    ],
    'docs/database.md' => [
        '### Authority activation lifecycle',
        'Configuration and source presence do not activate database authority.',
        'Database and object definition source; database/catalog/schema/attachment namespace selection and qualification as supported; namespace and object control-or-ownership; and active authority are separate facts.',
        'Record only applicable sources, such as direct, role or inherited, public or default, database or global, ownership-chain, IAM, or filesystem and process authority.',
        'Each adopted authority activation or deactivation has one explicit application-owned owner and path.',
        '`GRANT` or `REVOKE` SQL may be visible and checksum-covered inside a migration when the selected engine supports and uses it',
        'PHPThis chooses no universal migration-first, code-first, rolling, or maintenance-window sequence.',
    ],
    'docs/security.md' => [
        'Withholding runtime object access is valid until a named operation exists.',
        'Account for effective authority using only the engine\'s applicable direct, role or inherited, public or default, database or global, ownership-chain, IAM, filesystem or process, or other sources.',
        'Every authority activation and deactivation has one recorded application-owned owner and non-HTTP path.',
        '`GRANT` or `REVOKE` SQL is supported, selected, and part of a migration',
        'PHPThis neither requires nor discourages an engine-default or application-specific database, catalog, schema, attachment namespace, or equivalent.',
    ],
    'docs/migrations.md' => [
        '## Authority transition and release handoff',
        'Migration success proves the migration path only.',
        'Before dependent code receives traffic, positive evidence executes its exact runtime statements under the runtime identity',
        'PHPThis does not prescribe migration-first or code-first rollout.',
        'does not establish production lock duration, availability, free-space behavior, crash recovery, backup restore, live effective authority, release ordering',
    ],
    'docs/knowledge-map.md' => [
        'ADR 040, ADR 036 through ADR 039 for the Alpha 5 rollup',
        'supported database/catalog/schema/attachment namespace selection and qualification, namespace and object control-or-ownership model, per-operation runtime authority, activation and deactivation ownership, exact-engine positive and negative evidence',
    ],
    'docs/guardrails.md' => [
        'application-owned canonical `PHPThis\Database\Connection::connect` calls cannot coexist with a standalone `NOT_APPLICABLE(DATABASE)` declaration',
        "A separate installed distribution proof checks that ADR 038's application-owned authority lifecycle remains present",
        'This marker proof is a source-distribution check only: it performs no live authority probe, validates no engine privilege or control model',
    ],
    '.ai/application-context.md' => [
        "Keep ADR 038's application-owned database authority lifecycle in the consumer contract, both application contexts, and compact task routing.",
        'Configuration, connectivity, object existence, and migration completion do not activate authority.',
        'Do not prescribe a namespace, identity topology, default privilege, universal deployment order, permission helper, runtime introspection, or automatic hook.',
    ],
    'templates/application/AGENTS.md' => [
        'database, catalog, schema, or attachment namespace selection and qualification as supported by the chosen engine',
        'namespace and object control or ownership model, with explicit not-applicable facts where the engine has no such model',
        'effective authority resolution source, using only applicable mechanisms such as direct privileges, roles or inheritance, public or default access, database or global privileges, ownership chains, IAM, or filesystem and process authority',
        'Give each adopted authority activation or deactivation one non-HTTP owner and path; record `GRANT` or `REVOKE` only where supported.',
        'activate and verify it before dependent code receives traffic',
    ],
    'templates/application/.ai/data.md' => [
        'Its first non-heading declaration is the canonical standalone marker:',
        "\n`NOT_APPLICABLE(DATABASE)`\n",
        '{{CONNECTION_1_DATABASE_DEFINITION_OR_PROVISIONING_SOURCE}}',
        '{{CONNECTION_1_NAMESPACE_SELECTION_AND_QUALIFICATION_POLICY}}',
        '{{CONNECTION_1_NAMESPACE_AND_OBJECT_CONTROL_OR_OWNERSHIP_MODEL_OR_NOT_APPLICABLE}}',
        '{{DATABASE_AUTHORITY_1_CONNECTION_AND_OPERATION}}',
        '{{DATABASE_AUTHORITY_1_EFFECTIVE_AUTHORITY_RESOLUTION_SOURCE}}',
        '{{DATABASE_AUTHORITY_ACTIVATION_AND_DEACTIVATION_PATH_OR_NOT_APPLICABLE}}',
        'Authority activation and deactivation owner, complete non-HTTP path, and transition source; `GRANT` and `REVOKE` only where supported',
        'Activate and verify authority against the exact engine and version before dependent code receives traffic.',
    ],
    'templates/application/.ai/migrations.md' => [
        '{{MIGRATION_ENGINE_DECISION_SOURCE_OR_NOT_APPLICABLE}}',
        '{{MIGRATION_REQUIRED_AND_PROHIBITED_CAPABILITIES_OR_NOT_APPLICABLE}}',
        '{{MIGRATION_AUTHORITY_TRANSITION_PATH_OR_NOT_APPLICABLE}}',
        '{{MIGRATION_RUNTIME_AUTHORITY_HANDOFF_AND_EVIDENCE_OR_NOT_APPLICABLE}}',
        '{{MIGRATION_RELEASE_SEQUENCE_OR_NOT_APPLICABLE}}',
        'Migration success alone does not prove runtime authority is active.',
    ],
    'templates/application/.ai/operations.md' => [
        '{{DATABASE_AUTHORITY_AND_RELEASE_DECISION_SOURCE_OR_NOT_APPLICABLE}}',
        '{{DATABASE_AUTHORITY_TRANSITION_OPERATIONS_OR_NOT_APPLICABLE}}',
        '{{DATABASE_RELEASE_SEQUENCE_OR_NOT_APPLICABLE}}',
        '{{DATABASE_COMPATIBILITY_DEACTIVATION_AND_REMOVAL_POLICY_OR_NOT_APPLICABLE}}',
        '{{DATABASE_PRE_TRAFFIC_AUTHORITY_GATE_EVIDENCE_AND_OWNER_OR_NOT_APPLICABLE}}',
        'there is no universal order beyond activating and verifying required authority before dependent traffic',
    ],
    'templates/application/.ai/testing.md' => [
        'executes every intended statement for each named operation under the runtime identity before traffic',
        'selected prohibited namespace, data-definition, identity or role, authority-administration, migration-ledger, database or global, and unrelated-target capabilities',
        'direct privileges, roles or inheritance, public or default access, database or global privileges, ownership chains, IAM, or filesystem and process authority',
        'Configuration, connectivity, target existence, and migration success are not authority evidence.',
    ],
    'skeleton/.ai/data.md' => [
        "\n`NOT_APPLICABLE(DATABASE)`\n",
        'database/catalog/schema/attachment namespace selection and qualification as supported',
        'namespace and object control or ownership model or explicit N/A',
        'direct privileges, roles or inheritance, public or default access, database or global privileges, ownership chains, IAM, or filesystem and process authority',
        'one non-HTTP owner and path for every adopted authority activation and deactivation, `GRANT` and `REVOKE` only where supported',
        'Configuration, connectivity, target existence, and migration completion do not activate runtime authority.',
    ],
    'skeleton/.ai/migrations.md' => [
        'one owner and complete non-HTTP path for each authority activation and deactivation, with `GRANT` and `REVOKE` only where supported',
        'runtime-authority activation handoff, exact-engine positive and negative verification',
        'application rollout and traffic-enablement order',
        'Migration success alone does not prove runtime authority is active.',
    ],
    'skeleton/.ai/operations.md' => [
        'authority-transition owner or activation stage',
        'application-owned order and compatibility among migration, authority activation, exact-engine verification, application rollout, traffic enablement, later authority deactivation',
        'No universal deployment order is inferred',
    ],
    'skeleton/.ai/testing.md' => [
        'Execute every intended statement under the runtime identity before traffic',
        'elevated configuration remains unavailable to HTTP',
        'direct privileges, roles or inheritance, public or default access, database or global privileges, ownership chains, IAM, or filesystem and process authority',
        'each adopted authority activation and deactivation has one visible non-HTTP owner and path, record `GRANT` and `REVOKE` only where supported',
        'Configuration, connectivity, target existence, migration success, PHT006, tenant predicates, and adversarial bindings are not universal authority',
    ],
    'verification/ApplicationChecker.php' => [
        'private function databaseContextConnectionFailures(',
        'private function hasCanonicalConnectionCall(',
        'private function importAliases(',
        'private function resolvedClassName(',
        'Application data context declares no database while application-owned PHP calls PHPThis\\\\Database\\\\Connection::connect;',
        'T_NAME_FULLY_QUALIFIED',
    ],
    'tools/test-consumer-project.php' => [
        'proveInstalledDatabaseAuthorityLifecycleGuidanceDistribution($project, $installedFramework);',
        'proveDatabaseContextConnectionConsistency($project, $profileCommand, $environment);',
        'DatabaseContextOrdinaryControl',
        'DatabaseContextAliasControl',
        'DatabaseContextGroupedControl',
        'DatabaseContextNamespaceAliasControl',
        'DatabaseContextNamespaceImportControl',
        'DatabaseContextCurrentNamespaceControl',
        'DatabaseContextFullyQualifiedControl',
        'The isolated database-context diagnostic changed.',
        'CRLF database context bypassed the not-applicable Connection::connect check.',
        'The legacy starter no-database declaration bypassed the Connection::connect check.',
        'It therefore has no SQL, structural selectors, bounded data lists',
        'an unmatched leading backtick',
        'an unmatched trailing backtick',
        'legacy text quoted inside adopted prose',
        'A comment or string mentioning Connection::connect was mistaken for executable database use.',
        'private const CONNECTION_TYPE = Connection::class;',
        'installedSyntheticDatabaseContext()',
        'Structural namespace/control model: SQLite\'s default `main` attachment namespace exists only inside each in-memory proof connection;',
        'no live authority probe runs.',
        'PASS installed database-context connection consistency',
        'PASS installed database authority lifecycle guidance distribution',
    ],
    'tools/package-files.txt' => [
        'docs/decisions/038-application-owned-database-authority-lifecycle.md',
    ],
];

foreach ($databaseAuthorityLifecycleArtifactMarkers as $relativePath => $markers) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        $failures[] = "Cannot read database authority lifecycle artifact {$relativePath}.";
        continue;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $failures[] = "Database authority lifecycle artifact marker is missing from {$relativePath}: {$marker}";
        }
    }
}

$databaseAuthorityLifecycleForbiddenTemplateMarkers = [
    'templates/application/.ai/data.md' => [
        '| Connection name | Engine and supported version | PDO driver | Required Composer extension | Non-secret configuration reference | Schema authority |',
        '{{CONNECTION_1_SCHEMA_SOURCE}}',
        '{{CONNECTION_2_SCHEMA_SOURCE_OR_NOT_APPLICABLE}}',
    ],
];

foreach ($databaseAuthorityLifecycleForbiddenTemplateMarkers as $relativePath => $markers) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        $failures[] = "Cannot read database authority lifecycle template {$relativePath}.";
        continue;
    }

    foreach ($markers as $marker) {
        if (str_contains($contents, $marker)) {
            $failures[] = "Forbidden ambiguous database authority template marker remains in {$relativePath}: {$marker}";
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
    'Alpha 2 is published',
    'Alpha 2 has been published',
    'Alpha 2 is available',
    '0.1.0-alpha.2 is published',
    '0.1.0-alpha.2 is available',
    'Alpha 2 is now available',
    'the Alpha 2 packages are available',
    'the public Alpha 2 installation path is available',
    'Alpha 3 is published',
    'Alpha 3 has been published',
    'Alpha 3 is available',
    '0.1.0-alpha.3 is published',
    '0.1.0-alpha.3 is available',
    'Alpha 3 is now available',
    'the Alpha 3 packages are available',
    'the public Alpha 3 installation path is available',
    'Alpha 4 is published',
    'Alpha 4 has been published',
    'Alpha 4 is available',
    '0.1.0-alpha.4 is published',
    '0.1.0-alpha.4 is available',
    'Alpha 4 is now available',
    'the Alpha 4 packages are available',
    'the public Alpha 4 installation path is available',
];

$requiredReleaseRoutingAuthorityFiles = [
    '.ai/README.md',
    '.ai/application-context.md',
    'docs/guardrails.md',
    'docs/knowledge-map.md',
];

$mutableReleaseStateAuthorityFiles = [
    ...$requiredReleaseRoutingAuthorityFiles,
    '.ai/testing.md',
    'AGENTS.md',
    'CONTRIBUTING.md',
    'README.md',
    'RELEASING.md',
    'ROADMAP.md',
    'SECURITY.md',
    'VISION.md',
    'docs/consumer-contract.md',
    'docs/getting-started.md',
    'docs/strict-profile.md',
    'docs/decisions/README.md',
    'docs/releases/0.1.0-alpha.1.md',
    'docs/releases/0.1.0-alpha.2.md',
    'docs/releases/0.1.0-alpha.3.md',
    'docs/releases/0.1.0-alpha.4.md',
    'docs/releases/0.1.0-alpha.5.md',
    'docs/decisions/018-bounded-alpha-1-release-scope.md',
    'docs/decisions/029-alpha-2-consumer-profile-rollup.md',
    'docs/decisions/031-bounded-alpha-3-release-scope.md',
    'docs/decisions/035-bounded-alpha-4-release-scope.md',
    'docs/decisions/040-bounded-alpha-5-release-scope.md',
    'skeleton/README.md',
];

$mutableReleaseStateDetectionControls = [
    'ALPHA 5 IS PUBLISHED.' => true,
    "Alpha 5 is\npublicly available." => true,
    'Alpha 5 has been released.' => true,
    '**Alpha 5** is now publicly available.' => true,
    'v0.1.0-alpha.5 is available.' => true,
    '[Alpha 5](https://example.invalid/release) has now been published.' => true,
    'Alpha 5 publication state is external.' => false,
    'Publication state is external.' => false,
];

foreach ($mutableReleaseStateDetectionControls as $contents => $expectedClaim) {
    $hasClaim = mutableReleaseStateClaim($contents, $mutableReleaseStateForbiddenMarkers) !== null;

    if ($hasClaim !== $expectedClaim) {
        $failures[] = 'The normalized mutable release-state detector changed behavior.';
    }
}

$externalReleaseStateMarkers = [
    'README.md' => 'Package availability and current release state are external facts',
    'RELEASING.md' => 'Publication state is external',
    'ROADMAP.md' => 'Alpha 5 publication state is external',
    'SECURITY.md' => 'This tracked policy does not record current publication state',
    'docs/getting-started.md' => 'Package availability is an external fact',
    'docs/releases/0.1.0-alpha.1.md' => 'Publication state is external',
    'docs/releases/0.1.0-alpha.2.md' => 'Publication state is external',
    'docs/releases/0.1.0-alpha.3.md' => 'Publication state is external',
    'docs/releases/0.1.0-alpha.4.md' => 'Publication state is external',
    'docs/releases/0.1.0-alpha.5.md' => 'Publication state is external',
    'docs/decisions/018-bounded-alpha-1-release-scope.md' => 'This decision does not record mutable publication state',
    'docs/decisions/029-alpha-2-consumer-profile-rollup.md' => 'not mutable tag, package, GitHub release, or installation availability',
    'docs/decisions/031-bounded-alpha-3-release-scope.md' => 'Publication state is external',
    'docs/decisions/035-bounded-alpha-4-release-scope.md' => 'Publication state is external',
    'docs/decisions/040-bounded-alpha-5-release-scope.md' => 'Publication state is external',
    'skeleton/README.md' => 'Package availability is an external fact',
];

foreach ($externalReleaseStateMarkers as $relativePath => $marker) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents) || !str_contains($contents, $marker)) {
        $failures[] = "The external release-state disclaimer is missing from {$relativePath}.";
    }
}

$consumerInstallationOrder = [
    'README.md' => [
        '## Start a PHPThis application',
        'Consumers install PHPThis through Composer.',
        'Do not clone or copy the PHPThis framework repository to start an application.',
        'composer create-project --stability=alpha phpthis/skeleton my-app',
        '`phpthis/skeleton` becomes the application root and Composer installs `phpthis/framework`',
        '## Develop or evaluate PHPThis itself',
        'It is not the consumer application installation path.',
        'git clone https://github.com/balgf/PHPThis.git',
    ],
    'skeleton/README.md' => [
        '## Create a new application',
        'Consumers do not clone or copy the PHPThis framework repository.',
        'composer create-project --stability=alpha phpthis/skeleton my-app',
        'installs `phpthis/framework` under `vendor/phpthis/framework`',
        '## Install and check an existing application checkout',
        '## Framework-maintainer source evaluation',
        'This section is not a consumer installation path.',
        'phpthis/framework: dev-main',
    ],
    'docs/getting-started.md' => [
        '## Start from the published skeleton',
        'composer create-project --stability=alpha phpthis/skeleton my-app',
        'Consumers do not clone or copy the PHPThis framework repository.',
        '## Framework source evaluation only',
        'It is not the normal consumer installation path.',
        'git clone https://github.com/balgf/PHPThis.git phpthis-source',
    ],
    'RELEASING.md' => [
        'Export the contents of `skeleton/` as the root of its dedicated repository',
        'Remove the framework-maintainer source-evaluation section from the exported skeleton README',
        'Remove the pre-alpha VCS `repositories` override from the exported `composer.json`',
    ],
    'composer.json' => [
        'start applications with phpthis/skeleton',
    ],
    'skeleton/composer.json' => [
        'starting a checked PHPThis application with phpthis/framework',
    ],
];

foreach ($consumerInstallationOrder as $relativePath => $orderedMarkers) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        $failures[] = "Cannot read consumer installation artifact {$relativePath}.";
        continue;
    }

    $previousPosition = -1;

    foreach ($orderedMarkers as $marker) {
        $position = strpos($contents, $marker);

        if ($position === false || $position <= $previousPosition) {
            $failures[] = "The Composer-first consumer installation contract is missing or out of order in {$relativePath}.";
            break;
        }

        $previousPosition = $position;
    }
}

foreach ($mutableReleaseStateAuthorityFiles as $relativePath) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        $failures[] = "Cannot read release-state authority {$relativePath}.";
        continue;
    }

    $releaseStateClaim = mutableReleaseStateClaim($contents, $mutableReleaseStateForbiddenMarkers);

    if ($releaseStateClaim !== null) {
        $failures[] = "Mutable release-state claim remains in {$relativePath}: {$releaseStateClaim}";
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
        '{name:uuid}',
        '{name:ulid}',
        'at most two',
        'Always use the narrowest type.',
        'RouteMatch',
        'PathParameters',
        'uuid(name): string',
        'ulid(name): string',
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
        'Superseded in part by [ADR 032]',
    ],
    'docs/decisions/032-explicit-uuid-and-ulid-route-types.md' => [
        'Status: accepted',
        '[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}',
        '[0-7][0-9abcdefghjkmnpqrstvwxyz]{25}',
        'Consumer Contract version 8',
        'Strict Profile version 2',
        '2,600 physical lines',
        '2,592 core lines',
        'No identifier library, generator, global factory, route builder',
    ],
    'docs/decisions/README.md' => [
        'Accepted records:',
        '`032-explicit-uuid-and-ulid-route-types.md`',
    ],
    'docs/consumer-contract.md' => [
        'Contract version: 10',
        'This is the canonical contract for an application built with the installed PHPThis version.',
        'Contract version 10 carries contract version 9 forward and adopts Strict Profile version 3.',
        '`positive-int`, `token`, `uuid`, or `ulid`',
        'Always use the narrowest route type.',
        'uuid(name): string',
        'ulid(name): string',
        'never normalized',
    ],
    'src/Routing/RouteParameterType.php' => [
        "case Uuid = 'uuid';",
        "case Ulid = 'ulid';",
        'self::Uuid => self::isUuid($segment)',
        'self::Ulid => self::isUlid($segment)',
        '[1-8][0-9a-f]{3}-[89ab]',
        '[0-7][0-9abcdefghjkmnpqrstvwxyz]{25}',
    ],
    'src/Routing/PathParameters.php' => [
        'public function uuid(string $name): string',
        'public function ulid(string $name): string',
        'Path parameters cannot contain more than two values.',
    ],
    'tests/run.php' => [
        'router matches canonical lowercase UUID path parameters',
        'router matches canonical lowercase ULID path parameters',
        'invalid UUID and ULID routes stop before handler and database work',
        'literal routes win over canonical UUID and ULID values',
        'Expected all fixed types to remain indexed across 20,000 routes.',
    ],
    'tools/benchmark-routing.php' => [
        "'fixed_parameter_types' => ['positive-int', 'token', 'uuid', 'ulid']",
        "'timed_dynamic_parameter_type' => 'ulid'",
        "'timed_uuid_parameter_type' => 'uuid'",
        "'uuid_hit_nanoseconds' => \$uuidHitNanoseconds",
        "->uuid('document_id')",
        "->ulid('document_id')",
    ],
    'tools/test-consumer-project.php' => [
        'proveInstalledUuidAndUlidRouting($project, $environment);',
        'PASS installed UUID and ULID routing',
        '/accounts/{account_id:uuid}',
        '/events/{event_id:ulid}',
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
        'docs/decisions/032-explicit-uuid-and-ulid-route-types.md',
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

$requestHandlerDecoratorArtifactMarkers = [
    'docs/decisions/033-application-owned-request-handler-decorators.md' => [
        'Status: accepted',
        'Consumer Contract version 9 accepts one optional application pattern named an **application-owned request-handler decorator**.',
        'receives exactly one downstream `RequestHandler` through its ordinary constructor',
        'Composition occurs only in the handler argument of an explicit `Route`.',
        'passes the exact same `Request` instance to downstream',
        'does not catch, wrap, translate, suppress, retry, or otherwise replace an exception',
        'constructs one explicit immutable replacement and preserves every unchanged status, header, body, `ResponseCookie`, and `LocalFileBody` field',
        'PHPThis adds no core class, runtime dependency, diagnostic, middleware interface, or composition facility',
        'Strict Profile version 2 remains unchanged.',
    ],
    'docs/decisions/README.md' => [
        '`033-application-owned-request-handler-decorators.md`',
    ],
    'docs/consumer-contract.md' => [
        'Contract version: 10',
        '## Optional application-owned request-handler decorators',
        'The decorator is composed only as the handler of an explicit `Route`.',
        'zero downstream calls or call its one downstream handler exactly once',
        'passes the exact same immutable `Request` instance downstream',
        'Do not add a generic or framework middleware interface, pipeline, stack, runner, registry, priority list, discovery rule, `$next` callable, request-context bag, request attributes, or framework-owned decorator.',
        'Version 9 adds no core class, framework middleware, runtime dependency, static diagnostic, request attribute, or automatic composition.',
    ],
    'docs/request-handling.md' => [
        '## Application-owned request-handler decorators',
        'receives exactly one downstream `RequestHandler`',
        'The complete outer-to-inner sequence stays visible beside the affected `Route`.',
        'Do not replace the direct nesting with a middleware array, helper, factory, registry, priority, discovery rule, `$next` callable, or container.',
        'It adds no core type or dependency.',
    ],
    'docs/vocabulary.md' => [
        '| application-owned request-handler decorator |',
        'middleware, interceptor, filter, pipeline element, `$next` callable',
    ],
    '.ai/routing.md' => [
        'When one route needs bounded wrapping behavior, its constructed handler may be one application-owned request-handler decorator.',
        'Construct it visibly beside the route',
        'Each decorator invokes its downstream zero or one time with the identical immutable `Request`',
        'Do not add a generic or framework middleware interface, pipeline, iterable registry, priorities, discovery, `$next` abstraction, context bag, hidden binding, or hidden I/O.',
    ],
    '.ai/request-policy.md' => [
        'Do not replace or obscure the action-specific adapter with an application-owned request-handler decorator',
    ],
    'templates/application/.ai/architecture.md' => [
        '{{REQUEST_HANDLER_DECORATOR_ADOPTION_OR_NOT_APPLICABLE}}',
        '{{REQUEST_HANDLER_DECORATOR_ROUTES_AND_ORDER_OR_NOT_APPLICABLE}}',
        '{{REQUEST_HANDLER_DECORATOR_SIDE_EFFECT_AND_FAILURE_POLICY_OR_NOT_APPLICABLE}}',
        'Construct the complete nesting as an unrolled expression beside every affected route.',
    ],
    'skeleton/.ai/architecture.md' => [
        '`NOT_APPLICABLE(REQUEST_HANDLER_DECORATOR)`',
        '`src/Routes.php` constructs `HealthHandler` directly.',
        'Never wrap `Application`, `RequestBoundary`, the terminal coordinator, or `ResponseEmitter`',
    ],
    'tests/handler-decorator.php' => [
        'final readonly class HandlerDecoratorProofOrderMarkerHandler implements RequestHandler',
        'private RequestHandler $downstream',
        'explicit nested handler decorators preserve request and response identity',
        'maintenance gate short-circuits or delegates exactly once',
        'handler decorator propagates the exact downstream exception',
        'handler decorator propagates its exact own exception before delegation',
        'response decorator preserves immutable buffered and local-file response fields',
        'handler decoration is route-local and removable by direct rewiring',
    ],
    'tests/run.php' => [
        "require __DIR__ . '/handler-decorator.php';",
        'handlerDecoratorTests()',
    ],
    'tools/test-consumer-project.php' => [
        'proveInstalledRequestHandlerDecorator($project, $environment);',
        'new InstalledHeaderDecorator(',
        'new InstalledRejectingDecorator(',
        'function assertInstalledDecoratorIsolation(InstalledDecoratorTrace $trace): void',
        'assertInstalledDecoratorIsolation($trace);',
        'PASS installed request-handler decorator composition',
        'The clean skeleton and request-handler decorator proof failed the installed profile check.',
        'is_file($requestHandlerDecoratorProofPath)',
    ],
    'tools/package-files.txt' => [
        'docs/decisions/033-application-owned-request-handler-decorators.md',
    ],
];

foreach ($requestHandlerDecoratorArtifactMarkers as $relativePath => $markers) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        $failures[] = "Cannot read request-handler decorator artifact {$relativePath}.";
        continue;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $failures[] = "Request-handler decorator artifact marker is missing from {$relativePath}: {$marker}";
        }
    }
}

$websocketArtifactMarkers = [
    'docs/decisions/034-application-owned-websocket-integration.md' => [
        'Status: accepted',
        'WebSocket integration remains application-owned.',
        'PHPThis adds no core WebSocket server, client, frame, connection, event-loop, daemon, supervisor, channel, broadcaster, pub/sub, retry, replay, acknowledgement, or delivery API and no runtime dependency.',
        'WebSocket handshakes and frames never become PHPThis HTTP `Request` or `Response` values.',
        'Consumer Contract version 9 and Strict Profile version 2 remain unchanged.',
        '365 application-owned assertions',
        'one reproducible application recipe',
        'the accountable human accepted the completed consumer evidence and its exact local proof limits',
    ],
    'docs/decisions/README.md' => [
        'Accepted records:',
        '`034-application-owned-websocket-integration.md`',
    ],
    'docs/websockets.md' => [
        '# Application-owned WebSocket integration',
        'PHPThis has no native WebSocket runtime or API.',
        'This guide is an accepted evidence profile',
        'A frame is not a PHPThis HTTP `Request`, and an outbound message is not a PHPThis HTTP `Response`.',
        'the exact raw handshake request target, accepted URI form, path-normalization and query behavior',
        'Default to best-effort delivery with no replay across reconnects.',
        '365 application-owned assertions',
        'They are not PHPThis defaults, production recommendations, capacity findings, or evidence for another package version',
    ],
    'docs/consumer-contract.md' => [
        'Contract version: 10',
        '## Application-owned WebSocket profile',
        'PHPThis has no WebSocket runtime or core WebSocket API.',
        'Frames never become PHPThis HTTP `Request` or `Response` values',
        'Do not add a framework WebSocket server, client, event loop, connection manager, daemon, supervisor, generic channel, broadcaster, pub/sub, event bus, middleware, context bag, service locator, discovery mechanism, hidden retry, replay, deduplication, acknowledgement, reconnect, or exactly-once behavior.',
        'ADR 034 documents one independent application-owned WebSocket proof without accepting a framework WebSocket runtime, changing application validity, or making its recipe limits universal.',
    ],
    'docs/knowledge-map.md' => [
        '| Propose, add, explain, or review a WebSocket path |',
        'verify that frames never become PHPThis HTTP `Request` or `Response` values and no framework WebSocket runtime exists',
    ],
    'docs/architecture.md' => [
        'ADR 034 keeps WebSockets outside that HTTP graph.',
        'There is no WebSocket namespace or runtime in core.',
        'one measured local recipe, not architecture defaults',
    ],
    'docs/security.md' => [
        '## WebSocket limits',
        'A successful protocol upgrade is not permanent authentication or authorization.',
        'Authenticate explicitly after upgrade even when the handshake also rejects invalid credentials',
        'do not add an unbounded gateway or application queue',
        'one local recipe, not security defaults',
    ],
    'docs/vocabulary.md' => [
        '| application-owned WebSocket integration |',
        '| WebSocket composition root |',
        '| WebSocket command |',
        '| best-effort WebSocket delivery |',
    ],
    'docs/evaluation.md' => [
        'ADR 034 adds an independent consumer proof for one application-owned WebSocket path without adding a framework implementation.',
        '365 application-owned assertions',
        'This establishes that the explicit boundary is viable for that pinned local recipe',
    ],
    'docs/guardrails.md' => [
        'accepted ADR 034, the WebSocket review profile, project-owned AI routes, and package inventory preserve the optional application-owned WebSocket boundary',
        'keeps `.ai/websockets.md` optional under current Contract version 10 as well as its originating Contract version 9',
    ],
    'README.md' => [
        'Accepted [application-owned WebSocket integration](docs/websockets.md)',
        'Frames are parsed into a narrow typed command and never adapted to PHPThis HTTP requests or responses',
        'ADR 034 remains evidence-backed application-owned guidance for a separate pinned third-party runtime, not a core WebSocket capability.',
    ],
    'VISION.md' => [
        'An application that needs WebSockets can keep its pinned mature runtime',
        'without adding a framework real-time runtime or adapting frames into HTTP values',
    ],
    'ROADMAP.md' => [
        'Complete: ADR 034',
        'Accountable-human review accepted the exact local recipe as evidence; no framework runtime, dependency, API, contract version, Strict Profile rule, or core-line increase is added.',
    ],
    '.ai/README.md' => [
        '| Propose, adopt, or change application-owned WebSockets |',
        '`.ai/websockets.md`',
    ],
    '.ai/application-context.md' => [
        'Include `.ai/websockets.md` in the current skeleton and template with `NOT_APPLICABLE(WEBSOCKETS)`',
        'this additional file is not a checker requirement',
    ],
    '.ai/websockets.md' => [
        '# Application-owned WebSocket integration contract',
        'WebSockets are an optional consuming-application capability, not a PHPThis runtime feature.',
        'A frame becomes one operation-specific final readonly command, not an HTTP request.',
        'Do not add framework-owned WebSocket, event-loop, connection-manager, daemon, or supervisor primitives.',
    ],
    '.ai/rules.md' => [
        'Keep optional WebSockets application-owned:',
        'adapting frames into PHPThis HTTP requests or responses',
    ],
    '.ai/testing.md' => [
        'An application that adopts WebSockets must test its parser, current authentication and authorization',
        'Real child-process and socket evidence must cover readiness without a blind sleep',
    ],
    'AGENTS.md' => [
        'Keep optional WebSockets application-owned and separate from PHPThis HTTP:',
        'Do not adapt frames into PHPThis `Request` or `Response`.',
    ],
    'templates/application/.ai/README.md' => [
        '| Adopt or change application-owned WebSockets |',
        'installed `vendor/phpthis/framework/docs/websockets.md`',
    ],
    'templates/application/.ai/websockets.md' => [
        '`NOT_APPLICABLE(WEBSOCKETS)`',
        'Keep every WebSocket type and the selected runtime application-owned and manually composed.',
        'Frames never become PHPThis HTTP `Request` or `Response` values',
        'real child-process and socket evidence',
    ],
    'templates/application/.ai/architecture.md' => [
        '## Optional application-owned WebSockets',
        'Keep frames outside PHPThis `Request`, `Response`, `Router`, `RequestBoundary`, `ResponseEmitter`, and terminal request-summary types.',
    ],
    'templates/application/.ai/integrations.md' => [
        '## Optional WebSocket runtime dependency',
        '`NOT_APPLICABLE(WEBSOCKETS)`',
    ],
    'templates/application/.ai/operations.md' => [
        '## WebSocket runtime',
        'forced-stop owner, deployment topology, capacity, scaling, incident policy',
    ],
    'templates/application/.ai/rules.md' => [
        'Do not adapt frames into PHPThis HTTP `Request` or `Response`.',
        'Do not add a generic WebSocket middleware, gateway, channel, room, broadcaster, pub/sub, event bus, service locator, context bag, discovery, application send queue, hidden retry, replay, acknowledgement, resume, or exactly-once claim.',
    ],
    'templates/application/.ai/testing.md' => [
        'WebSocket integration and lifecycle tests: `NOT_APPLICABLE(WEBSOCKETS)`',
        'Real child-process and socket tests prove readiness without a blind sleep',
    ],
    'templates/application/AGENTS.md' => [
        '`NOT_APPLICABLE(WEBSOCKETS)`',
        'Frames never become PHPThis HTTP `Request` or `Response` values.',
    ],
    'skeleton/.ai/README.md' => [
        '| Introduce or change application-owned WebSockets |',
        'installed `vendor/phpthis/framework/docs/websockets.md`',
    ],
    'skeleton/.ai/websockets.md' => [
        '`NOT_APPLICABLE(WEBSOCKETS)`',
        'The existing `GET /health` path remains an independent HTTP path.',
        'Do not add PHPThis WebSocket primitives, HTTP adaptation',
    ],
    'skeleton/.ai/architecture.md' => [
        '## Optional application-owned WebSockets',
        'The existing `GET /health` execution path is HTTP only.',
    ],
    'skeleton/.ai/integrations.md' => [
        '`NOT_APPLICABLE(WEBSOCKETS)`',
        'Keep retries, replay, acknowledgement, delivery, and backend-failure behavior explicit',
    ],
    'skeleton/.ai/operations.md' => [
        '## WebSocket runtime',
        'forced-stop owner, deployment topology, capacity, scaling, incident policy',
    ],
    'skeleton/.ai/rules.md' => [
        'Do not adapt frames into PHPThis HTTP `Request` or `Response`.',
        'Do not add a generic WebSocket middleware, gateway, channel, room, broadcaster, pub/sub, event bus, service locator, context bag, discovery, application send queue, hidden retry, replay, acknowledgement, resume, or exactly-once claim.',
    ],
    'skeleton/.ai/testing.md' => [
        'WebSocket integration and lifecycle tests: `NOT_APPLICABLE(WEBSOCKETS)`',
        'Real child-process and socket tests prove readiness without a blind sleep',
    ],
    'skeleton/AGENTS.md' => [
        '`NOT_APPLICABLE(WEBSOCKETS)`',
        'Frames never become PHPThis HTTP `Request` or `Response` values.',
    ],
    'tools/package-files.txt' => [
        'docs/decisions/034-application-owned-websocket-integration.md',
        'docs/websockets.md',
        'templates/application/.ai/websockets.md',
    ],
];

foreach ($websocketArtifactMarkers as $relativePath => $markers) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        $failures[] = "Cannot read WebSocket boundary artifact {$relativePath}.";
        continue;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $failures[] = "WebSocket boundary artifact marker is missing from {$relativePath}: {$marker}";
        }
    }
}

$forbiddenWebSocketRuntimePathPattern = '/(?:websockets?|realtime|event[-_]?loop|daemon|supervisor|broadcast(?:ing)?|pub[-_]?sub|channels?)/i';
$websocketFrameworkSourceFiles = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS),
);

foreach ($websocketFrameworkSourceFiles as $websocketFrameworkSourceFile) {
    if (!$websocketFrameworkSourceFile instanceof SplFileInfo || !$websocketFrameworkSourceFile->isFile()) {
        continue;
    }

    $relativePath = substr($websocketFrameworkSourceFile->getPathname(), strlen($root) + 1);

    if (preg_match($forbiddenWebSocketRuntimePathPattern, $relativePath) === 1) {
        $failures[] = "WebSocket runtime mechanism must remain outside framework source: {$relativePath}.";
    }
}

$websocketPackageInventory = file_get_contents($root . '/tools/package-files.txt');

if (is_string($websocketPackageInventory)) {
    $websocketPackagePaths = preg_split('/\R/', $websocketPackageInventory);

    if (is_array($websocketPackagePaths)) {
        foreach ($websocketPackagePaths as $websocketPackagePath) {
            if (
                str_starts_with($websocketPackagePath, 'src/')
                && preg_match($forbiddenWebSocketRuntimePathPattern, $websocketPackagePath) === 1
            ) {
                $failures[] = "WebSocket runtime mechanism must remain outside the framework package API: {$websocketPackagePath}.";
            }
        }
    }
}

$websocketProofOnlyDependencies = [
    'amphp/amp',
    'amphp/byte-stream',
    'amphp/http',
    'amphp/http-server',
    'amphp/socket',
    'amphp/websocket',
    'amphp/websocket-client',
    'amphp/websocket-server',
    'ext-pcntl',
    'revolt/event-loop',
];

foreach (['composer.json', 'skeleton/composer.json'] as $websocketComposerPath) {
    $contents = file_get_contents($root . '/' . $websocketComposerPath);
    $manifest = is_string($contents) ? json_decode($contents, true) : null;

    if (!is_array($manifest)) {
        $failures[] = "Cannot decode {$websocketComposerPath} for the WebSocket dependency boundary.";
        continue;
    }

    foreach (['require', 'require-dev'] as $dependencySection) {
        $dependencies = $manifest[$dependencySection] ?? [];

        if (!is_array($dependencies)) {
            continue;
        }

        foreach (array_keys($dependencies) as $dependency) {
            if (
                is_string($dependency)
                && in_array(strtolower($dependency), $websocketProofOnlyDependencies, true)
            ) {
                $failures[] = "Application-owned WebSocket proof dependency {$dependency} must not enter {$websocketComposerPath}:{$dependencySection}.";
            }
        }
    }
}

$websocketApplicationChecker = file_get_contents($root . '/verification/ApplicationChecker.php');

if (
    is_string($websocketApplicationChecker)
    && preg_match('/[\'\"]\\.ai\\/websockets\\.md[\'\"]\s*,/', $websocketApplicationChecker) === 1
) {
    $failures[] = 'Contract version 9 must not checker-require the optional application WebSocket context file.';
}

$websocketConsumerProjectProof = file_get_contents($root . '/tools/test-consumer-project.php');

if (
    is_string($websocketConsumerProjectProof)
    && str_contains($websocketConsumerProjectProof, 'proveWebSocketContextIsRequired')
) {
    $failures[] = 'Contract version 9 must not reject an existing consumer only because .ai/websockets.md is absent.';
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
        'new DenyAllAccountAuthentication()',
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
    '.ai/README.md' => [
        'ADR 042 for structured request-body content',
        'default generic `400`/application-owned generic `422` body classification',
    ],
    '.ai/application-context.md' => [
        'every adopted inbound operation',
        '`NOT_APPLICABLE(INPUT)`',
        'default generic `400` structural versus exact application-owned generic `422` unacceptable-value split',
        'Query, header, route, and transport representations do not inherit that body-content default.',
    ],
    '.ai/types.md' => [
        'No normalization is implicit.',
        'Native `json_decode` does not expose duplicate object keys and retains the last value',
        'ADR 033 and Consumer Contract v9',
        'For application-owned structured request-body content',
        'do not inherit this request-body default',
    ],
    '.ai/errors.md' => [
        'For application-owned structured request-body content',
        'defaults through its exact application-owned failure to `422`',
        'Query, header, route, and transport representations retain their separately recorded contracts.',
    ],
    '.ai/testing.md' => [
        'For application-owned structured request-body content',
        'property-order variants that both remain `400`',
        'Query, header, route, and transport representations retain their separately recorded contracts.',
    ],
    'docs/type-safety.md' => [
        'external mixed data -> named parser factory -> final readonly value -> native typed code',
        'Invalid input makes zero seam calls when one exists and cannot trigger operation-owned downstream I/O or mutation.',
        'A duplicate-key-aware parser requires a separate decision',
        'canonical authoring default for application-owned structured request-body content',
        'do not inherit this body-content default',
    ],
    'docs/errors.md' => [
        "blanket-`400` default for application-owned structured request-body content",
        'application-owned `UnacceptableCreateUserValues`',
        'Query-string, header, route, and transport representations retain their separately recorded contracts.',
    ],
    'docs/consumer-contract.md' => [
        'For application-owned structured request-body content',
        'Query-string, header, route, and transport representations retain their separately recorded contracts.',
        'ADR 042 changes the application-owned structured request-body authoring default',
    ],
    'docs/getting-started.md' => [
        "each inbound operation's raw representation",
        '`NOT_APPLICABLE(INPUT)`',
        'default generic `400` structural versus exact application-owned generic `422` unacceptable-value split',
        'query, header, route, and transport representations retain separately recorded contracts',
    ],
    'docs/guardrails.md' => [
        'The typed-input guard retains ADR 021',
        "ADR 042's application-owned request-body input-failure classification",
        'mixed-failure property-order evidence',
        'query/header/route/transport non-inheritance',
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
    'docs/decisions/042-application-owned-input-failure-classification.md' => [
        'Status: accepted',
        'For application-owned structured request-body content',
        'The operation-specific parser completes its whole shape and native-type pass before beginning value validation.',
        '`400 invalid_request` means the representation is malformed or its complete payload structure is invalid.',
        '`422 unprocessable_content` means the complete field set, nullability, native types, and nested shapes are correct',
        'Query-string, header, route, PHP runtime transport, and multipart transport failures retain their separately recorded contracts',
        'PHPThis adds no core exception, validator, result object, field-error schema, string-rule language, renderer, hydrator, automatic request binding, or status inference.',
        'Consumer Contract version 10 and Strict Profile version 3 remain unchanged because this decision adds authoring guidance',
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
        '!is_string($name) || !is_string($email)',
        'throw new UnacceptableCreateUserValues(',
    ],
    'example/src/Users/CreateUser/UnacceptableCreateUserValues.php' => [
        'final class UnacceptableCreateUserValues extends RuntimeException',
    ],
    'example/src/Users/CreateUser/CreateUserHandler.php' => [
        '$command = CreateUserCommand::fromJson($request->body);',
        '$this->createUser->execute($principal, $tenant, $accountId, $command);',
    ],
    'example/src/Users/CreateUser/CreateUserOperation.php' => [
        'interface CreateUserOperation',
        'AuthenticatedPrincipal $principal,',
        'ResolvedTenant $tenant,',
        'AccountId $accountId,',
        'CreateUserCommand $command,',
    ],
    'example/src/Users/CreateUser/TransactionalCreateUser.php' => [
        'final readonly class TransactionalCreateUser implements CreateUserOperation',
        'four-statement transaction',
        'INSERT INTO account_users (user_id, account_id)',
        'INSERT INTO application_jobs (',
    ],
    'tests/run.php' => [
        'HTTP command parses one exact JSON object',
        'HTTP command exposes native duplicate-key last-value behavior',
        'HTTP command classifies structural and unacceptable input',
        'HTTP handler invokes only its typed create-user operation',
        'HTTP handler rejects invalid commands before use-case invocation',
        'mapped input failures emit no submitted data or log entry',
        'function unacceptableCreateUserValueBodies(): array',
        "'integer_name_with_unacceptable_email'",
        "'unacceptable_name_with_unknown_field'",
        'UnacceptableCreateUserValues::class => new Response(',
        'example request boundary maps client failures before database work',
        'account-scoped user creation rejects invalid input before database work',
    ],
    'templates/application/.ai/architecture.md' => [
        '{{INPUT_BOUNDARY_ADOPTION_OR_NOT_APPLICABLE}}',
        '{{INPUT_OPERATION_1_FACTORY_AND_TYPE}}',
        'No normalization is implicit.',
        'complete field set, nullability, native types, and nested shape before applying value rules',
        'maps through an exact application-owned failure to generic `422`',
        'Query, header, route, and transport representations retain their separately recorded contracts.',
    ],
    'templates/application/.ai/testing.md' => [
        '{{INPUT_BOUNDARY_TEST_COMMAND_OR_NOT_APPLICABLE}}',
        'no operation-owned downstream database work',
        'When a separate typed operation seam exists, assert zero calls.',
        'duplicate-key-aware contract requires a separately accepted parser decision',
        'mixed unacceptable-value plus wrong-native-type case in property-order variants that both remain `400`',
        'Query, header, route, and transport representations retain their separately recorded contracts',
    ],
    'templates/application/AGENTS.md' => [
        'finish the complete field-set, nullability, native-type, and nested-shape pass before value rules',
        'correctly shaped and typed body content with unacceptable values returns generic `422` through an exact application-owned failure',
        'Query, header, route, and transport representations retain their separately recorded contracts.',
    ],
    'templates/application/.ai/rules.md' => [
        'For structured request-body content, complete the whole field-set, nullability, native-type, and nested-shape phase before value rules.',
        'mixed failures to `400` regardless of property order',
        'Query, header, route, and transport representations retain separately recorded contracts.',
    ],
    'templates/application/.ai/change-workflow.md' => [
        'For structured request-body content, record the complete structural phase before value rules',
        'do not apply that body-content default implicitly to query, header, route, or transport representations.',
        'Structured request-body tests must prove mixed structural and value failures remain `400` in property-order variants',
    ],
    'skeleton/.ai/README.md' => [
        'NOT_APPLICABLE(INPUT)',
        'do not add a generic input guide or validation mechanism',
    ],
    'skeleton/.ai/architecture.md' => [
        'NOT_APPLICABLE(INPUT)',
        'operation-specific named parser factory',
        'completes the whole exact-field, nullability, native-type, and nested-shape phase before applying any value',
        'exact application-owned exception registered as generic `422 unprocessable_content`',
        'Query, header, route, and transport representations retain their separately recorded contracts.',
    ],
    'skeleton/.ai/testing.md' => [
        'NOT_APPLICABLE(INPUT_EVIDENCE)',
        'no operation-owned downstream I/O or mutation',
        'zero typed-seam calls when one exists',
        'property-order variants that remain `400`',
        'Query, header, route, and transport representations retain their separately recorded contracts.',
    ],
    'skeleton/.ai/rules.md' => [
        'complete the entire field-set, nullability, native-type, and nested-shape pass before value rules',
        'correctly shaped and typed body content with unacceptable values returns generic `422` through an exact application-owned failure',
        'Query, header, route, and transport representations retain separately recorded contracts.',
    ],
    'skeleton/.ai/change-workflow.md' => [
        'complete structural phase before value rules',
        'mixed structural and value failures remain `400` in property-order variants',
        'Do not apply that body-content default implicitly to query, header, route, or transport representations.',
    ],
    'example/.ai/README.md' => [
        'ADR 042 for Create request-body classification',
        'only a correctly shaped and typed body with an unacceptable name or email throws application-owned `UnacceptableCreateUserValues`',
        'Query, header, route, and transport representations do not inherit this body-content default.',
    ],
    'tools/package-files.txt' => [
        'docs/decisions/021-application-owned-typed-input-boundaries.md',
        'docs/decisions/042-application-owned-input-failure-classification.md',
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
        'Contract version 10 carries contract version 9 forward and adopts Strict Profile version 3.',
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
        'ADR 023 is the mandatory request-summary decision',
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
        '$coordinator->handle($_SERVER, $_GET, $_POST, $_FILES)',
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
        "'schema_version' => self::SCHEMA_VERSION,",
        "'document_cache' => \$this->documentCache,",
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
        '$coordinator->handle($_SERVER, $_GET, $_POST, $_FILES)',
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
        "frameworkBehaviorGroupDefinitions('observability', observabilityTests())",
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
        'terminal summary exposes one bounded document-cache outcome without cache data',
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
        'Contract version 9 does not make that additional file a checker requirement',
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
        'private function runOneJob(?string $databasePath = null): ApplicationCommandOutcome',
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
        "frameworkBehaviorGroupDefinitions('jobs', jobTests())",
        'account-scoped user creation publishes one job with four writes across dataset sizes',
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
        'new ApplicationComposition($applicationDatabasePath)',
        '->commands(new SystemUserWelcomeJobClock())',
        '->run(ApplicationCommandName::DatabaseMigrate);',
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

$exampleSetup = file_get_contents($root . '/tools/setup-example.php');

if (
    is_string($exampleSetup)
    && preg_match(
        '/\b(?:CREATE\s+(?:TABLE|INDEX|TRIGGER|VIEW)|ALTER\s+TABLE|DROP\s+(?:TABLE|INDEX|TRIGGER|VIEW)|REINDEX|VACUUM)\b/i',
        $exampleSetup,
    ) === 1
) {
    $failures[] = 'tools/setup-example.php must delegate schema DDL instead of duplicating it.';
}

foreach (['src/Jobs', 'src/Queue'] as $forbiddenCoreDirectory) {
    if (is_dir($root . '/' . $forbiddenCoreDirectory)) {
        $failures[] = "Durable-job runtime must remain application-owned outside {$forbiddenCoreDirectory}.";
    }
}

$applicationChecker = file_get_contents($root . '/verification/ApplicationChecker.php');

if (is_string($applicationChecker) && str_contains($applicationChecker, "'.ai/jobs.md',")) {
    $failures[] = 'Contract version 9 must not checker-require the optional durable-job context file.';
}

$consumerProjectProof = file_get_contents($root . '/tools/test-consumer-project.php');

if (is_string($consumerProjectProof) && str_contains($consumerProjectProof, 'proveJobsContextIsRequired')) {
    $failures[] = 'Contract version 9 must not reject an existing consumer only because .ai/jobs.md is absent.';
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
        'PHPThis provides no core CLI command, command map, argument parser, scheduler, lock, lease, daemon, or process manager.',
        'Reject an unknown command separately from invalid, duplicate, misplaced, oversized, or unsupported arguments before application I/O.',
        'HTTP and CLI may share immutable configuration and explicit application construction code',
        'application-private Redis lease',
    ],
    '.ai/testing.md' => [
        'execute its real console in fresh subprocesses',
        'explicit-clock cadence boundaries',
        'Do not mock a generic console or scheduler',
    ],
    'docs/cli.md' => [
        '# Application CLI and scheduler',
        'PHPThis accepts one application-owned operational console pattern and provides no core command or scheduler API.',
        'php example/bin/console.php <jobs:run-one|schedule:run|database:migrate> [--database=/absolute/path]',
        '`database:migrate` is the sole migration spelling in the accepted example.',
        'intdiv(epoch_seconds, 60) % 5 === 0',
        'SET key token NX PX 30000',
        '`Example\\ApplicationComposition`',
        '## Unsupported boundary',
    ],
    'docs/cli/README.md' => [
        '# Application CLI knowledge index',
        'Arguments and output',
        'Scheduling and coordination',
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
        '# CLI scheduling and coordination',
        'intdiv(epoch_seconds, 60) % 5 === 0',
        'Sequential invocations in the same due minute are not deduplicated',
    ],
    'docs/cli/testing.md' => [
        '# CLI testing',
        'For production adoption, execute the real application console in fresh subprocesses.',
        'The current example proof is intentionally bounded',
        'stale-owner renewal and release rejection',
    ],
    'docs/consumer-contract.md' => [
        '## Optional application-owned CLI and scheduler',
        'Contract-version-7-compatible optional application clarification, not a new checker requirement',
        'Contract version 10 carries contract version 9 forward and adopts Strict Profile version 3.',
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
        'no framework CLI, scheduler, lock, or lease API exists',
    ],
    'README.md' => [
        'php example/bin/console.php jobs:run-one',
        'php example/bin/console.php schedule:run',
        'Application CLI and scheduler',
    ],
    'ROADMAP.md' => [
        'ADR 025 accepts one application-owned explicit console and cron-friendly scheduled pass',
        'ADR 028 accepts one Redis-specific application cache and schedule lease',
    ],
    'example/.ai/README.md' => [
        'Change an application command, argument, exit, stream, cadence, or overlap policy',
        '`bin/console.php`, `ApplicationComposition`, `src/Cli/`',
    ],
    'example/.ai/cli.md' => [
        '# Example application CLI and scheduler context',
        'php example/bin/console.php jobs:run-one [--database=/absolute/path]',
        'php example/bin/console.php schedule:run [--database=/absolute/path]',
        'php example/bin/console.php database:migrate [--database=/absolute/path]',
        'intdiv(epochSeconds, 60) % 5 === 0',
        'SET key token NX PX 30000',
        'No live Redis client, connection, budget, trace, request, session, correlation ID, or mutable clock is shared between HTTP and CLI',
    ],
    'example/src/Cli/README.md' => [
        '# Example application CLI source',
        'application-owned evidence for ADR 025 and ADR 028, not PHPThis core runtime code',
        '`example/bin/console.php` is the only operational entrypoint',
        'Do not add command discovery, dynamic class or service resolution, a second console, generic parser or scheduler facade, daemon, polling or renewal loop',
    ],
    'example/AGENTS.md' => [
        'Keep `bin/console.php` as the sole application operational console.',
        'Do not add another entrypoint, command discovery, a service container, scheduler facade, daemon, persistent slot ledger, catch-up, or generic distributed-coordination API.',
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
        'return new ApplicationCommands(',
        '$this->databasePath,',
    ],
    'example/src/ApplicationDatabasePath.php' => [
        'strlen($value) > 4_096',
        "str_ends_with(\$value, '\\\\')",
        "preg_match('/[\\x00-\\x1F\\x7F]/', \$value)",
    ],
    'example/src/Cli/ApplicationCommandName.php' => [
        "case DatabaseMigrate = 'database:migrate';",
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
        "case Applied = 'applied';",
        "case UpToDate = 'up_to_date';",
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
        'ApplicationCommandName::DatabaseMigrate => new ApplicationCommandExecution(',
        'intdiv($this->clock->now(), 60)',
        '$currentMinute % 5 !== 0',
        'RedisScheduleRunLease::connect(',
        '$scheduleLease->acquire() === RedisScheduleRunLeaseAcquireOutcome::Contended',
        '$scheduleLease->renew() === RedisScheduleRunLeaseRenewOutcome::Lost',
        '$scheduleLease->release() === RedisScheduleRunLeaseReleaseOutcome::Lost',
        'private function runOneJob(?string $databasePath = null): ApplicationCommandOutcome',
        'private function runMigrations(): ApplicationCommandOutcome',
    ],
    'example/src/Coordination/RedisScheduleRunLease.php' => [
        "'NX'",
        "'PX' => self::LEASE_TTL_MILLISECONDS",
        'self::RENEW_SCRIPT',
        'self::RELEASE_SCRIPT',
        'MAXIMUM_RENEWALS = 4',
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
        'schedule run skips a subprocess-held Redis lease without blocking or delivering',
        'schedule run recovers after a lease-holder process dies and Redis expires ownership',
        'application composition keeps CLI execution outside fresh HTTP request state',
    ],
    'tests/redis-schedule-lease-holder.php' => [
        "fwrite(STDOUT, \"READY\\n\")",
        'RedisScheduleRunLease::connect(',
        'RedisScheduleRunLeaseAcquireOutcome::Acquired',
        'RedisScheduleRunLeaseRenewOutcome::Renewed',
        'RedisScheduleRunLeaseReleaseOutcome::Released',
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

    foreach (['jobs:run-one', 'schedule:run', 'database:migrate'] as $applicationCommand) {
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
    $failures[] = 'Contract version 9 must not checker-require the optional application CLI context file.';
}

if (is_string($consumerProjectProof) && str_contains($consumerProjectProof, 'proveCliContextIsRequired')) {
    $failures[] = 'Contract version 9 must not reject an existing consumer only because .ai/cli.md is absent.';
}

$applicationCliSourceFiles = [
    'example/bin/console.php',
    'example/src/Cli/ApplicationCommandExecution.php',
    'example/src/Cli/ApplicationCommandLine.php',
    'example/src/Cli/ApplicationCommandName.php',
    'example/src/Cli/ApplicationCommandOutcome.php',
    'example/src/Cli/ApplicationCommands.php',
    'example/src/Cli/InvalidApplicationCommandArguments.php',
    'example/src/Cli/UnknownApplicationCommand.php',
    'example/src/Coordination/RedisScheduleRunLease.php',
    'example/src/Coordination/RedisScheduleRunLeaseTrace.php',
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

$workbenchArtifactMarkers = [
    'docs/decisions/041-optional-development-workbench.md' => [
        'Status: accepted',
        'optional separate `phpthis/workbench` development package',
        'For each entered expression it starts a fresh `PHP_BINARY` child',
        'parent-process `ini_set()` changes and parent-launch `-d` options do not carry into it',
        'The generated child program is not a security boundary.',
        'an expression can target the parent or other processes and can leave external state changed',
        'Workbench provides no execution timeout, CPU limit, memory limit, resource limit, or operating-system termination isolation',
        'Direct deferred-work handler execution does not prove publication or queued delivery.',
        'existing adopted business operation',
        'This decision adds no framework-core PHP, runtime dependency, command, checker rule, `PHT` diagnostic',
    ],
    'docs/decisions/README.md' => [
        '`041-optional-development-workbench.md`',
    ],
    'docs/workbench.md' => [
        '# PHPThis Workbench',
        'separate `phpthis/workbench` Composer package',
        'returns exactly one concrete application-owned object',
        'Every expression is sent over standard input to a fresh `PHP_BINARY` child',
        'Composer\\\\Config::disableProcessTimeout',
        '`-d` options used to launch that parent are not inherited by the child',
        'Arbitrary PHP can still signal or terminate other processes and can leave filesystem, database, network, queue, or other external state changed.',
        'Workbench supplies no execution timeout, CPU limit, memory limit, resource limit, or operating-system termination isolation.',
        'A non-returning or blocked expression prevents the next prompt until the child is externally interrupted or terminated.',
        'existing adopted business operation',
        'recorded finite tested one-delivery operational command',
        'Workbench supplies no `dispatch()`',
        'An entered expression is unchecked arbitrary PHP.',
        'Workbench output is exploratory evidence, not application validity evidence.',
    ],
    'docs/consumer-contract.md' => [
        '## Optional development Workbench',
        'Existing applications need not add `.ai/workbench.md` when they do not adopt the package',
        'This changes neither Consumer Contract version 10 nor Strict Profile version 3',
    ],
    'docs/knowledge-map.md' => [
        '| Adopt, use, or review PHPThis Workbench |',
        'verify that no container, discovery, generic dispatch, second publisher, core runtime, batch, HTTP, remote, or production shell was introduced',
    ],
    'docs/cli.md' => [
        'The optional separate `phpthis/workbench` development package is an unchecked expression workspace',
        'ADR 041\'s separately installed Workbench does not change that boundary',
    ],
    'docs/jobs.md' => [
        '## Development exploration is not delivery',
        'A direct deferred-work handler call bypasses publication, stored-envelope parsing, claim order, lease and fencing',
        'existing adopted business operation',
        'recorded finite tested one-delivery console command',
    ],
    'docs/configuration.md' => [
        '## Workbench process authority',
        'parent runtime `ini_set()` changes and parent-launch `-d` options do not carry into the child',
        'An environment label, debug flag, local hostname, or `.env` filename is not an authority check.',
    ],
    'docs/security.md' => [
        '## Workbench limits',
        'signal or terminate the parent or another process',
        'Workbench also provides no execution timeout, CPU limit, memory limit, resource limit, or operating-system termination isolation.',
        'Parent-process `ini_set()` changes and parent-launch `-d` restrictions do not carry into the child',
        'Workbench is not a sandbox, dry run, redactor, authorization layer, output bound, environment verifier, or production-safety control.',
    ],
    'docs/vocabulary.md' => [
        '| development Workbench |',
        '| Workbench workspace |',
    ],
    'docs/guardrails.md' => [
        'The Workbench guard retains only the accepted integration contract for the separately owned `phpthis/workbench` package.',
        'It keeps `.ai/workbench.md` optional under Consumer Contract version 10.',
    ],
    'README.md' => [
        'Optional [PHPThis Workbench](docs/workbench.md)',
        '[ADR 041](docs/decisions/041-optional-development-workbench.md)',
    ],
    'VISION.md' => [
        'A human can inspect one explicitly composed development object or operation through a fresh strict process',
        'Providing a framework-owned production shell, container-backed console, administrative execution path, generic dispatcher, or remotely accessible Workbench.',
    ],
    'ROADMAP.md' => [
        'Complete: ADR 041 accepts PHPThis Workbench as a separate optional development-only package',
        'ADR 041 accepts only a separate development Workbench package',
    ],
    '.ai/README.md' => [
        '| Propose, adopt, or change the optional development Workbench |',
        '`.ai/workbench.md`',
    ],
    '.ai/application-context.md' => [
        'Include `.ai/workbench.md` in the current skeleton and template with `NOT_APPLICABLE(WORKBENCH)`',
        'this optional file is not a checker requirement',
        'existing adopted business operation and transaction',
        'recorded finite tested console commands',
    ],
    '.ai/workbench.md' => [
        '# Optional development Workbench contract',
        'When the workspace exposes a real side effect, also read `docs/security.md` and `.ai/database.md`',
        '`skeleton/.ai/data.md`, `skeleton/.ai/integrations.md`, `skeleton/.ai/operations.md`',
        'one checked project-relative application bootstrap returns exactly one concrete final named object exposed as `$workspace`',
        'no execution timeout or CPU, memory, resource, or operating-system termination isolation',
        'operating-system identity, inherited environment, independently loaded child CLI configuration, ambient filesystem, network, process, and service access',
        'existing adopted business producer transaction',
        'no sandbox, redaction, dry-run, output-bound, production-safety, authorization, or validity claim',
    ],
    '.ai/rules.md' => [
        'Keep optional Workbench use development-only and explicit:',
        'Core or production Workbench types;',
    ],
    '.ai/testing.md' => [
        'An application that adopts ADR 041 Workbench keeps its bootstrap and concrete workspace type inside the ordinary application manifest and complete check.',
        'Entered expressions and displayed values remain unchecked exploratory evidence.',
    ],
    'templates/application/.ai/README.md' => [
        '| Adopt or change PHPThis Workbench |',
        'installed `vendor/phpthis/framework/docs/workbench.md`',
        'complete arbitrary-PHP development authority',
        'existing business producer transaction',
    ],
    'templates/application/.ai/workbench.md' => [
        '{{WORKBENCH_ADOPTION_OR_NOT_APPLICABLE}}',
        '{{WORKBENCH_EXCLUDED_AUTHORITY_OR_NOT_APPLICABLE}}',
        '{{WORKBENCH_RESOURCE_LIMITS_OR_NOT_APPLICABLE}}',
        '{{WORKBENCH_SIDE_EFFECT_POLICY_OR_NOT_APPLICABLE}}',
        '{{WORKBENCH_JOB_PATH_OR_NOT_APPLICABLE}}',
        'Workbench is arbitrary development code, not a sandbox',
    ],
    'templates/application/AGENTS.md' => [
        'record adoption or `NOT_APPLICABLE(WORKBENCH)` in `.ai/workbench.md`',
        'Install only through `require-dev`',
        'existing adopted business producer transaction',
    ],
    'skeleton/.ai/README.md' => [
        '| Adopt or change PHPThis Workbench |',
        '`NOT_APPLICABLE(WORKBENCH)`',
        'complete arbitrary-PHP development authority',
        'existing business producer transaction',
    ],
    'skeleton/.ai/workbench.md' => [
        '`NOT_APPLICABLE(WORKBENCH)`',
        'dedicated development operating-system identity, inherited environment, independently loaded child CLI configuration',
        'absence of a Workbench execution timeout or CPU, memory, resource, and operating-system termination isolation',
        'existing adopted business producer transaction and the application-recorded finite one-delivery console command',
        'Install Workbench only through `require-dev`',
        'Production artifacts install with `--no-dev`',
    ],
    'skeleton/AGENTS.md' => [
        '`NOT_APPLICABLE(WORKBENCH)`',
        'do not add a container, registry, generic dispatcher',
    ],
    'tools/package-files.txt' => [
        'docs/decisions/041-optional-development-workbench.md',
        'docs/workbench.md',
        'templates/application/.ai/workbench.md',
    ],
    'tools/test-consumer-project.php' => [
        '$installedWorkbenchGuidanceProof = proveInstalledWorkbenchGuidanceDistribution(',
        "if (\$installedWorkbenchGuidanceProof !== 'installed-workbench-guidance-proved')",
        "return 'installed-workbench-guidance-proved';",
        'The installed checker rejected a consumer only because .ai/workbench.md was absent.',
        'PASS installed Workbench guidance distribution',
        'without explicit application approval and verified Composer-source availability.',
    ],
    'tools/guardrails.php' => [
        'function workbenchRuntimePathIsForbidden(string $relativePath): bool',
        "'src/Development/Workbench.php' => true,",
        "'src/Development/Workbenches/Runner.php' => true,",
        "'src/Console/InteractiveShell.php' => true,",
        "'src/Console/InteractiveShells.php' => true,",
        "'src/Development/ReplConsole.php' => true,",
        "'src/Console/Repls/Runner.php' => true,",
        "'src/Console/REPLs/Runner.php' => true,",
        "'src/Console/DevelopmentREPLs.php' => true,",
        "'src/Language/Replacement.php' => false,",
        "'src/Language/Replay.php' => false,",
    ],
];

foreach ($workbenchArtifactMarkers as $relativePath => $markers) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        $failures[] = "Cannot read Workbench boundary artifact {$relativePath}.";
        continue;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $failures[] = "Workbench boundary artifact marker is missing from {$relativePath}: {$marker}";
        }
    }
}

$workbenchRuntimePathFixtures = [
    'src/Development/Workbench.php' => true,
    'src/Development/Workbenches/Runner.php' => true,
    'src/Console/InteractiveShell.php' => true,
    'src/Console/InteractiveShells.php' => true,
    'src/Development/ReplConsole.php' => true,
    'src/Console/Repls/Runner.php' => true,
    'src/Console/REPLs/Runner.php' => true,
    'src/Console/DevelopmentREPLs.php' => true,
    'src/Tools/REPL/Runner.php' => true,
    'src/Language/Replacement.php' => false,
    'src/Language/Replay.php' => false,
    'src/Http/Reply.php' => false,
    'docs/workbench.md' => false,
];

foreach ($workbenchRuntimePathFixtures as $fixturePath => $expectedForbidden) {
    if (workbenchRuntimePathIsForbidden($fixturePath) !== $expectedForbidden) {
        $failures[] = "Workbench runtime path guard fixture has drifted: {$fixturePath}.";
    }
}

$workbenchFrameworkSourceFiles = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS),
);

foreach ($workbenchFrameworkSourceFiles as $workbenchFrameworkSourceFile) {
    if (!$workbenchFrameworkSourceFile instanceof SplFileInfo || !$workbenchFrameworkSourceFile->isFile()) {
        continue;
    }

    $relativePath = substr($workbenchFrameworkSourceFile->getPathname(), strlen($root) + 1);

    if (workbenchRuntimePathIsForbidden($relativePath)) {
        $failures[] = "Workbench runtime must remain outside framework core: {$relativePath}.";
    }
}

$workbenchPackageInventory = file_get_contents($root . '/tools/package-files.txt');

if (is_string($workbenchPackageInventory)) {
    $workbenchPackagePaths = preg_split('/\R/', $workbenchPackageInventory);

    if (is_array($workbenchPackagePaths)) {
        foreach ($workbenchPackagePaths as $workbenchPackagePath) {
            if (workbenchRuntimePathIsForbidden($workbenchPackagePath)) {
                $failures[] = "Workbench runtime must remain outside the framework package API: {$workbenchPackagePath}.";
            }
        }
    }
}

foreach (['composer.json', 'skeleton/composer.json'] as $workbenchDependencyManifest) {
    $contents = file_get_contents($root . '/' . $workbenchDependencyManifest);

    if (is_string($contents) && str_contains($contents, '"phpthis/workbench"')) {
        $failures[] = "Workbench must not enter {$workbenchDependencyManifest} without explicit application approval and verified Composer-source availability.";
    }
}

if (
    is_string($frameworkEntrypoint)
    && (str_contains($frameworkEntrypoint, 'workbench') || str_contains($frameworkEntrypoint, 'phpthis-workbench'))
) {
    $failures[] = 'The check-only framework entrypoint must not host Workbench.';
}

if (is_string($consumerProjectProof) && str_contains($consumerProjectProof, 'proveWorkbenchContextIsRequired')) {
    $failures[] = 'Consumer Contract version 10 must not reject an existing consumer only because .ai/workbench.md is absent.';
}

$redisCoordinationArtifactMarkers = [
    '.ai/cache.md' => [
        'framework currently provides no generic cache API',
        'Do not cache credentials, session state, CSRF tokens, or authorization decisions.',
        'ADR 028',
    ],
    '.ai/cli.md' => [
        'one fresh owner token',
        '`SET NX PX` acquisition',
        'Do not add retry, waiting, a renewal loop, a fencing-token claim, or a generic distributed-lock API.',
    ],
    'docs/decisions/028-application-owned-redis-cache-and-schedule-lease.md' => [
        'Status: accepted',
        'PHPThis accepts one application-owned Redis proof in the executable example and adds no framework cache, Redis, lock, or lease API.',
        'authentication, tenant resolution, and current authorization complete',
        'phpthis_example:<environment>:tenant:<account-id>:document_details:v1:<document-key>',
        'phpthis_example:<environment>:schedule_run:v1',
        'SET key token NX PX 30000',
        'not a monotonically increasing fencing token',
    ],
    'docs/redis-coordination.md' => [
        'A logical database number does not create the required separation.',
        'authenticate -> resolve tenant -> authorize -> cache read',
        'execute authoritative SQLite autocommit update -> invalidate exact Redis key',
        'The token is an ownership check, not a fencing token.',
    ],
    'docs/redis/topology.md' => [
        'noeviction',
        'A logical database number is not separation',
    ],
    'example/.ai/cache.md' => [
        'RedisDocumentDetailsCache',
        'RedisInvalidatingDocumentTitleUpdate',
        'The cache excludes not-found results, credentials, principals, memberships, permission data, denials, session state, secrets, and authorization decisions.',
    ],
    'example/src/ApplicationComposition.php' => [
        'new RedisDocumentDetailsCache(',
        "':schedule_run:v1'",
    ],
    'example/src/Documents/GetDocument/RedisDocumentDetailsCache.php' => [
        'implements RetrieveAuthorizedDocument',
        'MAXIMUM_PAYLOAD_BYTES = 1_024',
        'document_details:v1',
        'setOption(Redis::OPT_MAX_RETRIES, 0)',
        "['px' => \$this->ttlMilliseconds]",
    ],
    'example/src/Documents/UpdateDocumentTitle/RedisInvalidatingDocumentTitleUpdate.php' => [
        'UPDATE documents',
        'account_memberships.principal_id = :principal_id',
        '$this->cache->invalidate($accountId, $documentKey)',
    ],
    'example/src/Coordination/RedisScheduleRunLease.php' => [
        'LEASE_TTL_MILLISECONDS = 30_000',
        'CONNECT_TIMEOUT_SECONDS = 0.25',
        'READ_TIMEOUT_SECONDS = 0.25',
        'setOption(Redis::OPT_MAX_RETRIES, 0)',
        "'NX'",
        "'PX' => self::LEASE_TTL_MILLISECONDS",
        'self::RENEW_SCRIPT',
        'self::RELEASE_SCRIPT',
    ],
    'example/src/Observability/RequestSummary.php' => [
        "'schema_version' => self::SCHEMA_VERSION,",
        "'document_cache' => \$this->documentCache,",
    ],
    'tests/cache.php' => [
        'Redis proof uses distinct recorded cache and noeviction lease endpoints',
        "phpversion('redis')",
        "version_compare(\$clientVersion, '6.3.0', '<')",
        "version_compare(\$leaseInfo['redis_version'], '9.0.0', '>=')",
        'getOption(Redis::OPT_MAX_RETRIES) !== 0',
        'authorization denial performs no cache or protected source work',
        'Redis document cache preserves constant authoritative SQL on cold small and large fixtures',
        'Redis document cache bounds the accepted stale-refill race with finite TTL',
        'authoritative document update survives explicit invalidation outage',
    ],
    'tests/redis-coordination.php' => [
        'Redis schedule lease cannot renew or delete a successor lease',
        'Redis schedule lease preserves safe cleanup after an uncertain renewal',
        'Redis schedule lease bounds renewals and its structured outcome trace',
    ],
    'tests/cli.php' => [
        'schedule run skips a subprocess-held Redis lease without blocking or delivering',
        'schedule run recovers after a lease-holder process dies and Redis expires ownership',
    ],
    'tests/run.php' => [
        "require __DIR__ . '/cache.php';",
        "require __DIR__ . '/redis-coordination.php';",
        "frameworkBehaviorGroupDefinitions('cache', cacheTests())",
        "frameworkBehaviorGroupDefinitions('redis-coordination', redisCoordinationTests())",
    ],
    '.github/workflows/ci.yml' => [
        'redis-cache:',
        'redis-lease:',
        'extensions: pdo, pdo_sqlite, redis',
    ],
    'tools/package-files.txt' => [
        'docs/decisions/028-application-owned-redis-cache-and-schedule-lease.md',
        'docs/redis-coordination.md',
        'docs/redis/topology.md',
    ],
];

foreach ($redisCoordinationArtifactMarkers as $relativePath => $markers) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        $failures[] = "Cannot read Redis coordination artifact {$relativePath}.";
        continue;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $failures[] = "Redis coordination artifact marker is missing from {$relativePath}.";
        }
    }
}

foreach (
    [
        'example/src/Documents/GetDocument/RedisDocumentDetailsCache.php',
        'example/src/Coordination/RedisScheduleRunLease.php',
    ] as $redisClientSource
) {
    $contents = file_get_contents($root . '/' . $redisClientSource);

    if (!is_string($contents)) {
        continue;
    }

    $connectPosition = strpos($contents, '->connect(');
    $retryOptionPosition = strpos($contents, 'setOption(Redis::OPT_MAX_RETRIES, 0)');

    if (
        $connectPosition === false
        || $retryOptionPosition === false
        || $retryOptionPosition <= $connectPosition
    ) {
        $failures[] = "Redis client {$redisClientSource} must disable phpredis retries after connect resets client options.";
    }
}

$leaseSource = file_get_contents(
    $root . '/example/src/Coordination/RedisScheduleRunLease.php',
);

if (
    is_string($leaseSource)
    && (
        substr_count($leaseSource, 'setOption(Redis::OPT_MAX_RETRIES, 0)') !== 2
        || substr_count(
            $leaseSource,
            'setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE)',
        ) !== 2
        || substr_count(
            $leaseSource,
            'setOption(Redis::OPT_COMPRESSION, Redis::COMPRESSION_NONE)',
        ) !== 2
        || substr_count($leaseSource, 'setOption(Redis::OPT_REPLY_LITERAL, false)') !== 2
    )
) {
    $failures[] = 'Every Redis schedule-lease construction path must normalize all correctness-sensitive client options.';
}

foreach (['src/Cache', 'src/Caching', 'src/Redis', 'src/Coordination', 'src/Lease', 'src/Lock'] as $forbiddenCoreDirectory) {
    if (is_dir($root . '/' . $forbiddenCoreDirectory)) {
        $failures[] = "Redis cache and schedule-lease runtime must remain outside framework core: {$forbiddenCoreDirectory}.";
    }
}

$redisPackageInventory = file_get_contents($root . '/tools/package-files.txt');

if (
    is_string($redisPackageInventory)
    && preg_match('/^src\/(?:Cache|Caching|Redis|Coordination|Lease|Lock)\//m', $redisPackageInventory) === 1
) {
    $failures[] = 'Redis cache and schedule-lease runtime must remain outside the framework package API.';
}

if (is_string($composerManifest)) {
    $redisComposer = json_decode($composerManifest, true);

    if (!is_array($redisComposer)) {
        $failures[] = 'Cannot decode composer.json for the Redis evidence boundary.';
    } else {
        $runtimeRequirements = $redisComposer['require'] ?? null;
        $developmentRequirements = $redisComposer['require-dev'] ?? null;

        if (is_array($runtimeRequirements) && array_key_exists('ext-redis', $runtimeRequirements)) {
            $failures[] = 'The Redis extension must not become a framework runtime dependency.';
        }

        if (
            !is_array($developmentRequirements)
            || ($developmentRequirements['ext-redis'] ?? null) !== '^6.3'
        ) {
            $failures[] = 'Repository Redis evidence must declare the tested ext-redis ^6.3 development range.';
        }
    }
}

$migrationArtifactMarkers = [
    '.ai/README.md' => [
        'Add, change, place, or review database migrations',
        '`.ai/migrations.md`, `.ai/database.md`, `.ai/cli.md`, `.ai/testing.md`, ADR 027',
    ],
    '.ai/application-context.md' => [
        '`NOT_APPLICABLE(MIGRATIONS)`',
        'Contract version 9 does not make that additional file a checker requirement',
        'Recommend `src/Database/Migrations/` with the matching application namespace',
        'preserve any coherent application-owned alternative',
        'multiple named database connections adopt independent migration histories',
    ],
    '.ai/migrations.md' => [
        '# Migration authoring contract',
        'PHPThis provides no core migration API.',
        'recommend `src/Database/Migrations/` with a matching namespace',
        'Accept any coherent application-owned alternative.',
        'A relocation is an application architecture change requiring explicit human approval.',
        'multiple named database connections actually own independent migration histories',
        'do not prescribe or scaffold speculative subdivisions for a single-database application',
        'Never run it during HTTP startup or through framework `bin/phpthis`.',
        'Do not scan files, discover classes, resolve strings, or load runtime `.sql` files.',
        'Never call a database method in a loop',
    ],
    '.ai/testing.md' => [
        'An application that adopts ADR 027 migrations must execute the real console in fresh subprocesses',
        'zero migration work during HTTP startup',
    ],
    'docs/migrations.md' => [
        '# Explicit application migrations',
        'PHPThis accepts one application-owned SQLite migration-ledger pattern and provides no core migration runtime.',
        '## Recommended application structure',
        'src/',
        'Database/',
        'Migrations/',
        'record the actual path and namespace in `.ai/migrations.md`',
        'A consumer may choose any coherent alternative.',
        'does not enforce a path through the checker or Strict Profile',
        'A database-free skeleton creates no empty migration directory.',
        'PHPThis recommends no subdivision spelling',
        'connection without its own migration history',
        'does not recommend a generic `Database/Queries` directory, repository, query-object layer, or alternate SQL execution boundary',
        'PHPStan must resolve every direct SQL argument to finite non-blank compile-time constants.',
        'The manifest cap is 512 and the bounded ledger query uses `LIMIT 513`.',
        '`0007_create_account_users`',
        '23-statement budget and trace',
        'Do not expose it through HTTP configuration or compose the coordinator during request startup.',
    ],
    'docs/database.md' => [
        'Migrations are specialized application-owned database evolution.',
        'multiple named database connections independently adopt migration histories',
        'creates no speculative connection directories for a single-database application',
    ],
    'docs/decisions/027-application-owned-explicit-sqlite-migrations.md' => [
        'Status: accepted',
        'Consumer Contract version 6, Strict Profile version 2, and the 2,500-line core ceiling remain unchanged.',
        'final `Example\\Migrations\\SqliteApplicationMigrations`',
        '`0001_create_user_schema`',
        '`0006_create_document_access_schema`',
        '`application_migrations`',
        '`LIMIT 513`',
        '21-statement budget and trace',
        'mode `0600`',
        'No framework migration API, schema abstraction, reusable runner, discovery rule, core change, Consumer Contract version, Strict Profile version, or cross-engine claim is introduced.',
        'This record preserves the original `Example\\Migrations` name as historical evidence',
        'the current example was subsequently moved to `Example\\Database\\Migrations`',
    ],
    'docs/decisions/039-recommended-database-migration-structure.md' => [
        'Status: accepted',
        'Migrations are specialized application-owned database evolution.',
        'src/',
        'Database/',
        'Migrations/',
        'A consumer may instead record any coherent application-owned path and namespace.',
        'does not reject an alternative, enforce this directory through the checker or Strict Profile',
        'must preserve the current structure unless an accountable human explicitly approves',
        'The database-free skeleton does not create an empty migration directory.',
        'multiple named database connections genuinely own independent migration histories',
        'does not create speculative connection directories for a single-database application',
        'does not establish a generic database layer',
        'Consumer Contract version 10 and Strict Profile version 3 remain unchanged',
    ],
    'docs/consumer-contract.md' => [
        '## Optional application-owned database migrations',
        'Contract version 9 does not make that additional file a checker requirement',
        'ADR 039 recommends `src/Database/Migrations/`',
        'A coherent consumer-selected alternative remains valid',
        'does not enforce migration placement through the checker or Strict Profile',
        'no empty migration directory',
        'explicit connection-owned subdivision for each adopted history',
        'Do not invent connection subdivisions for a single-database application',
        'It never runs from the front controller, request composition, HTTP startup, framework `vendor/bin/phpthis`, command discovery, or dependency hooks.',
    ],
    'docs/vocabulary.md' => [
        'recommended migration placement',
        'connection-owned migration subdivision',
        'speculative single-database directory',
    ],
    'docs/decisions/README.md' => [
        '027-application-owned-explicit-sqlite-migrations.md',
        '039-recommended-database-migration-structure.md',
    ],
    'docs/knowledge-map.md' => [
        'Add, place, apply, explain, or recover a database migration',
        '`docs/migrations.md`, `docs/database.md`, `docs/security.md`',
        'connection-owned subdivision only for a named connection with an independently adopted migration history',
    ],
    'docs/guardrails.md' => [
        "ADR 039's migration-structure recommendation",
        'exact seven-file `example/src/Database/Migrations/` source set and namespace',
        'Multiple named connections may receive application-selected connection-owned subdivisions only when they independently adopt migration histories',
        'Composer-autoload and installed-checker proof using the alternative `src/Infrastructure/ChangeHistory/` source and `App\\Infrastructure\\ChangeHistory` namespace',
        'installed-consumer proof separately runs the canonical checker with a coherent nonrecommended source directory and matching namespace',
        'places one valid final class there, proves Composer can autoload it, and requires the installed canonical checker to pass',
    ],
    'README.md' => [
        'Schema evolution begins with one application-owned SQLite migration ledger',
        'php example/bin/console.php database:migrate',
    ],
    'ROADMAP.md' => [
        'ADR 027 accepts one application-owned SQLite migration ledger',
        'not a core schema API, migration discovery, down-migration engine, HTTP bootstrap behavior, or portable DDL contract',
    ],
    'example/.ai/README.md' => [
        'Change database schema migrations, migration history, migration placement, or migration recovery',
        '`bin/console.php`, `ApplicationComposition`, `src/Database/Migrations/`',
    ],
    'example/.ai/migrations.md' => [
        '# Example SQLite migration context',
        '`Example\\Database\\Migrations\\SqliteApplicationMigrations` coordinator',
        'application-relative source directory `src/Database/Migrations/` (repository path `example/src/Database/Migrations/`)',
        'not framework discovery or checker-enforced placement',
        'The manifest cap is 512 migrations and the ordered position/identifier/checksum history read uses `LIMIT 513`',
        '`0007_create_account_users`',
        'QueryBudget(23)',
        'The migration lock path is the canonical database path plus `.migration.lock`.',
        '`tools/setup-example.php` delegates schema work to this exact coordinator',
    ],
    'example/AGENTS.md' => [
        'Keep `database:migrate` as the sole application migration command',
        '`tools/setup-example.php` may delegate to that exact coordinator before seeding; it must not duplicate schema SQL.',
    ],
    'templates/application/.ai/migrations.md' => [
        '{{MIGRATION_ADOPTION_OR_NOT_APPLICABLE}}',
        '{{MIGRATION_SOURCE_DIRECTORY_OR_NOT_APPLICABLE}}',
        '{{MIGRATION_APPLICATION_NAMESPACE_OR_NOT_APPLICABLE}}',
        '{{MIGRATION_CONNECTION_OWNERSHIP_OR_NOT_APPLICABLE}}',
        'PHPThis recommends `src/Database/Migrations/`',
        'A coherent consumer-selected alternative is authoritative',
        'connection without an independently adopted migration history',
        '{{MIGRATION_MANIFEST_SOURCE_OR_NOT_APPLICABLE}}',
        'no database call occurs in a loop',
        'A non-SQLite adoption requires a separate engine-specific DDL, transaction, locking, privilege, recovery, and integration decision.',
    ],
    'skeleton/.ai/migrations.md' => [
        '`NOT_APPLICABLE(MIGRATIONS)`',
        'No migration directory, code, or dependency is included',
        'PHPThis recommends `src/Database/Migrations/`',
        '`App\\Database\\Migrations` namespace',
        'A coherent consumer-selected alternative is authoritative',
        'multiple named database connections later adopt independent migration histories',
        'do not pre-create or prescribe connection subdivisions',
        'HTTP startup performs no data-definition or authority-transition work.',
        'runtime `.sql` loading',
    ],
    'example/src/Database/Migrations/ApplicationMigrationFailureReason.php' => [
        "case Busy = 'busy';",
        "case ChecksumDrift = 'checksum_drift';",
        "case HistoryInvalid = 'history_invalid';",
        "case LedgerUnavailable = 'ledger_unavailable';",
        "case ApplyFailed = 'apply_failed';",
        "case LockFailed = 'lock_failed';",
    ],
    'example/src/Database/Migrations/ApplicationMigrationOutcome.php' => [
        "case Applied = 'applied';",
        "case UpToDate = 'up_to_date';",
    ],
    'example/src/Database/Migrations/ApplicationMigrationFailed.php' => [
        "'error' => 'migration_failed'",
        "'reason' => \$this->reason->value",
        "'migration' => \$this->migrationIdentifier",
    ],
    'example/src/Database/Migrations/LocalMigrationLock.php' => [
        "fopen(\$this->path, 'c+b')",
        '@chmod($this->path, 0600)',
        '@fstat($handle)',
        '@lstat($this->path)',
        "\$handleStatus['nlink'] === 1",
        "\$handleStatus['ino'] === \$pathStatus['ino']",
        'flock($handle, LOCK_EX | LOCK_NB, $wouldBlock)',
        'flock($handle, LOCK_UN)',
    ],
    'example/src/Database/Migrations/MigrationHistory.php' => [
        'count($rows) > 512',
        "array_keys(\$row) !== ['position', 'migration_id', 'checksum_sha256']",
        "ApplicationMigrationFailureReason::ChecksumDrift",
    ],
    'example/src/Database/Migrations/SqliteMigrationLedger.php' => [
        'CREATE TABLE application_migrations',
        'position INTEGER PRIMARY KEY',
        'migration_id TEXT NOT NULL UNIQUE',
        'checksum_sha256 TEXT NOT NULL',
        'applied_at_epoch INTEGER NOT NULL',
        'sqlite_autoindex_application_migrations_1',
        'ORDER BY sqlite_master.type ASC, sqlite_master.name ASC',
        'LIMIT 513',
        'unixepoch()',
    ],
    'example/src/Database/Migrations/SqliteApplicationMigrations.php' => [
        'final readonly class SqliteApplicationMigrations',
        'private const int QUERY_LIMIT = 23;',
        "private const string USER_SCHEMA_IDENTIFIER = '0001_create_user_schema';",
        "private const string JOB_SCHEMA_IDENTIFIER = '0002_create_job_schema';",
        "private const string PREPARE_DOCUMENT_IDENTIFIER = '0003_prepare_document_schema';",
        "private const string DOCUMENT_CATEGORY_IDENTIFIER = '0004_add_document_category';",
        "private const string DOCUMENT_SORT_RANK_IDENTIFIER = '0005_add_document_sort_rank';",
        "private const string DOCUMENT_ACCESS_IDENTIFIER = '0006_create_document_access_schema';",
        "private const string ACCOUNT_USERS_IDENTIFIER = '0007_create_account_users';",
        'private const string CREATE_ACCOUNT_USERS_SQL',
        'new QueryBudget(self::QUERY_LIMIT)',
        'new QueryTrace(self::QUERY_LIMIT)',
        'options: [PDO::ATTR_TIMEOUT => 5]',
        '$connection->beginTransaction();',
        '$ledger->record(',
        '$connection->commit();',
    ],
    'example/src/Cli/ApplicationCommands.php' => [
        'new SqliteApplicationMigrations(',
        "new LocalMigrationLock(\$databasePath . '.migration.lock')",
        'ApplicationMigrationOutcome::Applied => ApplicationCommandOutcome::Applied',
        'ApplicationMigrationOutcome::UpToDate => ApplicationCommandOutcome::UpToDate',
    ],
    'example/bin/console.php' => [
        'catch (ApplicationMigrationFailed $exception)',
        'fwrite(STDERR, $exception->stderrLine());',
    ],
    'tests/run.php' => [
        "require __DIR__ . '/migrations.php';",
        "frameworkBehaviorGroupDefinitions('migrations', migrationTests())",
    ],
    'tests/migrations.php' => [
        'database migrate applies an ordered inspectable ledger and reruns as a no-op',
        'database migrate adds account users without conflating principal identities',
        'database migrate rejects checksum drift before pending migration work',
        'database migrate rejects an incompatible preexisting ledger schema',
        'database migrate reports exact redacted ledger and lock failures',
        'migration history rejects malformed and oversized database snapshots',
        'database migrate preserves earlier commits across a later migration failure',
        'database migrate refuses to infer a baseline for an unledgered existing schema',
        'database migrate fails fast under a subprocess-held migration lock',
        'HTTP startup must not create the database or migration ledger.',
    ],
    'tests/cli-migration-lock-holder.php' => [
        "\$databasePath . '.migration.lock'",
        'chmod($lockPath, 0600)',
        'flock($handle, LOCK_EX | LOCK_NB)',
        'fwrite(STDOUT, "READY\\n")',
    ],
    'tools/setup-example.php' => [
        '->run(ApplicationCommandName::DatabaseMigrate);',
    ],
    'tools/test-consumer-project.php' => [
        'proveInstalledMigrationStructureGuidanceDistribution(',
        "\$project . '/src/Infrastructure/ChangeHistory'",
        "\$alternativeDirectory . '/ApplicationMigrations.php'",
        'The consumer-selected migration path and namespace are not Composer-autoload coherent.',
        'The installed checker rejected a coherent consumer-selected migration structure.',
        'PASS installed migration alternative structure',
        'PASS installed migration structure guidance distribution',
        'The database-free installed skeleton unexpectedly contains a migration directory.',
    ],
    'tools/package-files.txt' => [
        'docs/migrations.md',
        'docs/decisions/027-application-owned-explicit-sqlite-migrations.md',
        'docs/decisions/039-recommended-database-migration-structure.md',
        'templates/application/.ai/migrations.md',
    ],
];

foreach ($migrationArtifactMarkers as $relativePath => $markers) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        $failures[] = "Cannot read migration artifact {$relativePath}.";
        continue;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $failures[] = "Migration artifact marker is missing from {$relativePath}: {$marker}.";
        }
    }
}

$recommendedExampleMigrationDirectory = $root . '/example/src/Database/Migrations';
$legacyExampleMigrationDirectory = $root . '/example/src/Migrations';
$expectedExampleMigrationFiles = [
    'ApplicationMigrationFailed.php',
    'ApplicationMigrationFailureReason.php',
    'ApplicationMigrationOutcome.php',
    'LocalMigrationLock.php',
    'MigrationHistory.php',
    'SqliteApplicationMigrations.php',
    'SqliteMigrationLedger.php',
];

if (is_dir($legacyExampleMigrationDirectory)) {
    $failures[] = 'The maintained example must use the recommended src/Database/Migrations structure.';
}

if (!is_dir($recommendedExampleMigrationDirectory)) {
    $failures[] = 'The maintained example migration directory is missing: example/src/Database/Migrations.';
} else {
    $actualExampleMigrationFiles = [];

    foreach (new DirectoryIterator($recommendedExampleMigrationDirectory) as $migrationEntry) {
        if ($migrationEntry->isDot()) {
            continue;
        }

        if (!$migrationEntry->isFile()) {
            $failures[] = 'The maintained example migration directory must contain only the reviewed PHP files.';
            continue;
        }

        $actualExampleMigrationFiles[] = $migrationEntry->getFilename();
        $migrationContents = file_get_contents($migrationEntry->getPathname());

        if (
            !is_string($migrationContents)
            || !str_contains($migrationContents, 'namespace Example\\Database\\Migrations;')
        ) {
            $failures[] = "Maintained example migration {$migrationEntry->getFilename()} must use the Example\\Database\\Migrations namespace.";
        }
    }

    sort($actualExampleMigrationFiles);
    sort($expectedExampleMigrationFiles);

    if ($actualExampleMigrationFiles !== $expectedExampleMigrationFiles) {
        $failures[] = 'The maintained example migration file set changed without review.';
    }
}

foreach (
    ['src/Migration', 'src/Migrations', 'src/Schema', 'src/SchemaBuilder']
    as $forbiddenCoreDirectory
) {
    if (is_dir($root . '/' . $forbiddenCoreDirectory)) {
        $failures[] = "Migration runtime must remain application-owned outside framework core: {$forbiddenCoreDirectory}.";
    }
}

$databaseCoreDirectory = $root . '/src/Database';

if (is_dir($databaseCoreDirectory)) {
    foreach (new DirectoryIterator($databaseCoreDirectory) as $databaseCoreEntry) {
        if ($databaseCoreEntry->isDot()) {
            continue;
        }

        if (
            str_starts_with($databaseCoreEntry->getFilename(), 'Migration')
            || str_starts_with($databaseCoreEntry->getFilename(), 'Schema')
        ) {
            $failures[] = 'Migration or schema-building runtime must not enter src/Database.';
        }
    }
}

$migrationPackageInventory = file_get_contents($root . '/tools/package-files.txt');

if (is_string($migrationPackageInventory)) {
    foreach (
        [
            '/^src\/(?:Migration|Migrations|Schema|SchemaBuilder)(?:\/|\.php$)/m',
            '/^src\/Database\/(?:Migration|Schema)/m',
            '/^\.ai\/migrations\.md$/m',
            '/^example\/src\/(?:Database\/)?Migrations\//m',
            '/^skeleton\/\.ai\/migrations\.md$/m',
            '/^tests\/(?:migrations|cli-migration-lock-holder)\.php$/m',
        ] as $forbiddenMigrationPackagePattern
    ) {
        if (preg_match($forbiddenMigrationPackagePattern, $migrationPackageInventory) === 1) {
            $failures[] = 'Application-owned migration runtime and evidence must remain outside the framework package inventory.';
        }
    }
}

if (is_string($applicationChecker) && str_contains($applicationChecker, "'.ai/migrations.md',")) {
    $failures[] = 'Contract version 9 must not checker-require the optional migration context file.';
}

if (
    is_string($applicationChecker)
    && (
        str_contains($applicationChecker, 'src/Database/Migrations')
        || str_contains($applicationChecker, 'src/Migrations')
    )
) {
    $failures[] = 'The consumer checker must not enforce a migration source directory.';
}

if (is_string($consumerProjectProof) && str_contains($consumerProjectProof, 'proveMigrationsContextIsRequired')) {
    $failures[] = 'Contract version 9 must not reject an existing consumer only because .ai/migrations.md is absent.';
}

foreach (['skeleton/src/Database/Migrations', 'skeleton/src/Migrations'] as $emptySkeletonMigrationPath) {
    if (is_dir($root . '/' . $emptySkeletonMigrationPath)) {
        $failures[] = "The database-free skeleton must not create an empty migration directory: {$emptySkeletonMigrationPath}.";
    }
}

$runtimeSqlRoots = ['src', 'example', 'skeleton', 'templates/application', 'tools'];

foreach ($runtimeSqlRoots as $runtimeSqlRoot) {
    $runtimeSqlPath = $root . '/' . $runtimeSqlRoot;

    if (!is_dir($runtimeSqlPath)) {
        continue;
    }

    $runtimeSqlFiles = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($runtimeSqlPath, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($runtimeSqlFiles as $runtimeSqlFile) {
        if (
            $runtimeSqlFile instanceof SplFileInfo
            && $runtimeSqlFile->isFile()
            && strtolower($runtimeSqlFile->getExtension()) === 'sql'
        ) {
            $relativeSqlPath = substr($runtimeSqlFile->getPathname(), strlen($root) + 1);
            $failures[] = "Runtime .sql files are forbidden; keep direct finite SQL in PHP source: {$relativeSqlPath}.";
        }
    }
}

$migrationRuntimeSourceFiles = ['example/src/Cli/ApplicationCommands.php'];
$migrationRuntimeDirectory = $root . '/example/src/Database/Migrations';
$migrationRuntimeFiles = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($migrationRuntimeDirectory, FilesystemIterator::SKIP_DOTS),
);

foreach ($migrationRuntimeFiles as $migrationRuntimeFile) {
    if (
        $migrationRuntimeFile instanceof SplFileInfo
        && $migrationRuntimeFile->isFile()
        && strtolower($migrationRuntimeFile->getExtension()) === 'php'
    ) {
        $migrationRuntimeSourceFiles[] = substr(
            $migrationRuntimeFile->getPathname(),
            strlen($root) + 1,
        );
    }
}

sort($migrationRuntimeSourceFiles);
$forbiddenMigrationDiscoveryMarkers = [
    'class_exists(',
    'get_declared_classes(',
    'get_declared_interfaces(',
    'get_declared_traits(',
    'glob(',
    'scandir(',
    'DirectoryIterator',
    'FilesystemIterator',
    'RecursiveDirectoryIterator',
    'RecursiveIteratorIterator',
    'ReflectionClass',
    'ReflectionFunction',
    'ReflectionMethod',
    'SplFileObject',
];
$forbiddenMigrationFileFunctions = [
    'file',
    'file_get_contents',
    'fgets',
    'fread',
    'parse_ini_file',
    'readfile',
    'stream_get_line',
    'stream_get_contents',
];
$forbiddenMigrationAbstractions = [
    'MigrationInterface',
    'MigrationRegistry',
    'QueryBuilder',
    'SchemaBuilder',
    'TransactionCallback',
    'bindParam(',
    'bindValue(',
];

foreach ($migrationRuntimeSourceFiles as $relativePath) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        continue;
    }

    foreach (array_merge($forbiddenMigrationDiscoveryMarkers, $forbiddenMigrationAbstractions) as $marker) {
        if (str_contains($contents, $marker)) {
            $failures[] = "Migration runtime source {$relativePath} contains forbidden discovery loading or abstraction marker {$marker}.";
        }
    }

    foreach (token_get_all($contents) as $token) {
        if (!is_array($token)) {
            continue;
        }

        if (in_array($token[0], [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE], true)) {
            $failures[] = "Migration runtime source {$relativePath} must not load executable source at runtime.";
            continue;
        }

        if (!in_array($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED], true)) {
            continue;
        }

        $functionName = strtolower(ltrim($token[1], '\\'));
        $separator = strrpos($functionName, '\\');

        if ($separator !== false) {
            $functionName = substr($functionName, $separator + 1);
        }

        if (
            in_array($functionName, $forbiddenMigrationFileFunctions, true)
            || ($functionName === 'fopen' && $relativePath !== 'example/src/Database/Migrations/LocalMigrationLock.php')
        ) {
            $failures[] = "Migration runtime source {$relativePath} must not load migration SQL or source from runtime files.";
        }
    }
}

$migrationCoordinator = file_get_contents(
    $root . '/example/src/Database/Migrations/SqliteApplicationMigrations.php',
);

if (is_string($migrationCoordinator)) {
    foreach (
        [
            'if (!$history->contains(' => 7,
            '$connection->beginTransaction();' => 7,
            '$ledger->record(' => 7,
            '$connection->commit();' => 7,
            '$connection->rollBack();' => 7,
        ] as $migrationCoordinatorMarker => $expectedCount
    ) {
        if (substr_count($migrationCoordinator, $migrationCoordinatorMarker) !== $expectedCount) {
            $failures[] = sprintf(
                'SqliteApplicationMigrations marker %s must occur exactly %d times.',
                $migrationCoordinatorMarker,
                $expectedCount,
            );
        }
    }

    foreach (token_get_all($migrationCoordinator) as $token) {
        if (is_array($token) && in_array($token[0], [T_FOR, T_FOREACH, T_WHILE, T_DO], true)) {
            $failures[] = 'SqliteApplicationMigrations must keep every migration and database call explicitly unrolled.';
            break;
        }
    }

    $migrationSqlOrderMarkers = [
        <<<'PHP'
            $connection->executeStatement(self::CREATE_USERS_SQL);
            $connection->executeStatement(self::CREATE_USER_EVENTS_SQL);
            $connection->executeStatement(self::CREATE_USER_EVENTS_INDEX_SQL);
            PHP,
        <<<'PHP'
            self::USER_SCHEMA_IDENTIFIER . "\0"
                . self::CREATE_USERS_SQL . "\0"
                . self::CREATE_USER_EVENTS_SQL . "\0"
                . self::CREATE_USER_EVENTS_INDEX_SQL,
            PHP,
        <<<'PHP'
            $connection->executeStatement(self::CREATE_APPLICATION_JOBS_SQL);
            $connection->executeStatement(self::CREATE_APPLICATION_JOBS_AVAILABLE_INDEX_SQL);
            $connection->executeStatement(self::CREATE_APPLICATION_JOBS_LEASE_INDEX_SQL);
            $connection->executeStatement(self::CREATE_WELCOME_DELIVERIES_SQL);
            PHP,
        <<<'PHP'
            self::JOB_SCHEMA_IDENTIFIER . "\0"
                . self::CREATE_APPLICATION_JOBS_SQL . "\0"
                . self::CREATE_APPLICATION_JOBS_AVAILABLE_INDEX_SQL . "\0"
                . self::CREATE_APPLICATION_JOBS_LEASE_INDEX_SQL . "\0"
                . self::CREATE_WELCOME_DELIVERIES_SQL,
            PHP,
        '$connection->executeStatement(self::CREATE_DOCUMENTS_SQL);',
        <<<'PHP'
            self::PREPARE_DOCUMENT_IDENTIFIER . "\0"
                . self::CREATE_DOCUMENTS_SQL,
            PHP,
        '$connection->executeStatement(self::ADD_DOCUMENT_CATEGORY_SQL);',
        <<<'PHP'
            self::DOCUMENT_CATEGORY_IDENTIFIER . "\0"
                . self::ADD_DOCUMENT_CATEGORY_SQL,
            PHP,
        '$connection->executeStatement(self::ADD_DOCUMENT_SORT_RANK_SQL);',
        <<<'PHP'
            self::DOCUMENT_SORT_RANK_IDENTIFIER . "\0"
                . self::ADD_DOCUMENT_SORT_RANK_SQL,
            PHP,
        <<<'PHP'
            $connection->executeStatement(self::CREATE_DOCUMENT_INDEX_SQL);
            $connection->executeStatement(self::CREATE_ACCOUNT_MEMBERSHIPS_SQL);
            PHP,
        <<<'PHP'
            self::DOCUMENT_ACCESS_IDENTIFIER . "\0"
                . self::CREATE_DOCUMENT_INDEX_SQL . "\0"
                . self::CREATE_ACCOUNT_MEMBERSHIPS_SQL,
            PHP,
        '$connection->executeStatement(self::CREATE_ACCOUNT_USERS_SQL);',
        <<<'PHP'
            self::ACCOUNT_USERS_IDENTIFIER . "\0"
                . self::CREATE_ACCOUNT_USERS_SQL,
            PHP,
    ];

    $normalizedMigrationCoordinator = preg_replace('/\s+/', ' ', $migrationCoordinator);

    foreach ($migrationSqlOrderMarkers as $migrationSqlOrderMarker) {
        $normalizedMigrationSqlOrderMarker = preg_replace(
            '/\s+/',
            ' ',
            trim($migrationSqlOrderMarker),
        );

        if (
            !is_string($normalizedMigrationCoordinator)
            || !is_string($normalizedMigrationSqlOrderMarker)
            || !str_contains($normalizedMigrationCoordinator, $normalizedMigrationSqlOrderMarker)
        ) {
            $failures[] = 'Migration execution order and checksum-covered SQL order must remain paired explicitly.';
        }
    }
}

$httpMigrationBoundaryFiles = [
    'example/bootstrap.php',
    'example/public/index.php',
    'example/src/ApplicationComposition.php',
    'skeleton/bootstrap.php',
    'skeleton/public/index.php',
];
$forbiddenHttpMigrationMarkers = [
    'ApplicationCommandName::DatabaseMigrate',
    'SqliteApplicationMigrations',
    'application_migrations',
    'database:migrate',
    'setup-example.php',
];

foreach ($httpMigrationBoundaryFiles as $relativePath) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        continue;
    }

    foreach ($forbiddenHttpMigrationMarkers as $marker) {
        if (str_contains($contents, $marker)) {
            $failures[] = "HTTP startup boundary {$relativePath} must not wire migration marker {$marker}.";
        }
    }
}

$frameworkRuntimePaths = ['bin/phpthis'];
$frameworkSourceFiles = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS),
);

foreach ($frameworkSourceFiles as $frameworkSourceFile) {
    if (
        $frameworkSourceFile instanceof SplFileInfo
        && $frameworkSourceFile->isFile()
        && strtolower($frameworkSourceFile->getExtension()) === 'php'
    ) {
        $frameworkRuntimePaths[] = substr($frameworkSourceFile->getPathname(), strlen($root) + 1);
    }
}

foreach ($frameworkRuntimePaths as $frameworkRuntimePath) {
    $frameworkRuntime = file_get_contents($root . '/' . $frameworkRuntimePath);

    if (
        is_string($frameworkRuntime)
        && preg_match('/\bmigrat(?:e|es|ed|ing|ion|ions)\b/i', $frameworkRuntime) === 1
    ) {
        $failures[] = "Migration behavior must remain outside framework runtime source: {$frameworkRuntimePath}.";
    }
}

$composerLifecycleEvents = [
    'pre-install-cmd',
    'post-install-cmd',
    'pre-update-cmd',
    'post-update-cmd',
    'post-root-package-install',
    'post-create-project-cmd',
    'pre-autoload-dump',
    'post-autoload-dump',
    'pre-status-cmd',
    'post-package-install',
    'post-package-update',
    'pre-package-uninstall',
    'post-package-uninstall',
];

foreach (['composer.json', 'skeleton/composer.json'] as $composerLifecyclePath) {
    $contents = file_get_contents($root . '/' . $composerLifecyclePath);
    $manifest = is_string($contents) ? json_decode($contents, true) : null;
    $scripts = is_array($manifest) ? ($manifest['scripts'] ?? null) : null;

    if (!is_array($scripts)) {
        continue;
    }

    foreach ($composerLifecycleEvents as $composerLifecycleEvent) {
        $commands = $scripts[$composerLifecycleEvent] ?? null;

        if ($commands === null) {
            continue;
        }

        $encodedCommands = json_encode($commands, JSON_THROW_ON_ERROR);

        foreach (
            ['database:migrate', 'SqliteApplicationMigrations', 'setup-example.php', '@example:setup']
            as $migrationLifecycleMarker
        ) {
            if (str_contains($encodedCommands, $migrationLifecycleMarker)) {
                $failures[] = "Composer lifecycle {$composerLifecyclePath}:{$composerLifecycleEvent} must not run migrations.";
            }
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
    $runtimeRequirements = is_array($composer) ? ($composer['require'] ?? null) : null;
    $developmentRequirements = is_array($composer) ? ($composer['require-dev'] ?? null) : null;
    $scripts = is_array($composer) ? ($composer['scripts'] ?? null) : null;
    $check = is_array($scripts) ? ($scripts['check'] ?? null) : null;
    $archive = is_array($composer) ? ($composer['archive'] ?? null) : null;
    $archiveExclusions = is_array($archive) ? ($archive['exclude'] ?? null) : null;

    if (
        !is_array($developmentRequirements)
        || ($developmentRequirements['phpunit/phpunit'] ?? null) !== '^13.0'
    ) {
        $failures[] = 'PHPUnit 13 must remain an exact framework-maintainer require-dev dependency.';
    }

    foreach (is_array($runtimeRequirements) ? array_keys($runtimeRequirements) : [] as $runtimePackage) {
        if (
            is_string($runtimePackage)
            && (
                str_starts_with($runtimePackage, 'phpunit/')
                || str_starts_with($runtimePackage, 'pestphp/')
            )
        ) {
            $failures[] = "Test runner must not become a framework runtime dependency: {$runtimePackage}.";
        }
    }

    foreach (is_array($developmentRequirements) ? array_keys($developmentRequirements) : [] as $developmentPackage) {
        if (is_string($developmentPackage) && str_starts_with($developmentPackage, 'pestphp/')) {
            $failures[] = "Pest is outside the framework-maintainer runner decision: {$developmentPackage}.";
        }
    }

    if (
        !is_array($scripts)
        || ($scripts['test'] ?? null) !== 'php vendor/bin/phpunit --configuration=phpunit.xml.dist'
    ) {
        $failures[] = 'composer test must run the canonical PHPUnit framework-maintainer suite.';
    }

    if (
        !is_array($scripts)
        || ($scripts['test:coverage'] ?? null)
            !== 'php vendor/bin/phpunit --configuration=phpunit.xml.dist --coverage-text --coverage-clover=.phpunit.cache/coverage.xml'
    ) {
        $failures[] = 'composer test:coverage must produce report-only text and Clover coverage.';
    }

    $expectedCheckStages = [
        '@guard',
        '@analyse',
        '@test:profile',
        '@test:duplication',
        '@test:consumer',
        '@test:database-drivers',
        '@test:query-scaling',
        '@test',
    ];

    if ($check !== $expectedCheckStages) {
        $failures[] = 'composer check must preserve every canonical stage in its reviewed order.';
    }

    if (
        !is_array($archiveExclusions)
        || !in_array('/phpunit.xml.dist', $archiveExclusions, true)
        || !in_array('/.phpunit.cache', $archiveExclusions, true)
    ) {
        $failures[] = 'Maintainer-only PHPUnit configuration and reports must remain outside Composer package archives.';
    }

    if (!is_array($scripts) || ($scripts['test:database-drivers'] ?? null) !== 'php tools/test-database-drivers.php') {
        $failures[] = 'composer.json must define the canonical database-driver certification script.';
    }

    if (!is_array($check) || !in_array('@test:database-drivers', $check, true)) {
        $failures[] = 'composer check must include database-driver certification.';
    }
}

$skeletonComposerContents = file_get_contents($root . '/skeleton/composer.json');
$skeletonComposer = is_string($skeletonComposerContents)
    ? json_decode($skeletonComposerContents, true)
    : null;
$skeletonRuntimeRequirements = is_array($skeletonComposer) ? ($skeletonComposer['require'] ?? null) : null;
$skeletonDevelopmentRequirements = is_array($skeletonComposer) ? ($skeletonComposer['require-dev'] ?? null) : null;
$skeletonScripts = is_array($skeletonComposer) ? ($skeletonComposer['scripts'] ?? null) : null;

foreach (
    [
        is_array($skeletonRuntimeRequirements) ? $skeletonRuntimeRequirements : [],
        is_array($skeletonDevelopmentRequirements) ? $skeletonDevelopmentRequirements : [],
    ] as $skeletonRequirements
) {
    foreach (array_keys($skeletonRequirements) as $skeletonPackage) {
        if (
            is_string($skeletonPackage)
            && (
                str_starts_with($skeletonPackage, 'phpunit/')
                || str_starts_with($skeletonPackage, 'pestphp/')
            )
        ) {
            $failures[] = "The skeleton must keep its application-owned test-runner choice: {$skeletonPackage}.";
        }
    }
}

if (!is_array($skeletonScripts) || ($skeletonScripts['test'] ?? null) !== 'php tests/run.php') {
    $failures[] = 'The skeleton must retain its application-owned example test command.';
}

$gitAttributes = file_get_contents($root . '/.gitattributes');

if (
    !is_string($gitAttributes)
    || preg_match('/^\/phpunit\.xml\.dist export-ignore$/m', $gitAttributes) !== 1
    || preg_match('/^\/\.phpunit\.cache export-ignore$/m', $gitAttributes) !== 1
) {
    $failures[] = 'Maintainer-only PHPUnit configuration and reports must remain outside Git exports.';
}

$phpunitConfig = file_get_contents($root . '/phpunit.xml.dist');

if (!is_string($phpunitConfig)) {
    $failures[] = 'Cannot read phpunit.xml.dist.';
} else {
    foreach (
        [
            'https://schema.phpunit.de/13.2/phpunit.xsd',
            'cacheDirectory=".phpunit.cache"',
            'failOnRisky="true"',
            'failOnWarning="true"',
            '<file>tests/FrameworkBehaviorTest.php</file>',
            '<directory>src</directory>',
            '<directory includeInCodeCoverage="false">example/src</directory>',
            '<coverage includeUncoveredFiles="true"/>',
            '<junit outputFile=".phpunit.cache/junit.xml"/>',
        ] as $phpunitConfigMarker
    ) {
        if (!str_contains($phpunitConfig, $phpunitConfigMarker)) {
            $failures[] = "The framework-maintainer PHPUnit configuration is missing: {$phpunitConfigMarker}.";
        }
    }

    if (
        str_contains($phpunitConfig, '<directory>tests</directory>')
        || substr_count($phpunitConfig, '<file>tests/FrameworkBehaviorTest.php</file>') !== 1
    ) {
        $failures[] = 'PHPUnit must discover only the explicit framework behavior bridge.';
    }
}

$maintainerTestPackageInventory = file_get_contents($root . '/tools/package-files.txt');

if (
    is_string($maintainerTestPackageInventory)
    && preg_match(
        '/^(?:phpunit\.xml\.dist|tests\/FrameworkBehaviorTest\.php|tests\/behavior-names\.txt)$/m',
        $maintainerTestPackageInventory,
    ) === 1
) {
    $failures[] = 'Framework-maintainer test artifacts must remain outside the runtime package inventory.';
}

$behaviorInventory = file_get_contents($root . '/tests/behavior-names.txt');

if (!is_string($behaviorInventory)) {
    $failures[] = 'Cannot read the framework behavior-name inventory.';
} elseif (
    $behaviorInventory === ''
    || !str_ends_with($behaviorInventory, "\n")
    || str_contains($behaviorInventory, "\r")
) {
    $failures[] = 'The framework behavior-name inventory must use non-empty LF-terminated lines.';
} else {
    $behaviorNames = explode("\n", substr($behaviorInventory, 0, -1));

    if (count($behaviorNames) !== 177 || count(array_unique($behaviorNames)) !== 177) {
        $failures[] = 'The framework suite must preserve exactly 177 unique named framework behaviors.';
    }

    if (
        hash('sha256', $behaviorInventory)
        !== '2e775ff43a5ba3d7f530dbecad3ab2aff2b0e8df7869150abf58e528a560db65'
    ) {
        $failures[] = 'The ordered framework behavior-name inventory changed without an explicit parity decision.';
    }
}

$maintainerTestArtifactMarkers = [
    '.ai/README.md' => [
        'Add or focus framework-maintainer tests',
        '`tests/FrameworkBehaviorTest.php`',
        '`phpunit.xml.dist`',
    ],
    '.ai/testing.md' => [
        'PHPUnit 13 as a maintainer-only development runner',
        '`tests/behavior-names.txt` locks their complete order',
        "composer test -- --group routing",
        'migrated query-trace comparison slice',
        'Applications continue to own their test library, runner, organization',
    ],
    'README.md' => [
        'PHPUnit 13 maintainer suite requires PHP 8.4.1 or newer',
        'do not affect the framework runtime or require consumers to select the same test runner',
    ],
    'tests/run.php' => [
        'function frameworkBehaviorDefinitions(): Generator',
        "frameworkBehaviorGroupDefinitions('request-policy', requestPolicyTests())",
        "frameworkBehaviorGroupDefinitions('composition', compositionBehaviorTests())",
        "frameworkBehaviorGroupDefinitions('database-boundary', databaseBoundaryBehaviorTests())",
        'function frameworkBehaviorGroupDefinitions(string $group, iterable $tests): Generator',
        'function compositionBehaviorTests(): Generator',
        'function databaseBoundaryBehaviorTests(): Generator',
        'function frameworkBehaviorRegistry(): array',
        'function frameworkBehaviorTests(): array',
        'function frameworkBehaviorGroups(): array',
        'function frameworkBehaviorNamesForGroup(string $group): array',
        'array_key_exists($name, $registered)',
        'Assert::assertSame(',
        '2e775ff43a5ba3d7f530dbecad3ab2aff2b0e8df7869150abf58e528a560db65',
    ],
    'tests/FrameworkBehaviorTest.php' => [
        "#[Group('request-policy')]",
        "#[Group('routing')]",
        "#[Group('database-boundary')]",
        "#[Group('parity')]",
        "#[TestDox('\$_dataName')]",
        'frameworkBehaviorTests()[$name]',
        '$this->addToAssertionCount(1);',
        'yield $name => [$name];',
        'testReviewedInventoryAndGroupOrderMatchTheRegistry',
        'Expected focused groups to flatten to the exact reviewed behavior inventory.',
    ],
    'tests/request-policy.php' => [
        'function requestPolicyTests(): Generator',
    ],
    'tests/observability.php' => [
        'function observabilityTests(): Generator',
    ],
    'tests/jobs.php' => [
        'function jobTests(): Generator',
    ],
    'tests/cli.php' => [
        'function cliTests(): Generator',
    ],
    'tests/migrations.php' => [
        'function migrationTests(): Generator',
    ],
    'tests/document-files.php' => [
        'function documentFileTests(): Generator',
    ],
    'tests/cache.php' => [
        'function cacheTests(): Generator',
    ],
    'tests/redis-coordination.php' => [
        'function redisCoordinationTests(): Generator',
    ],
    'tests/consumer-profile.php' => [
        'function consumerProfileTests(): Generator',
    ],
    'tests/handler-decorator.php' => [
        'function handlerDecoratorTests(): Generator',
    ],
    'ROADMAP.md' => [
        'adopt PHPUnit 13 only for the framework-maintainer suite',
        'exact-name and coherent-group selection',
        'application-owned consumer test choices',
    ],
];

foreach ($maintainerTestArtifactMarkers as $relativePath => $markers) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        $failures[] = "Cannot read framework-maintainer test artifact {$relativePath}.";
        continue;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $failures[] = "Framework-maintainer test artifact marker is missing from {$relativePath}: {$marker}.";
        }
    }
}

$phpunitBehaviorBridge = file_get_contents($root . '/tests/FrameworkBehaviorTest.php');
/** @var array<non-empty-string, array{test_method: non-empty-string, provider: non-empty-string}> $expectedPhpunitGroupBridges */
$expectedPhpunitGroupBridges = [
    'request-policy' => [
        'test_method' => 'testRequestPolicyBehavior',
        'provider' => 'requestPolicyProvider',
    ],
    'observability' => [
        'test_method' => 'testObservabilityBehavior',
        'provider' => 'observabilityProvider',
    ],
    'jobs' => [
        'test_method' => 'testJobBehavior',
        'provider' => 'jobProvider',
    ],
    'cli' => [
        'test_method' => 'testCliBehavior',
        'provider' => 'cliProvider',
    ],
    'migrations' => [
        'test_method' => 'testMigrationBehavior',
        'provider' => 'migrationProvider',
    ],
    'document-files' => [
        'test_method' => 'testDocumentFileBehavior',
        'provider' => 'documentFileProvider',
    ],
    'cache' => [
        'test_method' => 'testCacheBehavior',
        'provider' => 'cacheProvider',
    ],
    'redis-coordination' => [
        'test_method' => 'testRedisCoordinationBehavior',
        'provider' => 'redisCoordinationProvider',
    ],
    'consumer-profile' => [
        'test_method' => 'testConsumerProfileBehavior',
        'provider' => 'consumerProfileProvider',
    ],
    'handler-decorator' => [
        'test_method' => 'testHandlerDecoratorBehavior',
        'provider' => 'handlerDecoratorProvider',
    ],
    'composition' => [
        'test_method' => 'testCompositionBehavior',
        'provider' => 'compositionProvider',
    ],
    'http-boundary' => [
        'test_method' => 'testHttpBoundaryBehavior',
        'provider' => 'httpBoundaryProvider',
    ],
    'routing' => [
        'test_method' => 'testRoutingBehavior',
        'provider' => 'routingProvider',
    ],
    'input-projection' => [
        'test_method' => 'testInputProjectionBehavior',
        'provider' => 'inputProjectionProvider',
    ],
    'crud' => [
        'test_method' => 'testCrudBehavior',
        'provider' => 'crudProvider',
    ],
    'database-boundary' => [
        'test_method' => 'testDatabaseBoundaryBehavior',
        'provider' => 'databaseBoundaryProvider',
    ],
];

if (!is_string($phpunitBehaviorBridge)) {
    $failures[] = 'Cannot read the PHPUnit framework behavior bridge.';
} else {
    foreach ($expectedPhpunitGroupBridges as $group => $bridge) {
        $testMarker = sprintf(
            "#[Group('%s')]\n"
            . "    #[DataProvider('%s')]\n"
            . "    #[TestDox('\$_dataName')]\n"
            . "    public function %s(string \$name): void\n"
            . "    {\n"
            . "        \$this->runBehavior(\$name);\n"
            . '    }',
            $group,
            $bridge['provider'],
            $bridge['test_method'],
        );
        $providerMarker = sprintf(
            "public static function %s(): Generator\n"
            . "    {\n"
            . "        yield from self::groupedBehaviorProvider('%s');\n"
            . '    }',
            $bridge['provider'],
            $group,
        );

        if (
            !str_contains($phpunitBehaviorBridge, $testMarker)
            || !str_contains($phpunitBehaviorBridge, $providerMarker)
        ) {
            $failures[] = "PHPUnit must retain the complete test/provider bridge for group {$group}.";
        }
    }

    if (
        substr_count($phpunitBehaviorBridge, "#[Group('") !== 17
        || substr_count($phpunitBehaviorBridge, "#[DataProvider('") !== 16
        || substr_count($phpunitBehaviorBridge, 'yield from self::groupedBehaviorProvider(') !== 16
    ) {
        $failures[] = 'PHPUnit must expose exactly 16 behavior groups and one parity group.';
    }
}

$frameworkBehaviorRegistry = file_get_contents($root . '/tests/run.php');

if (
    is_string($frameworkBehaviorRegistry)
    && str_contains($frameworkBehaviorRegistry, 'fwrite(STDOUT, "PASS {$name}')
) {
    $failures[] = 'The removed custom framework test execution loop must not return.';
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

if (
    is_string($ciContents)
    && (
        substr_count($ciContents, 'coverage: pcov') !== 1
        || !str_contains($ciContents, 'run: composer test:coverage')
        || !str_contains($ciContents, 'uses: actions/upload-artifact@v4')
        || !str_contains($ciContents, '.phpunit.cache/junit.xml')
        || !str_contains($ciContents, '.phpunit.cache/coverage.xml')
        || !str_contains($ciContents, 'if-no-files-found: warn')
    )
) {
    $failures[] = 'CI must retain report-only PHPUnit coverage and machine-readable test artifacts.';
}

$consumerProfileArtifactMarkers = [
    '.ai/README.md' => [
        'Review the Alpha 2 consumer profile or a capability exit',
        '`.ai/consumer-profile.md`',
    ],
    '.ai/consumer-profile.md' => [
        'framework behavior lives only in `src/` and the Consumer Contract',
        'commit-visible job publication',
        'Do not add an ORM, repository, binding helper',
    ],
    'docs/consumer-profile.md' => [
        '`POST /accounts/{account_id:positive-int}/users`',
        'four complete raw SQL statements',
        'The checked-in HTTP composition remains deny-all.',
        'Framework and skeleton Composer metadata use `~8.4.0`',
    ],
    'docs/decisions/029-alpha-2-consumer-profile-rollup.md' => [
        'Status: accepted',
        '| #2 | bounded multiple typed routes, ADR 019 | `core` |',
        '| #3 | request policy, ADR 020 | `application pattern` |',
        '| #4 | typed input boundaries, ADR 021 | `application pattern` |',
        '| #5 | finite data paths, ADR 022 | `application pattern` |',
        '| #6 | terminal request summaries, ADR 023 | `application pattern` |',
        '| #7 | SQLite durable jobs, ADR 024 | `application pattern` |',
        '| #8 | explicit CLI and scheduler, ADR 025 | `application pattern` |',
        '| #9 | bounded file transfers, ADR 026 | `core` |',
        '| #10 | explicit SQLite migrations, ADR 027 | `application pattern` |',
        '| #11 | Redis cache and schedule lease, ADR 028 | `application pattern` |',
        'No capability has an overall `defer` exit.',
        'The supported PHP runtime is exactly the PHP 8.4.x Composer range `~8.4.0`.',
    ],
    'docs/decisions/README.md' => [
        '`029-alpha-2-consumer-profile-rollup.md`',
    ],
    'docs/evaluation.md' => [
        'The Alpha 2 rollup is recorded in `docs/consumer-profile.md` and ADR 029.',
    ],
    'docs/knowledge-map.md' => [
        'Assess the Alpha 2 consumer profile or a capability exit',
    ],
    'example/src/Users/UserRoutes.php' => [
        'new Route(\'POST\', \'/accounts/{account_id:positive-int}/users\', $createUserHandler)',
    ],
    'example/src/Users/CreateUser/CreateUserHandler.php' => [
        '$this->authenticate->authenticate($request)',
        '$this->resolveTenant->resolve($principal, $accountId)',
        '$this->authorize->authorizeCreate($principal, $tenant)',
        '$command = CreateUserCommand::fromJson($request->body);',
        '$this->createUser->execute($principal, $tenant, $accountId, $command);',
    ],
    'example/src/Users/CreateUser/TransactionalCreateUser.php' => [
        'four-statement transaction',
        'INSERT INTO users (name, email)',
        'INSERT INTO account_users (user_id, account_id)',
        'INSERT INTO user_events (user_id, event_type)',
        'INSERT INTO application_jobs (',
        '$this->connection->commit();',
    ],
    'tests/consumer-profile.php' => [
        'consumer profile composes policy typed input transaction job and correlation',
        'consumer profile denials and invalid input stop before protected SQL',
        'consumer profile job and budget failures roll back every scoped write',
        'consumer profile SQL rejects mismatched tenant and missing actor membership',
        'new QuerySummarySource(\'create_user\', $budget, $queryTrace)',
    ],
    'tests/run.php' => [
        "require __DIR__ . '/consumer-profile.php';",
        "frameworkBehaviorGroupDefinitions('consumer-profile', consumerProfileTests())",
    ],
    'composer.json' => [
        '"php": "~8.4.0"',
    ],
    'skeleton/composer.json' => [
        '"php": "~8.4.0"',
    ],
    '.github/workflows/ci.yml' => [
        "php: ['8.4']",
        'php-version: ${{ matrix.php }}',
    ],
    'tools/package-files.txt' => [
        'docs/consumer-profile.md',
        'docs/decisions/029-alpha-2-consumer-profile-rollup.md',
    ],
    'ROADMAP.md' => [
        'ADR 029 records every Alpha 2 capability exit',
    ],
];

foreach ($consumerProfileArtifactMarkers as $relativePath => $markers) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        $failures[] = "Cannot read consumer-profile artifact {$relativePath}.";
        continue;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $failures[] = "Consumer-profile artifact marker is missing from {$relativePath}: {$marker}.";
        }
    }
}

foreach (['composer.json', 'skeleton/composer.json'] as $phpManifestPath) {
    $manifestContents = file_get_contents($root . '/' . $phpManifestPath);
    $manifest = is_string($manifestContents) ? json_decode($manifestContents, true) : null;
    $requirements = is_array($manifest) ? ($manifest['require'] ?? null) : null;

    if (!is_array($requirements) || ($requirements['php'] ?? null) !== '~8.4.0') {
        $failures[] = "{$phpManifestPath} must support exactly PHP 8.4.x through ~8.4.0.";
    }

    foreach (['require', 'require-dev'] as $dependencySection) {
        $dependencies = is_array($manifest) ? ($manifest[$dependencySection] ?? null) : null;

        foreach (is_array($dependencies) ? array_keys($dependencies) : [] as $dependencyName) {
            if (is_string($dependencyName) && str_contains(strtolower($dependencyName), 'dotenv')) {
                $failures[] = "{$phpManifestPath} must not add a framework or skeleton dotenv dependency.";
            }
        }
    }
}

if (is_string($packageInventory)) {
    $packagePaths = preg_split('/\R/', $packageInventory);

    foreach (is_array($packagePaths) ? $packagePaths : [] as $packagePath) {
        if (frameworkMechanismPathIsForbidden($packagePath)) {
            $failures[] = "Permanent framework boundary forbids packaged runtime mechanism path: {$packagePath}.";
        }
    }
}

$fileTransferArtifactMarkers = [
    '.ai/README.md' => [
        'Add, change, or review file uploads or local-file responses',
        '`.ai/file-transfers.md`',
    ],
    '.ai/file-transfers.md' => [
        'A `null` multipart limit disables multipart input.',
        'Do not add a generic storage interface, facade, disk registry, binding helper',
        'Do not claim rejection of duplicate raw scalar parts',
        'After headers, do not attempt a replacement response',
        'Do not introduce an ORM',
    ],
    'docs/consumer-contract.md' => [
        '## Optional bounded file transfers',
        'Raw `$_FILES` never enters a handler.',
        'Contract version 10 carries contract version 9 forward and adopts Strict Profile version 3.',
    ],
    'docs/decisions/026-bounded-file-transfers.md' => [
        'Status: accepted',
        'Duplicate raw parts using the same scalar name collapse to one normalized entry',
        'The accepted implementation occupies 2,495 physical core lines',
        'PHPThis adds no ORM behavior, automatic or domain binding',
    ],
    'docs/file-transfers/README.md' => [
        'This knowledge set routes an AI through PHPThis\'s one accepted file-transfer path.',
        'The installed example uses a 2 MiB multipart transport ceiling and a separate 1 MiB document limit.',
    ],
    'example/.ai/file-transfers.md' => [
        '`POST /document-files`',
        '`GET /document-files/{file_id:token}`',
        'application.response_emission_failed',
    ],
    'skeleton/.ai/file-transfers.md' => [
        '`NOT_APPLICABLE(FILE_TRANSFER)`',
        'multipart input remains disabled',
    ],
    'templates/application/.ai/file-transfers.md' => [
        '{{FILE_TRANSFER_ADOPTION_OR_NOT_APPLICABLE}}',
        '{{FILE_TRANSFER_EVIDENCE_OR_NOT_APPLICABLE}}',
    ],
    'src/Http/RequestReader.php' => [
        'private ?int $maximumMultipartBytes;',
        'array $parsedFields = [],',
        'array $files = [],',
        'RequestUploadError::tryFrom',
    ],
    'src/Http/ResponseEmitter.php' => [
        'private const int FILE_CHUNK_BYTES = 8_192;',
        'if (headers_sent())',
        'throw new ResponseEmissionFailed(false);',
        'throw new ResponseEmissionFailed(true);',
    ],
    'example/src/DocumentFiles/LocalDocumentFiles.php' => [
        'move_uploaded_file($upload->temporaryPath, $destination)',
        'requirePrivateDirectory($this->directory)',
        "DIRECTORY_SEPARATOR . 'content'",
    ],
    'example/src/DocumentFiles/DownloadDocumentFileHandler.php' => [
        "'Accept-Ranges' => 'none'",
        "'Content-Disposition' => 'attachment; filename=\"document.bin\"'",
    ],
    'example/public/index.php' => [
        '$coordinator->handle($_SERVER, $_GET, $_POST, $_FILES)',
        "error_log('application.response_emission_failed')",
        'if (!$failure->responseStarted)',
    ],
    'skeleton/public/index.php' => [
        '$coordinator->handle($_SERVER, $_GET, $_POST, $_FILES)',
        "error_log('application.response_emission_failed')",
        'if (!$failure->responseStarted)',
    ],
    'tests/document-files.php' => [
        'real multipart upload and download remain bounded and metadata-blind',
        'large local file emission stays below a fixed memory delta',
        "'scalar-duplicate'",
        "'display_errors=1'",
    ],
    'tests/upload-request-boundary.php' => [
        'Expected multipart input to require an explicit configured cap.',
    ],
    'tools/package-files.txt' => [
        'docs/decisions/026-bounded-file-transfers.md',
        'docs/file-transfers/README.md',
        'src/Http/RequestUpload.php',
        'src/Http/LocalFileBody.php',
        'templates/application/.ai/file-transfers.md',
    ],
    'README.md' => [
        'The Alpha 2 core ceiling is 2,500 physical lines.',
        'the reviewed implementation occupies 2,495 lines',
    ],
];

foreach ($fileTransferArtifactMarkers as $relativePath => $markers) {
    $contents = file_get_contents($root . '/' . $relativePath);

    if (!is_string($contents)) {
        $failures[] = "Cannot read file-transfer artifact {$relativePath}.";
        continue;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $failures[] = "File-transfer artifact marker is missing from {$relativePath}.";
        }
    }
}

$consumerContractPath = $root . '/docs/consumer-contract.md';

if (is_file($consumerContractPath)) {
    $consumerContract = file_get_contents($consumerContractPath);

    if (!is_string($consumerContract)) {
        $failures[] = 'Cannot read docs/consumer-contract.md.';
    } else {
        if (preg_match('/^Contract version: 10$/m', $consumerContract) !== 1) {
            $failures[] = 'docs/consumer-contract.md must declare contract version 10.';
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
    } elseif (preg_match('/^Profile version: 3$/m', $strictProfile) !== 1) {
        $failures[] = 'docs/strict-profile.md must declare profile version 3.';
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

        if (!str_contains($applicationAgentInstructions, 'Consumer Contract v10 and Strict Profile v3 are the minimum accepted rules')) {
            $failures[] = 'Application AGENTS.md must identify Consumer Contract v10 and Strict Profile v3 as the minimum accepted rules.';
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
        || !str_contains($skeletonAgentInstructions, 'Consumer Contract v10 and Strict Profile v3 are the minimum accepted rules')
    ) {
        $failures[] = 'Skeleton AGENTS.md must preserve Alpha 5 Contract v10 authority, the installed knowledge route, AI authoring role, and human decision boundary.';
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
    if (frameworkMechanismPathIsForbidden($relativePath)) {
        $failures[] = "Permanent framework boundary forbids core runtime mechanism path: {$relativePath}.";
    }

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

    $previousSignificantTokenId = null;

    foreach (token_get_all($contents) as $token) {
        $tokenId = is_array($token) ? $token[0] : null;
        $isSignificant = !is_array($token)
            || !in_array($tokenId, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);

        if (
            $tokenId === T_EVAL
            && !in_array(
                $previousSignificantTokenId,
                [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION],
                true,
            )
        ) {
            $failures[] = "{$relativePath} uses eval.";
            break;
        }

        if ($isSignificant) {
            $previousSignificantTokenId = $tokenId;
        }
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

if ($coreLines > 2_600) {
    $failures[] = "Core source has {$coreLines} physical lines; the accepted UUID/ULID routing limit is 2600.";
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
