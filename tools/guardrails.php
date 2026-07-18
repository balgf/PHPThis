<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/verification/SyntaxProfile.php';

use PHPThis\Verification\SyntaxProfile;

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
    '.ai/crud.md',
    '.ai/database.md',
    '.ai/session.md',
    'docs/consumer-contract.md',
    'docs/crud.md',
    'docs/getting-started.md',
    'docs/knowledge-map.md',
    'docs/security.md',
    'docs/sessions.md',
    'docs/decisions/011-ai-first-authoring.md',
    'docs/decisions/012-pdo-transport-application-owned-dialects.md',
    'docs/decisions/013-optional-crud-reference-profile.md',
    'docs/decisions/014-sql-data-and-finite-structure.md',
    'docs/decisions/015-explicit-native-session-lifecycle.md',
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
    'src/Session/SessionConfiguration.php',
    'src/Session/SessionLifecycle.php',
    'src/Session/SessionSnapshot.php',
    'src/Session/SessionUnavailable.php',
    'tools/package-files.txt',
    'tools/test-database-drivers.php',
];

foreach ($requiredRepositoryFiles as $requiredRepositoryFile) {
    if (!is_file($root . '/' . $requiredRepositoryFile)) {
        $failures[] = "Required repository file is missing: {$requiredRepositoryFile}.";
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
        if (preg_match('/^Contract version: 3$/m', $consumerContract) !== 1) {
            $failures[] = 'docs/consumer-contract.md must declare contract version 3.';
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

    $tokens = token_get_all($contents);
    $loopBodyPending = false;
    $loopHeaderDepth = null;
    $loopHeaderComplete = false;
    $braceDepth = 0;
    $loopBraceDepths = [];
    $routeLookupMethodPending = null;
    $routeLookupMethod = null;
    $routeLookupMethodBraceDepth = null;
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

        if ($relativePath === 'src/Routing/Router.php' && $tokenId === T_FUNCTION) {
            for ($next = $index + 1, $count = count($tokens); $next < $count; $next++) {
                $nextToken = $tokens[$next];

                if (is_array($nextToken) && $nextToken[0] === T_WHITESPACE) {
                    continue;
                }

                if (
                    is_array($nextToken)
                    && $nextToken[0] === T_STRING
                    && in_array($nextToken[1], ['match', 'allowedMethodsForPath'], true)
                ) {
                    $routeLookupMethodPending = $nextToken[1];
                }

                break;
            }
        }

        if (in_array($tokenId, [T_FOR, T_FOREACH, T_WHILE, T_DO], true)) {
            if ($routeLookupMethod !== null) {
                $failures[] = sprintf(
                    'src/Routing/Router.php:%d uses a loop in %s; route lookup must remain indexed.',
                    $token[2],
                    $routeLookupMethod,
                );
            }

            $loopBodyPending = true;
            $loopHeaderDepth = $tokenId === T_DO ? 0 : null;
            $loopHeaderComplete = $tokenId === T_DO;
            continue;
        }

        if ($loopBodyPending && $tokenText === '(') {
            $loopHeaderDepth = ($loopHeaderDepth ?? 0) + 1;
            continue;
        }

        if ($loopBodyPending && $tokenText === ')' && is_int($loopHeaderDepth)) {
            $loopHeaderDepth--;

            if ($loopHeaderDepth === 0) {
                $loopHeaderComplete = true;
            }

            continue;
        }

        if ($tokenText === '{') {
            $braceDepth++;

            if ($routeLookupMethodPending !== null) {
                $routeLookupMethod = $routeLookupMethodPending;
                $routeLookupMethodBraceDepth = $braceDepth;
                $routeLookupMethodPending = null;
            }

            if ($loopBodyPending && $loopHeaderComplete) {
                $loopBraceDepths[] = $braceDepth;
                $loopBodyPending = false;
            }

            continue;
        }

        if ($tokenText === '}') {
            if ($loopBraceDepths !== [] && end($loopBraceDepths) === $braceDepth) {
                array_pop($loopBraceDepths);
            }

            if ($routeLookupMethodBraceDepth === $braceDepth) {
                $routeLookupMethod = null;
                $routeLookupMethodBraceDepth = null;
            }

            $braceDepth--;
            continue;
        }

        if ($loopBodyPending && $loopHeaderComplete && $tokenText === ';') {
            $loopBodyPending = false;
            continue;
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

if ($coreLines > 1_700) {
    $failures[] = "Core source has {$coreLines} physical lines; the Phase 1 limit is 1700.";
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
