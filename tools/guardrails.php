<?php

declare(strict_types=1);

require_once __DIR__ . '/strict-profile.php';

$root = dirname(__DIR__);
$phpFiles = [];
$markdownFiles = [];
$failures = [];
$phpstanConfig = file_get_contents($root . '/phpstan.neon');

if (!is_string($phpstanConfig)) {
    $failures[] = 'Cannot read phpstan.neon.';
} else {
    if (!str_contains($phpstanConfig, 'vendor/phpstan/phpstan-strict-rules/rules.neon')) {
        $failures[] = 'phpstan.neon must include PHPStan strict rules.';
    }

    if (!str_contains($phpstanConfig, 'tools/phpstan/extension.php')) {
        $failures[] = 'phpstan.neon must include PHPThis Strict Profile rules.';
    }

    if (preg_match('/strictRules:\s*\R\s+allRules:\s*true\b/', $phpstanConfig) !== 1) {
        $failures[] = 'phpstan.neon must explicitly enable every installed strict rule.';
    }

    if (preg_match('/^\s*ignoreErrors\s*:/m', $phpstanConfig) === 1) {
        $failures[] = 'phpstan.neon must not define ignoreErrors.';
    }
}

foreach (['phpstan-baseline.neon', 'phpstan-baseline.php'] as $baselineFile) {
    if (is_file($root . '/' . $baselineFile)) {
        $failures[] = "PHPStan baseline files are forbidden: {$baselineFile}.";
    }
}

$requiredApplicationContextFiles = [
    'docs/consumer-contract.md',
    'docs/getting-started.md',
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
];

foreach ($requiredApplicationContextFiles as $requiredApplicationContextFile) {
    if (!is_file($root . '/' . $requiredApplicationContextFile)) {
        $failures[] = "Required application context file is missing: {$requiredApplicationContextFile}.";
    }
}

$consumerContractPath = $root . '/docs/consumer-contract.md';

if (is_file($consumerContractPath)) {
    $consumerContract = file_get_contents($consumerContractPath);

    if (!is_string($consumerContract)) {
        $failures[] = 'Cannot read docs/consumer-contract.md.';
    } elseif (preg_match('/^Contract version: 0$/m', $consumerContract) !== 1) {
        $failures[] = 'docs/consumer-contract.md must declare contract version 0.';
    }
}

$applicationAgentInstructionsPath = $root . '/templates/application/AGENTS.md';

if (is_file($applicationAgentInstructionsPath)) {
    $applicationAgentInstructions = file_get_contents($applicationAgentInstructionsPath);

    if (!is_string($applicationAgentInstructions)) {
        $failures[] = 'Cannot read templates/application/AGENTS.md.';
    } elseif (!str_contains(
        $applicationAgentInstructions,
        'vendor/phpthis/framework/docs/consumer-contract.md',
    )) {
        $failures[] = 'Application AGENTS.md must point to the installed PHPThis consumer contract.';
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

    if ($file->getExtension() === 'php') {
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

    if (preg_match('/^<\\?php\\s+declare\\(strict_types=1\\);/', $contents) !== 1) {
        $failures[] = "{$relativePath} must declare strict types immediately after <?php.";
    }

    preg_match_all('/function\\s+(__[A-Za-z0-9_]+)/', $contents, $magicMatches);

    foreach ($magicMatches[1] as $magicMethod) {
        if ($magicMethod !== '__construct') {
            $failures[] = "{$relativePath} defines forbidden magic method {$magicMethod}.";
        }
    }

    if (preg_match('/\\beval\\s*\\(/', $contents) === 1) {
        $failures[] = "{$relativePath} uses eval.";
    }

    if (preg_match('/\\$\\$[A-Za-z_{]/', $contents) === 1) {
        $failures[] = "{$relativePath} uses a variable variable.";
    }

    foreach (PHPThisStrictProfile::syntaxFailures($contents, $relativePath) as $profileFailure) {
        $failures[] = $profileFailure;
    }

    if ($relativePath !== 'src/Database/Connection.php' && preg_match('/new\\s+PDO\\s*\\(/', $contents) === 1) {
        $failures[] = "{$relativePath} constructs PDO outside Connection.";
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

    foreach ($tokens as $index => $token) {
        $tokenId = is_array($token) ? $token[0] : null;
        $tokenText = is_array($token) ? $token[1] : $token;

        if (
            $relativePath !== 'example/public/index.php'
            && $tokenId === T_VARIABLE
            && in_array(
                $tokenText,
                ['$_SERVER', '$_GET', '$_POST', '$_COOKIE', '$_FILES', '$_ENV', '$_REQUEST'],
                true,
            )
        ) {
            $failures[] = sprintf(
                '%s:%d reads a PHP superglobal outside the front controller.',
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

if ($coreLines > 900) {
    $failures[] = "Core source has {$coreLines} physical lines; the Phase 1 limit is 900.";
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
