<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/verification/SyntaxProfile.php';
require_once __DIR__ . '/guardrails/repository.php';
require_once __DIR__ . '/guardrails/context.php';
require_once __DIR__ . '/guardrails/boundaries.php';
require_once __DIR__ . '/guardrails/operations.php';
require_once __DIR__ . '/guardrails/distribution.php';

$root = dirname(__DIR__);
$failures = [];

foreach (repositoryGuardrailFailures($root) as $failure) {
    $failures[] = $failure;
}

foreach (contextGuardrailFailures($root) as $failure) {
    $failures[] = $failure;
}

foreach (boundaryGuardrailFailures($root) as $failure) {
    $failures[] = $failure;
}

foreach (operationGuardrailFailures($root) as $failure) {
    $failures[] = $failure;
}

$markdownCount = 0;
$phpCount = 0;
$coreLines = 0;

foreach (
    distributionGuardrailFailures($root, $markdownCount, $phpCount, $coreLines)
    as $failure
) {
    $failures[] = $failure;
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
        $markdownCount,
        $phpCount,
        $coreLines,
    ),
);