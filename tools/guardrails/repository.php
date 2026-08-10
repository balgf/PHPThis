<?php

declare(strict_types=1);

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

/**
 * @return list<string>
 */
function canonicalCrudTreeFailures(string $root): array
{
    $documentationPath = $root . '/docs/crud.md';
    $documentation = file_get_contents($documentationPath);

    if (!is_string($documentation)) {
        return ['Cannot read docs/crud.md for the canonical current CRUD tree comparison.'];
    }

    $matches = [];
    $matched = preg_match(
        '~For the checked-in `example/src/Users` reference, this is the single canonical current tree\..*?'
        . '```text\R(?<tree>.*?)\R```~s',
        $documentation,
        $matches,
    );
    $tree = $matches['tree'] ?? null;

    if ($matched !== 1 || !is_string($tree)) {
        return ['docs/crud.md must contain one parseable canonical current example/src/Users tree.'];
    }

    $treeLines = preg_split('/\R/', $tree);

    if (!is_array($treeLines)) {
        return ['Cannot split the canonical current CRUD tree in docs/crud.md.'];
    }

    /** @var array<int, string> $directoriesByDepth */
    $directoriesByDepth = [];
    /** @var list<string> $documentedFiles */
    $documentedFiles = [];

    foreach ($treeLines as $lineOffset => $treeLine) {
        if ($treeLine === '') {
            continue;
        }

        $lineMatches = [];

        if (preg_match('/^(?<indent> *)(?<entry>\S.*)$/D', $treeLine, $lineMatches) !== 1) {
            return [sprintf(
                'docs/crud.md canonical current tree line %d must use two-space indentation and one path entry.',
                $lineOffset + 1,
            )];
        }

        $indent = $lineMatches['indent'];
        $entry = $lineMatches['entry'];

        if (strlen($indent) % 2 !== 0) {
            return [sprintf(
                'docs/crud.md canonical current tree line %d must use two-space indentation and one path entry.',
                $lineOffset + 1,
            )];
        }

        $depth = intdiv(strlen($indent), 2);

        if (str_ends_with($entry, '/')) {
            $directory = substr($entry, 0, -1);

            if ($directory === '' || str_contains($directory, '/')) {
                return [sprintf(
                    'docs/crud.md canonical current tree line %d has an invalid directory entry.',
                    $lineOffset + 1,
                )];
            }

            foreach (array_keys($directoriesByDepth) as $knownDepth) {
                if ($knownDepth >= $depth) {
                    unset($directoriesByDepth[$knownDepth]);
                }
            }

            $directoriesByDepth[$depth] = $directory;
            continue;
        }

        if (!str_ends_with($entry, '.php') || str_contains($entry, '/')) {
            return [sprintf(
                'docs/crud.md canonical current tree line %d must name one PHP source file.',
                $lineOffset + 1,
            )];
        }

        $segments = [];

        for ($parentDepth = 0; $parentDepth < $depth; $parentDepth++) {
            if (!isset($directoriesByDepth[$parentDepth])) {
                return [sprintf(
                    'docs/crud.md canonical current tree line %d has no directory at depth %d.',
                    $lineOffset + 1,
                    $parentDepth,
                )];
            }

            $segments[] = $directoriesByDepth[$parentDepth];
        }

        $segments[] = $entry;
        $documentedFiles[] = implode('/', $segments);
    }

    if ($documentedFiles === [] || count(array_unique($documentedFiles)) !== count($documentedFiles)) {
        return ['docs/crud.md canonical current tree must contain a non-empty unique source-file list.'];
    }

    $actualRoot = $root . '/example/src/Users';

    if (!is_dir($actualRoot)) {
        return ['Cannot read example/src/Users for the canonical current CRUD tree comparison.'];
    }

    $examplePrefix = $root . '/example/';
    /** @var list<string> $actualFiles */
    $actualFiles = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($actualRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY,
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo) {
            return ['Cannot inspect one example/src/Users entry for the canonical current CRUD tree comparison.'];
        }

        if (!$file->isFile()) {
            continue;
        }

        $absolutePath = $file->getPathname();

        if (!str_starts_with($absolutePath, $examplePrefix)) {
            return ['Canonical CRUD source escaped the expected example directory.'];
        }

        $actualFiles[] = substr($absolutePath, strlen($examplePrefix));
    }

    sort($documentedFiles, SORT_STRING);
    sort($actualFiles, SORT_STRING);

    if ($documentedFiles === $actualFiles) {
        return [];
    }

    $undocumentedFiles = array_values(array_diff($actualFiles, $documentedFiles));
    $missingFiles = array_values(array_diff($documentedFiles, $actualFiles));
    $details = [];

    if ($undocumentedFiles !== []) {
        $details[] = 'undocumented current files: ' . implode(', ', $undocumentedFiles);
    }

    if ($missingFiles !== []) {
        $details[] = 'documented files absent from the example: ' . implode(', ', $missingFiles);
    }

    return ['docs/crud.md canonical current tree differs from example/src/Users; ' . implode('; ', $details) . '.'];
}

/**
 * @param array<string, list<string>> $artifactMarkers
 * @param non-empty-string $artifactLabel
 * @param list<string> $failures
 */
function requireGuardrailArtifactMarkers(
    string $root,
    array $artifactMarkers,
    string $artifactLabel,
    array &$failures,
): void {
    foreach ($artifactMarkers as $relativePath => $markers) {
        $path = $root . '/' . $relativePath;

        if (!is_file($path)) {
            $failures[] = "Required {$artifactLabel} artifact is not a regular file: {$relativePath}.";
            continue;
        }

        $contents = file_get_contents($path);

        if (!is_string($contents)) {
            $failures[] = "Cannot read {$artifactLabel} artifact {$relativePath}.";
            continue;
        }

        foreach ($markers as $marker) {
            if (!str_contains($contents, $marker)) {
                $failures[] = "{$artifactLabel} artifact {$relativePath} is missing marker: {$marker}";
            }
        }
    }
}

/**
 * @param array<string, list<string>> $artifactMarkers
 * @param non-empty-string $artifactLabel
 * @param list<string> $failures
 */
function forbidGuardrailArtifactMarkers(
    string $root,
    array $artifactMarkers,
    string $artifactLabel,
    array &$failures,
): void {
    foreach ($artifactMarkers as $relativePath => $markers) {
        $path = $root . '/' . $relativePath;

        if (!is_file($path)) {
            $failures[] = "Required {$artifactLabel} artifact is not a regular file: {$relativePath}.";
            continue;
        }

        $contents = file_get_contents($path);

        if (!is_string($contents)) {
            $failures[] = "Cannot read {$artifactLabel} artifact {$relativePath}.";
            continue;
        }

        foreach ($markers as $marker) {
            if (str_contains($contents, $marker)) {
                $failures[] = "{$artifactLabel} artifact {$relativePath} contains forbidden marker: {$marker}";
            }
        }
    }
}

/** @return list<string> */
function decisionSuccessorRelationshipFailures(string $root): array
{
    /** @var array<string, array{title: non-empty-string, metadata: non-empty-string, targets: non-empty-list<non-empty-string>}> $decisionHeaders */
    $decisionHeaders = [
        'docs/decisions/005-bounded-query-tracing.md' => [
            'title' => 'ADR 005: Bounded query tracing',
            'metadata' => "Superseded in part by [ADR 008](008-explicit-request-boundary.md), which replaces only this decision's temporary Phase 0 core-source ceiling.",
            'targets' => ['008-explicit-request-boundary.md'],
        ],
        'docs/decisions/008-explicit-request-boundary.md' => [
            'title' => 'ADR 008: Explicit request boundary and exact error responses',
            'metadata' => 'Superseded in part by [ADR 023](023-application-owned-terminal-request-summaries.md), which replaces only the separate unknown-failure log, and [ADR 026](026-bounded-file-transfers.md), which resolves only the upload and response-streaming reconsideration item.',
            'targets' => [
                '023-application-owned-terminal-request-summaries.md',
                '026-bounded-file-transfers.md',
            ],
        ],
        'docs/decisions/012-pdo-transport-application-owned-dialects.md' => [
            'title' => 'ADR 012: PDO transport with application-owned SQL dialects',
            'metadata' => 'Superseded in part by [ADR 023](023-application-owned-terminal-request-summaries.md), which replaces only the option to share one request-wide query budget across terminal-summary database sources.',
            'targets' => ['023-application-owned-terminal-request-summaries.md'],
        ],
        'docs/decisions/013-optional-crud-reference-profile.md' => [
            'title' => 'ADR 013: Optional CRUD reference profile',
            'metadata' => "Superseded in part by [ADR 021](021-application-owned-typed-input-boundaries.md), which replaces only the earlier Create tree and handler-owned transaction description.\n\nCurrent executable-example placement is refined by [ADR 046](046-canonical-executable-example-boundaries.md), which moves the shared `UserId` invariant to the feature level without changing this optional profile.",
            'targets' => [
                '021-application-owned-typed-input-boundaries.md',
                '046-canonical-executable-example-boundaries.md',
            ],
        ],
        'docs/decisions/017-bounded-trailing-positive-integer-routes.md' => [
            'title' => 'ADR 017: Bounded trailing positive-integer routes',
            'metadata' => "Superseded in part by [ADR 019](019-bounded-multiple-typed-routes.md), which retains this decision's positive-integer and explicit-routing constraints while replacing its one-trailing-parameter limit, prefix index, and one-value metadata.",
            'targets' => ['019-bounded-multiple-typed-routes.md'],
        ],
        'docs/decisions/019-bounded-multiple-typed-routes.md' => [
            'title' => 'ADR 019: Bounded multiple typed routes',
            'metadata' => "Superseded in part by [ADR 032](032-explicit-uuid-and-ulid-route-types.md), which retains this decision's parameter count, state index, opaque-token, conflict, and immutable-delivery constraints while extending the fixed parameter-type set with canonical UUID and ULID values.",
            'targets' => ['032-explicit-uuid-and-ulid-route-types.md'],
        ],
        'docs/decisions/020-application-owned-request-policy.md' => [
            'title' => 'ADR 020: Application-owned request policy composition',
            'metadata' => 'Superseded in part by [ADR 023](023-application-owned-terminal-request-summaries.md), which replaces only the denial and unknown-failure logging wording with one application-owned terminal summary attempt.',
            'targets' => ['023-application-owned-terminal-request-summaries.md'],
        ],
        'docs/decisions/021-application-owned-typed-input-boundaries.md' => [
            'title' => 'ADR 021: Application-owned typed input boundaries',
            'metadata' => 'Superseded in part by [ADR 042](042-application-owned-input-failure-classification.md), which replaces only the blanket-`400` authoring default for application-owned structured request-body content.',
            'targets' => ['042-application-owned-input-failure-classification.md'],
        ],
        'docs/decisions/025-application-owned-explicit-cli-and-scheduler.md' => [
            'title' => 'ADR 025: Application-owned explicit CLI and scheduler',
            'metadata' => "Superseded in part by [ADR 028](028-application-owned-redis-cache-and-schedule-lease.md), which replaces only the executable example's same-host schedule file lock with one application-owned Redis owner-token lease and extends `schedule:run` success and Redis-failure JSON with a bounded `coordination` list.",
            'targets' => ['028-application-owned-redis-cache-and-schedule-lease.md'],
        ],
    ];
    $failures = [];

    foreach ($decisionHeaders as $relativePath => $expected) {
        $path = $root . '/' . $relativePath;

        if (!is_file($path)) {
            $failures[] = "Partially superseded decision is not a regular file: {$relativePath}.";
            continue;
        }

        $contents = file_get_contents($path);

        if (!is_string($contents)) {
            $failures[] = "Cannot read partially superseded decision {$relativePath}.";
            continue;
        }

        $expectedPrefix = "# {$expected['title']}\n\nStatus: accepted\n\n{$expected['metadata']}\n\n## Context\n";

        if (!str_starts_with($contents, $expectedPrefix)) {
            $failures[] = "Partially superseded decision {$relativePath} must expose its exact successor metadata directly after accepted status.";
        }

        foreach ($expected['targets'] as $targetPath) {
            if (!is_file($root . '/docs/decisions/' . $targetPath)) {
                $failures[] = "Partially superseded decision {$relativePath} has no regular-file relationship target {$targetPath}.";
            }
        }
    }

    $indexPath = $root . '/docs/decisions/README.md';

    if (!is_file($indexPath)) {
        $failures[] = 'The decision successor index is not a regular file.';
        return $failures;
    }

    $indexContents = file_get_contents($indexPath);

    if (!is_string($indexContents)) {
        $failures[] = 'Cannot read the decision index for successor relationships.';
        return $failures;
    }

    $lines = preg_split('/\R/', $indexContents);

    if (!is_array($lines)) {
        $failures[] = 'Cannot parse the decision index for successor relationships.';
        return $failures;
    }

    $heading = '## Current and successor relationships';
    $headingIndex = null;
    $headingCount = 0;

    foreach ($lines as $index => $line) {
        if ($line === $heading) {
            $headingIndex = $index;
            $headingCount++;
        }
    }

    if ($headingIndex === null || $headingCount !== 1) {
        $failures[] = 'The decision index must contain one current-and-successor relationship section.';
        return $failures;
    }

    $expectedIntroduction = 'A partially superseded record remains accepted outside the exact scope named below. Follow the direct successor for that scope; use current operational guides for ordinary implementation rather than rewriting historical decision bodies.';

    if (($lines[$headingIndex + 2] ?? null) !== $expectedIntroduction) {
        $failures[] = 'The decision index must retain the bounded partially-superseded relationship explanation.';
    }

    $expectedRows = [
        '| Accepted record | Scope superseded in part | Direct successor |',
        '| --- | --- | --- |',
        '| [ADR 005](005-bounded-query-tracing.md) | Temporary Phase 0 core-source ceiling | [ADR 008](008-explicit-request-boundary.md) |',
        '| [ADR 008](008-explicit-request-boundary.md) | Separate unknown-failure log | [ADR 023](023-application-owned-terminal-request-summaries.md) |',
        '| [ADR 008](008-explicit-request-boundary.md) | Upload and response-streaming reconsideration item | [ADR 026](026-bounded-file-transfers.md) |',
        '| [ADR 012](012-pdo-transport-application-owned-dialects.md) | Shared request-wide query-budget option for terminal-summary database sources | [ADR 023](023-application-owned-terminal-request-summaries.md) |',
        '| [ADR 013](013-optional-crud-reference-profile.md) | Earlier Create tree and handler-owned transaction description | [ADR 021](021-application-owned-typed-input-boundaries.md) |',
        '| [ADR 017](017-bounded-trailing-positive-integer-routes.md) | One-trailing-parameter limit, prefix index, and one-value route metadata | [ADR 019](019-bounded-multiple-typed-routes.md) |',
        '| [ADR 019](019-bounded-multiple-typed-routes.md) | Fixed parameter-type set before UUID and ULID | [ADR 032](032-explicit-uuid-and-ulid-route-types.md) |',
        '| [ADR 020](020-application-owned-request-policy.md) | Denial and unknown-failure logging wording | [ADR 023](023-application-owned-terminal-request-summaries.md) |',
        '| [ADR 021](021-application-owned-typed-input-boundaries.md) | Blanket-`400` authoring default for structured request-body content | [ADR 042](042-application-owned-input-failure-classification.md) |',
        '| [ADR 025](025-application-owned-explicit-cli-and-scheduler.md) | Executable example\'s same-host schedule file lock and `schedule:run` coordination output | [ADR 028](028-application-owned-redis-cache-and-schedule-lease.md) |',
    ];
    $actualRows = [];
    $tableEndIndex = null;

    for ($index = $headingIndex + 1, $count = count($lines); $index < $count; $index++) {
        if (!str_starts_with($lines[$index], '|')) {
            if ($actualRows !== []) {
                $tableEndIndex = $index;
                break;
            }

            continue;
        }

        $actualRows[] = $lines[$index];
    }

    if ($actualRows !== $expectedRows) {
        $failures[] = 'The decision index successor table must preserve the complete explicit relationship list and order.';
    }

    $expectedRefinement = "ADR 013's current executable-example identifier placement is additionally refined by [ADR 046](046-canonical-executable-example-boundaries.md); the canonical current tree remains in [Optional CRUD reference profile](../crud.md#reference-placement). This refinement does not additionally supersede ADR 013's optional structure decision.";

    if ($tableEndIndex === null || ($lines[$tableEndIndex + 1] ?? null) !== $expectedRefinement) {
        $failures[] = 'The decision index must retain ADR 013\'s separate current executable-example refinement pointer.';
    }

    return $failures;
}

/** @return list<string> */
function canonicalVocabularyFailures(string $root): array
{
    $path = $root . '/docs/vocabulary.md';

    if (!is_file($path)) {
        return ['docs/vocabulary.md is not a regular file for the canonical concept rows.'];
    }

    $contents = file_get_contents($path);

    if (!is_string($contents)) {
        return ['Cannot read docs/vocabulary.md for the canonical concept rows.'];
    }

    /** @var array<string, array{meaning: non-empty-string, aliases: non-empty-string}> $expectedRows */
    $expectedRows = [
        'typed operation seam' => [
            'meaning' => 'optional application-owned, narrowly typed interface, at most one in a request path, separating completed inbound transport or HTTP adaptation from one independently meaningful business or transaction responsibility while outbound response adaptation remains in the handler; omitted when behavior remains coherent in the handler',
            'aliases' => 'service layer, repository, command bus, use-case interface required for every handler',
        ],
        'application-owned representation primitive' => [
            'meaning' => 'optional narrowly named application value used through composition by distinct concrete domain identifiers that deliberately share one complete validation invariant and canonical scalar representation; operations continue to require concrete identifiers and generation stays separate',
            'aliases' => 'framework identifier, generic domain ID, base class, trait, generator, binding or persistence abstraction',
        ],
        'UUID policy' => [
            'meaning' => 'application-owned recorded separation of accepted canonical UUID versions from generation version and owner, metadata disclosure, ordering and clock behavior, failure, narrower domain rules, persistence, and evidence',
            'aliases' => 'route grammar, framework UUID generator, package selection, database default, persistence abstraction',
        ],
    ];
    /** @var array<string, list<array{meaning: string, aliases: string}>> $rowsByTerm */
    $rowsByTerm = [];
    $lines = preg_split('/\R/', $contents);

    if (!is_array($lines)) {
        return ['Cannot parse docs/vocabulary.md for the canonical concept rows.'];
    }

    foreach ($lines as $line) {
        if (!str_starts_with($line, '| ')) {
            continue;
        }

        $columns = explode(' | ', trim($line, '| '));

        if (count($columns) !== 3 || !array_key_exists($columns[0], $expectedRows)) {
            continue;
        }

        $rowsByTerm[$columns[0]][] = [
            'meaning' => $columns[1],
            'aliases' => $columns[2],
        ];
    }

    $failures = [];

    foreach ($expectedRows as $term => $expectedRow) {
        $actualRows = $rowsByTerm[$term] ?? [];

        if ($actualRows !== [$expectedRow]) {
            $failures[] = "Canonical vocabulary term {$term} must have one exact reviewed meaning and alias boundary.";
        }
    }

    return $failures;
}

/** @return list<string> */
function databaseCertificationAgreementFailures(string $root): array
{
    /** @var array<string, array{provision: non-empty-string, label: non-empty-string, version: non-empty-string, environment: non-empty-string, query: non-empty-string, service: ?non-empty-string}> $drivers */
    $drivers = [
        'sqlite' => [
            'provision' => 'PHP 8.4 `pdo_sqlite` on the `ubuntu-24.04` runner',
            'label' => 'SQLite',
            'version' => '3.45.1',
            'environment' => 'PHPTHIS_SQLITE_EXPECTED_VERSION',
            'query' => "'sqlite' => \$connection->selectOneRow('SELECT sqlite_version() AS engine_version')",
            'service' => null,
        ],
        'mysql' => [
            'provision' => 'Official `mysql:8.4` service',
            'label' => 'MySQL',
            'version' => '8.4.11',
            'environment' => 'PHPTHIS_MYSQL_EXPECTED_VERSION',
            'query' => "'mysql' => \$connection->selectOneRow('SELECT VERSION() AS engine_version')",
            'service' => 'image: mysql:8.4',
        ],
        'pgsql' => [
            'provision' => 'Official `postgres:17` service',
            'label' => 'PostgreSQL',
            'version' => '17.10',
            'environment' => 'PHPTHIS_PGSQL_EXPECTED_VERSION',
            'query' => '"SELECT split_part(current_setting(\'server_version\'), \' \', 1) AS engine_version"',
            'service' => 'image: postgres:17',
        ],
    ];
    $documentationPath = $root . '/docs/database.md';
    $workflowPath = $root . '/.github/workflows/ci.yml';
    $harnessPath = $root . '/tools/test-database-drivers.php';

    if (!is_file($documentationPath) || !is_file($workflowPath) || !is_file($harnessPath)) {
        return ['The database certification matrix, workflow, and harness must all be regular files.'];
    }

    $documentation = file_get_contents($documentationPath);
    $workflow = file_get_contents($workflowPath);
    $harness = file_get_contents($harnessPath);

    if (!is_string($documentation) || !is_string($workflow) || !is_string($harness)) {
        return ['Cannot read the database certification matrix, workflow, and harness agreement artifacts.'];
    }

    $expectedRows = [
        '| PDO driver | CI provision | Required exact engine or server version |',
        '| --- | --- | --- |',
    ];

    foreach ($drivers as $driver => $contract) {
        $expectedRows[] = sprintf(
            '| `%s` | %s | %s `%s` |',
            $driver,
            $contract['provision'],
            $contract['label'],
            $contract['version'],
        );
    }

    $documentationLines = preg_split('/\R/', $documentation);

    if (!is_array($documentationLines)) {
        return ['Cannot parse the database certification matrix.'];
    }

    $matrixHeadingIndex = null;

    foreach ($documentationLines as $index => $line) {
        if ($line === '### PDO transport certification matrix') {
            if ($matrixHeadingIndex !== null) {
                return ['The database guide must contain one PDO transport certification matrix.'];
            }

            $matrixHeadingIndex = $index;
        }
    }

    $actualRows = [];

    if ($matrixHeadingIndex !== null) {
        for ($index = $matrixHeadingIndex + 1, $count = count($documentationLines); $index < $count; $index++) {
            if (!str_starts_with($documentationLines[$index], '|')) {
                if ($actualRows !== []) {
                    break;
                }

                continue;
            }

            $actualRows[] = $documentationLines[$index];
        }
    }

    $failures = [];

    if ($actualRows !== $expectedRows) {
        $failures[] = 'The database guide must retain one exact ordered PDO transport certification matrix.';
    }

    if (!str_contains(
        $documentation,
        'No unlisted patch, minor, major, distribution build, extension build, service topology, or managed offering inherits certification from a listed row.',
    )) {
        $failures[] = 'The database certification matrix must retain its exact unlisted-version limitation.';
    }

    if (!str_contains(
        $documentation,
        'Its maintained SQLite negative control first supplies an impossible expected version, requires the exact bounded mismatch diagnostic and removal of the pre-DDL fixture, then proves clean recovery through the normal certification run.',
    )) {
        $failures[] = 'The database certification matrix must retain its SQLite mismatch-and-recovery evidence boundary.';
    }

    $expectedJobName = sprintf(
        'name: PDO transport (%s %s, %s %s, %s %s)',
        $drivers['sqlite']['label'],
        $drivers['sqlite']['version'],
        $drivers['mysql']['label'],
        $drivers['mysql']['version'],
        $drivers['pgsql']['label'],
        $drivers['pgsql']['version'],
    );

    if (substr_count($workflow, $expectedJobName) !== 1) {
        $failures[] = 'The database certification workflow name must agree with every documented exact version.';
    }

    /** @var array<string, string> $workflowVersions */
    $workflowVersions = [];
    $workflowMatches = [];
    preg_match_all(
        "/^\\s+(PHPTHIS_(?:SQLITE|MYSQL|PGSQL)_EXPECTED_VERSION): '([^']+)'$/m",
        $workflow,
        $workflowMatches,
        PREG_SET_ORDER,
    );

    foreach ($workflowMatches as $workflowMatch) {
        $workflowVersions[$workflowMatch[1]] = $workflowMatch[2];
    }

    if (count($workflowMatches) !== count($drivers)) {
        $failures[] = 'The database certification workflow must define each reviewed expected-version input exactly once.';
    }

    /** @var array<string, string> $expectedWorkflowVersions */
    $expectedWorkflowVersions = [];

    foreach ($drivers as $driver => $contract) {
        $expectedWorkflowVersions[$contract['environment']] = $contract['version'];

        if ($contract['service'] !== null && substr_count($workflow, $contract['service']) !== 1) {
            $failures[] = "The database certification workflow is missing the reviewed service selector {$contract['service']}.";
        }

        if (substr_count($harness, $contract['query']) !== 1) {
            $failures[] = "The database certification harness must contain one reviewed {$contract['label']} version query.";
        }

        $harnessEnvironmentMapping = sprintf("'%s' => '%s'", $driver, $contract['environment']);

        if (substr_count($harness, $harnessEnvironmentMapping) !== 1) {
            $failures[] = "The database certification harness must contain one reviewed {$contract['label']} expected-version input.";
        }
    }

    if ($workflowVersions !== $expectedWorkflowVersions) {
        $failures[] = 'The database certification workflow expected-version inputs must exactly match the documented matrix.';
    }

    $versionProbePosition = strpos($harness, '$version = databaseDriverVersion($driver, $configuration);');
    $expectedVersionPosition = strpos(
        $harness,
        '$expectedVersion = $expectedVersionOverride ?? expectedDatabaseVersion($driver);',
    );
    $versionMismatchPosition = strpos(
        $harness,
        'if ($expectedVersion !== null && $version !== $expectedVersion) {',
    );
    $versionMismatchFailurePosition = strpos($harness, 'Configured %s certification version mismatch');
    $fixtureDdlPosition = strpos($harness, 'CREATE TABLE {$table}');
    $negativeControlCallPosition = strpos(
        $harness,
        'proveSqliteVersionMismatchBeforeFixtureDdl($configuration);',
    );
    $normalCertificationPosition = strpos(
        $harness,
        'certifyDatabaseDriver($driver, $configuration),',
    );
    $orderedVersionMarkers = [
        '$version = databaseDriverVersion($driver, $configuration);',
        '$expectedVersion = $expectedVersionOverride ?? expectedDatabaseVersion($driver);',
        'if ($expectedVersion !== null && $version !== $expectedVersion) {',
        'Configured %s certification version mismatch',
        'CREATE TABLE {$table}',
    ];
    $orderedVersionMarkersAreUnique = true;

    foreach ($orderedVersionMarkers as $marker) {
        if (substr_count($harness, $marker) !== 1) {
            $orderedVersionMarkersAreUnique = false;
        }
    }

    if (
        !$orderedVersionMarkersAreUnique
        || $versionProbePosition === false
        || $expectedVersionPosition === false
        || $versionMismatchPosition === false
        || $versionMismatchFailurePosition === false
        || $fixtureDdlPosition === false
        || $negativeControlCallPosition === false
        || $normalCertificationPosition === false
        || $negativeControlCallPosition >= $normalCertificationPosition
        || !(
            $versionProbePosition < $expectedVersionPosition
            && $expectedVersionPosition < $versionMismatchPosition
            && $versionMismatchPosition < $versionMismatchFailurePosition
            && $versionMismatchFailurePosition < $fixtureDdlPosition
        )
        || !str_contains($harness, 'function databaseDriverVersion(string $driver, array $configuration): string')
        || !str_contains($harness, 'function expectedDatabaseVersion(string $driver): ?string')
        || !str_contains(
            $harness,
            'function proveSqliteVersionMismatchBeforeFixtureDdl(array $configuration): void',
        )
        || !str_contains($harness, "certifyDatabaseDriver('sqlite', \$configuration, '0.0');")
        || !str_contains(
            $harness,
            'SQLite version mismatch did not fail with the exact bounded diagnostic.',
        )
        || !str_contains($harness, 'SQLite version mismatch left its pre-DDL fixture behind.')
        || !str_contains($harness, 'SQLite mismatch and recovery control passed')
    ) {
        $failures[] = 'The database certification harness must retain its ordered exact-version and SQLite mismatch-and-recovery proofs before fixture DDL.';
    }

    return $failures;
}

/** @return list<string> */
function legacyPositiveIntegerRouteConvenienceFailures(string $root): array
{
    $sourcePath = $root . '/src/Routing/PathParameters.php';
    $upgradeGuidancePath = $root . '/docs/getting-started.md';

    if (!is_file($sourcePath) || !is_file($upgradeGuidancePath)) {
        return ['The PathParameters source and upgrade guidance must both be regular files.'];
    }

    $source = file_get_contents($sourcePath);
    $upgradeGuidance = file_get_contents($upgradeGuidancePath);

    if (!is_string($source) || !is_string($upgradeGuidance)) {
        return ['Cannot read the PathParameters source and upgrade guidance for the compatibility decision.'];
    }

    $failures = [];
    $maintainerCallSitePaths = [
        'tests/crud.php',
        'tests/input-projection.php',
        'tests/routing.php',
        'tests/upload-request-boundary.php',
    ];
    $requiredCallSiteMarkers = [];
    $forbiddenCallSiteMarkers = [];

    foreach ($maintainerCallSitePaths as $relativePath) {
        $requiredCallSiteMarkers[$relativePath] = ['PathParameters::fromValues('];
        $forbiddenCallSiteMarkers[$relativePath] = ['PathParameters::onePositiveInteger('];
    }

    requireGuardrailArtifactMarkers(
        $root,
        $requiredCallSiteMarkers,
        'PathParameters routing-compatibility call-site',
        $failures,
    );
    forbidGuardrailArtifactMarkers(
        $root,
        $forbiddenCallSiteMarkers,
        'PathParameters routing-compatibility call-site',
        $failures,
    );

    if (!str_contains($source, 'public static function fromValues(')) {
        $failures[] = 'PathParameters must retain its canonical explicit fromValues factory.';
    }

    if (str_contains($source, 'onePositiveInteger')) {
        $failures[] = 'The removed onePositiveInteger compatibility convenience must not remain in PathParameters.';
    }

    $expectedUpgradeGuidance = 'Alpha 6 removes the public-prerelease convenience factory `PathParameters::onePositiveInteger($name, $value)`. Any consumer upgrading from Alpha 5 or an earlier PHPThis revision or package must replace each call with `PathParameters::fromValues([$name => $value], [])`; an unchanged old call fails because the method no longer exists. This is a deliberate prerelease compatibility break in factory shape only; route matching, accepted positive-integer grammar, immutable delivery, and the `positiveInteger()` accessor remain unchanged. Reconcile framework guidance deliberately: the application\'s `AGENTS.md`, `.ai/` context, source layout, and decisions remain project-owned and are never overwritten or relocated by an upgrade. The source repository\'s `skeleton/` directory retains a VCS constraint and `repositories` override only as a source-evaluation bootstrap, so record the evaluated Git commit and commit the generated application lockfile.';

    if (substr_count($upgradeGuidance, $expectedUpgradeGuidance) !== 1) {
        $failures[] = 'The getting-started guide must retain the exact PathParameters source-consumer upgrade instruction.';
    }

    return $failures;
}


/** @return list<string> */
function repositoryGuardrailFailures(string $root): array
{
    $failures = [];

    foreach (decisionSuccessorRelationshipFailures($root) as $relationshipFailure) {
        $failures[] = $relationshipFailure;
    }

    foreach (canonicalVocabularyFailures($root) as $vocabularyFailure) {
        $failures[] = $vocabularyFailure;
    }

    foreach (databaseCertificationAgreementFailures($root) as $databaseCertificationFailure) {
        $failures[] = $databaseCertificationFailure;
    }

    foreach (legacyPositiveIntegerRouteConvenienceFailures($root) as $routeCompatibilityFailure) {
        $failures[] = $routeCompatibilityFailure;
    }

    $proofMaintainabilityArtifactMarkers = [
        'docs/guardrails.md' => [
            'Repeated documentation-marker checks use explicit shared repository-module helpers rather than duplicated loops',
            'The decision-navigation and vocabulary guard uses one fixed reviewed map of partial-supersession relationships.',
            'the `eval(...)` language construct and variable variables are absent, while legal declarations, aliases, accesses, and named arguments whose identifier is `eval` remain accepted',
        ],
        'docs/consumer-contract.md' => [
            "Read the application's `.ai/rules.md`, `.ai/change-workflow.md`, and `.ai/project.md`.",
            'Start with the one current operational guide selected by `.ai/README.md`.',
            "ADR 028 replaces only the executable example's schedule file lock with one application-owned Redis owner-token lease and extends successful and Redis-failure `schedule:run` output with one bounded `coordination` list.",
        ],
        'docs/cli.md' => [
            "ADR 028 replaces only the example's same-host schedule file lock with one Redis-specific owner-token lease and extends successful and Redis-failure `schedule:run` output with one bounded `coordination` list.",
        ],
        'docs/consumer-profile.md' => [
            'the exact maintained matrix: SQLite `3.45.1`, MySQL `8.4.11`, and PostgreSQL `17.10`',
            'no unlisted engine version inherits certification',
        ],
        'docs/knowledge-map.md' => [
            'application response headers, `.ai/architecture.md`, `.ai/data.md`, `.ai/integrations.md`, `.ai/operations.md`, `.ai/testing.md`',
        ],
        'ROADMAP.md' => [
            'the final post-Issue-35 correctness sweep restricts the forbidden `eval` check to the actual language construct while accepting legal same-named identifiers',
            'It changes no framework core, Consumer Contract version 11, Strict Profile version 3, runtime dependency, or consumer API.',
        ],
        'verification/SyntaxProfile.php' => [
            'private static function isEvalLanguageConstruct(array $tokens, int $index): bool',
            "return !self::isEvalMethodIdentifier(\$tokens, \$index);",
        ],
        'tools/test-strict-profile.php' => [
            'final class EvalConstants',
            'enum EvalCases: string',
            'original as eval;',
            "#[EvalNamedAttribute(eval: 'attribute')]",
            "acceptNamedEval(eval: 'function')",
        ],
        'tools/test-consumer-project.php' => [
            'proveInstalledReferenceClarityDistribution($installedFramework);',
            'function proveInstalledReferenceClarityDistribution(string $installedFramework): void',
            'PASS installed historical and reference clarity distribution',
            'proveEvalIdentifiersAreAllowedAndLanguageConstructIsRejected($project, $profileCommand, $environment);',
            'function proveEvalIdentifiersAreAllowedAndLanguageConstructIsRejected(',
            'final class EvalConstantControl',
            'enum EvalCaseControl: string',
            'original as eval;',
            "#[EvalNamedAttributeControl(eval: 'attribute')]",
            "'parent' => 'vendor/../composer.json'",
            "'empty' => 'vendor//phpthis/framework/docs/jobs.md'",
            "'dot' => 'vendor/./phpthis/framework/docs/jobs.md'",
            '`vendor/outside-vendor-reference-control`',
            "symlink(\$project . '/composer.json', \$symlinkReference)",
            'escapes the configured Composer vendor directory',
            'does not resolve through the configured Composer vendor directory',
        ],
    ];

    requireGuardrailArtifactMarkers(
        $root,
        $proofMaintainabilityArtifactMarkers,
        'proof maintainability',
        $failures,
    );

    $guardrailModuleArtifactMarkers = [
        'tools/guardrails.php' => [
            "require_once __DIR__ . '/guardrails/repository.php';",
            "require_once __DIR__ . '/guardrails/context.php';",
            "require_once __DIR__ . '/guardrails/boundaries.php';",
            "require_once __DIR__ . '/guardrails/operations.php';",
            "require_once __DIR__ . '/guardrails/distribution.php';",
            'repositoryGuardrailFailures($root)',
            'contextGuardrailFailures($root)',
            'boundaryGuardrailFailures($root)',
            'operationGuardrailFailures($root)',
            'distributionGuardrailFailures($root, $markdownCount, $phpCount, $coreLines)',
            '"PASS guardrails: %d Markdown files, %d PHP files, %d core lines\n"',
        ],
        'tools/guardrails/repository.php' => [
            'function repositoryGuardrailFailures(string $root): array',
            'function requireGuardrailArtifactMarkers(',
            'function forbidGuardrailArtifactMarkers(',
        ],
        'tools/guardrails/context.php' => [
            'function contextGuardrailFailures(string $root): array',
            'function mutableReleaseStateClaim(string $contents, array $markers): ?string',
        ],
        'tools/guardrails/boundaries.php' => [
            'function boundaryGuardrailFailures(string $root): array',
        ],
        'tools/guardrails/operations.php' => [
            'function operationGuardrailFailures(string $root): array',
            'function workbenchRuntimePathIsForbidden(string $relativePath): bool',
        ],
        'tools/guardrails/distribution.php' => [
            'function distributionGuardrailFailures(',
            "['tools/guardrails/distribution.php', 'verification/ApplicationChecker.php']",
            '$nativeSessionFunctions = [',
        ],
        'docs/guardrails.md' => [
            'The entrypoint explicitly loads five concern modules in one fixed order: repository, context, boundaries, operations, and distribution.',
            'There is no module discovery, rule registry, container, configuration language, shared content cache, or package/runtime integration.',
        ],
        '.ai/testing.md' => [
            '`tools/guardrails.php` is likewise a small explicit ordered entrypoint over exactly five concern modules under `tools/guardrails/`',
            'Do not add discovery, a rule registry, a container, a configuration language, or a shared content cache merely to organize this maintainer command.',
        ],
        'ROADMAP.md' => [
            'Issue 36 keeps `php tools/guardrails.php` as one deterministic command while moving its checks into five explicit concern modules',
        ],
    ];

    requireGuardrailArtifactMarkers(
        $root,
        $guardrailModuleArtifactMarkers,
        'modular repository guardrail',
        $failures,
    );

    forbidGuardrailArtifactMarkers(
        $root,
        [
            'tools/guardrails/distribution.php' => [
                "str_starts_with(\$relativePath, 'tools/guardrails/')",
            ],
        ],
        'native-session guardrail module exemption',
        $failures,
    );

    $consumerEvidenceOrganizationMarkers = [
        'docs/consumer-contract.md' => [
            'When an application-owned test or validation entrypoint spans unrelated concerns or becomes difficult to review, prefer a small deterministic entrypoint',
            'Preserve deterministic execution and failure behavior, and keep focused evidence directly runnable where the selected tool allows.',
            'Modularize only application-owned code; do not copy, replace, or modularize the installed `vendor/bin/phpthis check` entrypoint.',
            'This is advisory organization guidance, not a validity rule: PHPThis sets no line-count threshold',
            'its documented complete project check remains the authoritative gate.',
        ],
        'templates/application/.ai/testing.md' => [
            'When an application-owned test or validation entrypoint spans unrelated concerns or becomes difficult to review, prefer a small deterministic entrypoint',
            'cohesive concern-owned modules in an explicit order, with narrowly shared support',
            'do not copy, replace, or modularize the installed `vendor/bin/phpthis check` entrypoint',
            'adds no checker rule',
        ],
        'skeleton/.ai/testing.md' => [
            'When an application-owned test or validation entrypoint spans unrelated concerns or becomes difficult to review, prefer a small deterministic entrypoint',
            'cohesive concern-owned modules in an explicit order, with narrowly shared support',
            'do not copy, replace, or modularize the installed `vendor/bin/phpthis check` entrypoint',
            'adds no checker rule',
        ],
        'tools/test-consumer-project.php' => [
            'proveInstalledTestRunnerModularizationGuidanceDistribution($project, $installedFramework);',
            'function proveInstalledTestRunnerModularizationGuidanceDistribution(',
            'PASS installed test-runner modularization guidance distribution',
        ],
    ];

    requireGuardrailArtifactMarkers(
        $root,
        $consumerEvidenceOrganizationMarkers,
        'consumer test-runner modularization guidance',
        $failures,
    );

    forbidGuardrailArtifactMarkers(
        $root,
        ['docs/knowledge-map.md' => ['.ai/cache.md']],
        'current application context route',
        $failures,
    );

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
        'docs/date-time.md',
        'docs/email.md',
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
        'docs/frontend-integration.md',
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
        'docs/releases/0.1.0-alpha.6.md',
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
        'docs/decisions/043-engine-specific-application-migration-invariants.md',
        'docs/decisions/044-bounded-task-routed-ai-context.md',
        'docs/decisions/045-bounded-session-cleanup-and-response-framing.md',
        'docs/decisions/046-canonical-executable-example-boundaries.md',
        'docs/decisions/047-bounded-alpha-6-release-scope.md',
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
        'example/src/Users/UserId.php',
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
        'src/Session/SessionCleanupFailed.php',
        'src/Session/SessionConfiguration.php',
        'src/Session/SessionLifecycle.php',
        'src/Session/SessionSnapshot.php',
        'src/Session/SessionUnavailable.php',
        'tests/FrameworkBehaviorTest.php',
        'tests/behavior-names.txt',
        'tests/composition.php',
        'tests/create-user-support.php',
        'tests/crud.php',
        'tests/database-boundary.php',
        'tests/document-files.php',
        'tests/http-boundary.php',
        'tests/input-projection.php',
        'tests/large-file-emitter.php',
        'tests/observability.php',
        'tests/process-support.php',
        'tests/request-reader-support.php',
        'tests/response-framing.php',
        'tests/response-emitter.php',
        'tests/routing.php',
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
        'tests/fixtures/session-cleanup-failures.php.fixture',
        'tools/package-files.txt',
        'tools/guardrails/repository.php',
        'tools/guardrails/context.php',
        'tools/guardrails/boundaries.php',
        'tools/guardrails/operations.php',
        'tools/guardrails/distribution.php',
        'tools/agent-evaluation.php',
        'tools/agent-evaluation/README.md',
        'tools/agent-evaluation/support.php',
        'tools/agent-evaluation/tasks.php',
        'tools/agent-evaluation/run.php',
        'tools/agent-evaluation/score.php',
        'tools/agent-evaluation/schema/run.schema.json',
        'tools/agent-evaluation/schema/score.schema.json',
        'tools/agent-evaluation/schema/task.schema.json',
        'tools/agent-evaluation/tasks.json',
        'tools/agent-evaluation/tasks/change.simple-ping/prompt.md',
        'tools/agent-evaluation/tasks/change.simple-ping/public/holdout.php.fixture',
        'tools/agent-evaluation/tasks/change.simple-ping/rubric.md',
        'tools/agent-evaluation/tasks/change.simple-ping/task.json',
        'tools/test-agent-evaluation.php',
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

    $agentEvaluationArtifactMarkers = [
        '.ai/README.md' => [
            'Change the maintainer-only agent evaluation kit',
            'do not add a model-provider integration or execute untrusted candidate code without a separately accepted sandbox boundary',
        ],
        '.ai/testing.md' => [
            'The maintainer-only PHPThis Agent Evaluation Kit lives under `tools/agent-evaluation/`',
            '`tools/agent-evaluation.php` is its small explicit ordered entrypoint over exactly four cohesive modules',
            'The repository does not call a model provider or execute AI-authored candidate code as part of `composer check`.',
        ],
        'ROADMAP.md' => [
            'Agent Evaluation Kit v0.1 data contract',
            'official evaluation still requires an external post-generation holdout and repeated isolated trials',
        ],
        'composer.json' => [
            '"test:agent-evaluation": "php tools/test-agent-evaluation.php"',
            '"@test:agent-evaluation"',
        ],
        'docs/evaluation.md' => [
            '## Agent Evaluation Kit v0.1',
            '`AGENT_EVALUATION_PUBLIC_SMOKE_ONLY`',
            '`AGENT_EVALUATION_EXTERNAL_HOLDOUT_AFTER_GENERATION`',
            'at least ten trials per condition before reporting a rate',
        ],
        'docs/guardrails.md' => [
            'The Agent Evaluation Kit guard',
            'does not execute AI-authored candidate code',
        ],
        'tools/agent-evaluation.php' => [
            "require_once __DIR__ . '/agent-evaluation/support.php';",
            "require_once __DIR__ . '/agent-evaluation/tasks.php';",
            "require_once __DIR__ . '/agent-evaluation/run.php';",
            "require_once __DIR__ . '/agent-evaluation/score.php';",
            'validate-run <task-id> <run.json>',
            'validate-score <task-id> <run.json> <score.json>',
        ],
        'tools/agent-evaluation/README.md' => [
            '`AGENT_EVALUATION_SCHEMA_VERSION(1)`',
            '`AGENT_EVALUATION_TASK(change.simple-ping)`',
            '`AGENT_EVALUATION_PUBLIC_SMOKE_ONLY`',
            '`AGENT_EVALUATION_EXTERNAL_HOLDOUT_AFTER_GENERATION`',
            'This directory remains outside the framework package.',
            'It requires exactly `support.php`, `tasks.php`, `run.php`, and `score.php`',
            'Version 1 treats the event and candidate-patch artifacts as opaque retained bytes',
            'This is the one retained artifact whose internal format v0.1 validates.',
        ],
        'tools/agent-evaluation/support.php' => [
            'AGENT_EVALUATION_MAX_JSON_BYTES',
            'JSON input contains a duplicate object name.',
        ],
        'tools/agent-evaluation/tasks.php' => [
            'AGENT_EVALUATION_TASK_REVISIONS',
            'Public smoke task {$taskId} cannot authorize comparative claims.',
        ],
        'tools/agent-evaluation/run.php' => [
            'Run record task revision does not match the selected task.',
            'Prepared-dependencies manifest lines must be unique and byte-sorted.',
        ],
        'tools/agent-evaluation/score.php' => [
            "['manifest_valid', 'workspace_policy', 'application_check', 'public_scorer', 'resource_bounds']",
            'Automated status does not match the admissibility, mandatory checks, and critical dimensions.',
        ],
        'tools/agent-evaluation/tasks.json' => [
            '"change.simple-ping"',
        ],
        'tools/agent-evaluation/schema/run.schema.json' => [
            '"title": "PHPThis agent evaluation run v1"',
            '"candidate_patch_path"',
        ],
        'tools/agent-evaluation/schema/score.schema.json' => [
            '"title": "PHPThis agent evaluation score v1"',
            '"manifest_valid"',
            '"human_review"',
        ],
        'tools/agent-evaluation/schema/task.schema.json' => [
            '"title": "PHPThis agent evaluation task v1"',
            '"comparative_claims"',
            '"const": false',
        ],
        'tools/agent-evaluation/tasks/change.simple-ping/task.json' => [
            '"id": "change.simple-ping"',
            '"source-skeleton"',
            '"max_changed_files": 3',
            '"comparative_claims": false',
        ],
        'tools/agent-evaluation/tasks/change.simple-ping/prompt.md' => [
            'Add a dependency-free `GET /ping` endpoint',
            'Keep the existing `GET /health` behavior unchanged.',
        ],
        'tools/agent-evaluation/tasks/change.simple-ping/rubric.md' => [
            '`AGENT_EVALUATION_PUBLIC_SMOKE_ONLY`',
            '`manifest_valid`',
            'An official evaluation supplies a separately versioned scorer only after generation',
        ],
        'tools/agent-evaluation/tasks/change.simple-ping/public/holdout.php.fixture' => [
            "new Request('GET', '/ping')",
            "new Request('POST', '/ping')",
            "new Request('GET', '/health')",
            "new Request('GET', '/missing')",
        ],
        'tools/test-agent-evaluation.php' => [
            'Run record task revision does not match the selected task.',
            "['public_scorer'] = false",
            "['weighted_score'] = 99",
            'prompt SHA-256 does not match its recorded hash.',
        ],
    ];
    requireGuardrailArtifactMarkers(
        $root,
        $agentEvaluationArtifactMarkers,
        'agent-evaluation source and boundary',
        $failures,
    );

    $candidateExecutionFunctions = [
        'proc_open(',
        'shell_exec(',
        'passthru(',
        'system(',
        'exec(',
        'popen(',
        'pcntl_exec(',
    ];
    forbidGuardrailArtifactMarkers(
        $root,
        [
            'tools/agent-evaluation.php' => $candidateExecutionFunctions,
            'tools/agent-evaluation/support.php' => $candidateExecutionFunctions,
            'tools/agent-evaluation/tasks.php' => $candidateExecutionFunctions,
            'tools/agent-evaluation/run.php' => $candidateExecutionFunctions,
            'tools/agent-evaluation/score.php' => $candidateExecutionFunctions,
            'tools/test-agent-evaluation.php' => $candidateExecutionFunctions,
        ],
        'agent-evaluation candidate-execution prohibition',
        $failures,
    );

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

    $installedConsumerStage = is_array($duplicationCheck)
        ? array_search('@test:consumer', $duplicationCheck, true)
        : false;

    if ($installedConsumerStage === false) {
        $failures[] = 'The canonical framework check must execute the installed release and consumer-distribution proof.';
    }

    $agentEvaluationStage = is_array($duplicationCheck)
        ? array_search('@test:agent-evaluation', $duplicationCheck, true)
        : false;
    $analysisStage = is_array($duplicationCheck)
        ? array_search('@analyse', $duplicationCheck, true)
        : false;
    $profileStage = is_array($duplicationCheck)
        ? array_search('@test:profile', $duplicationCheck, true)
        : false;

    if (
        $agentEvaluationStage === false
        || $analysisStage === false
        || $profileStage === false
        || $agentEvaluationStage <= $analysisStage
        || $agentEvaluationStage >= $profileStage
    ) {
        $failures[] = 'The canonical framework check must run the agent-evaluation self-test after analysis and before the Strict Profile suite.';
    }

    return $failures;
}
