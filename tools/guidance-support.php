<?php

declare(strict_types=1);

/**
 * Check concrete document references, not the meaning of the surrounding prose.
 * Routes use the existing inline-code or inline-link spelling; callers supply
 * explicit destinations because copied application paths resolve differently.
 *
 * @param array<string, string> $references reference => destination file
 * @return list<string>
 */
function guidanceDocumentFailures(string $path, array $references = []): array
{
    $contents = is_file($path) ? file_get_contents($path) : false;
    if (!is_string($contents) || trim($contents) === '') {
        return ["Required guidance is missing, unreadable, or empty: {$path}."];
    }

    $nonProsePattern = '~<!--.*?-->|^ {0,3}(`{3,}|\x7E{3,})[^\r\n]*\R.*?^ {0,3}\1[ \t]*$~ms';
    $prose = preg_replace($nonProsePattern, '', $contents);
    if (!is_string($prose) || trim($prose) === '') {
        return ["Required guidance has no prose: {$path}."];
    }
    $matches = [];
    preg_match_all('~\[[^\]\r\n]*\]\(([^)\r\n]+)\)|(?<!`)`([^`\r\n]+)`(?!`)~', $prose, $matches, PREG_SET_ORDER);
    $actualReferences = [];
    foreach ($matches as $match) {
        $reference = $match[2] ?? $match[1] ?? null;
        if ($reference !== null) {
            $actualReferences[] = $reference;
        }
    }

    $failures = [];
    foreach ($references as $reference => $destination) {
        if (!in_array($reference, $actualReferences, true)) {
            $failures[] = "Missing guidance route in {$path}: {$reference}.";
        }
        $target = is_file($destination) ? file_get_contents($destination) : false;
        $targetProse = is_string($target) ? preg_replace($nonProsePattern, '', $target) : null;
        if (!is_string($targetProse) || trim($targetProse) === '') {
            $failures[] = "Missing or empty guidance route target from {$path}: {$destination}.";
            continue;
        }
        $fragment = explode('#', $reference, 2)[1] ?? null;
        if ($fragment === null) {
            continue;
        }
        $headings = [];
        preg_match_all('/^#{1,6}[ \t]+([^\r\n]+)$/m', $targetProse, $headings);
        $anchors = [];
        foreach ($headings[1] as $heading) {
            $plain = preg_replace('/[^a-z0-9 -]/', '', strtolower($heading));
            if (is_string($plain)) {
                $anchors[] = str_replace(' ', '-', trim($plain));
            }
        }
        if (!in_array($fragment, $anchors, true)) {
            $failures[] = "Missing guidance anchor {$fragment} in {$destination}, routed from {$path}.";
        }
    }
    return $failures;
}

/** @return list<string> */
function taskRoutedGuidanceFailures(string $framework, string $application): array
{
    $failures = [];
    // These are distribution expectations, shared by source and installed proofs.
    // They neither discover policy nor certify the meaning of a task label.
    $documents = [
        'VISION.md' => ['docs/design-goals.md'],
        'docs/design-goals.md' => [],
        'docs/knowledge-map.md' => [
            'VISION.md', 'docs/design-goals.md', 'docs/consumer-contract.md',
            'docs/request-handling.md', 'docs/configuration.md', 'docs/migrations.md',
            'docs/jobs/README.md', 'docs/file-transfers/README.md', 'docs/websockets.md',
            'docs/consumer-contract-upgrades.md', 'docs/type-safety.md', 'docs/errors.md',
            'docs/security.md', 'docs/static-analysis.md', 'docs/strict-profile.md',
            'docs/sessions.md', 'docs/caching.md', 'docs/coordination.md', 'docs/cli.md',
            'docs/frontend-integration.md', 'docs/email.md', 'docs/rag.md', 'docs/workbench.md',
            'docs/observability/README.md', 'docs/logging.md', 'docs/date-time.md',
            'docs/request-policy.md', 'docs/stateless-authentication.md', 'docs/crud.md',
            'docs/database.md', 'docs/performance.md', 'RELEASING.md',
        ],
        'docs/consumer-contract.md' => [
            'docs/knowledge-map.md', 'docs/request-handling.md', 'docs/configuration.md',
            'docs/migrations.md', 'docs/jobs/README.md', 'docs/file-transfers/README.md',
            'docs/websockets.md', 'docs/consumer-contract-upgrades.md',
        ],
        'docs/consumer-contract-upgrades.md' => [],
        'docs/getting-started.md' => [],
        'docs/evaluation.md' => ['docs/knowledge-map.md'],
        'RELEASING.md' => ['docs/decisions/README.md', 'docs/decisions/062-bounded-alpha-8-release-scope.md', 'docs/releases/0.1.0-alpha.8.md'],
        'ROADMAP.md' => ['RELEASING.md', 'docs/evaluation.md', 'docs/knowledge-map.md'],
        'docs/strict-profile.md' => [],
        'docs/type-safety.md' => [],
        'docs/crud.md' => [],
        'docs/ai-context-routing-review.md' => [],
        'docs/decisions/044-bounded-task-routed-ai-context.md' => [],
        'docs/decisions/058-concern-local-ai-context-routing.md' => [],
        'docs/decisions/064-one-entrypoint-per-audience.md' => [],
    ];
    foreach ($documents as $relativePath => $routes) {
        $references = [];
        foreach ($routes as $route) {
            $references[$route] = $framework . '/' . $route;
        }
        $failures = [...$failures, ...guidanceDocumentFailures($framework . '/' . $relativePath, $references)];
    }

    $failures = [...$failures, ...guidanceDocumentFailures($framework . '/docs/evaluation.md', [
        'ai-context-routing-review.md' => $framework . '/docs/ai-context-routing-review.md',
    ])];

    foreach ([$application, $framework . '/templates/application'] as $context) {
        $entrypointReferences = [
            'vendor/phpthis/framework/docs/consumer-contract.md' => $framework . '/docs/consumer-contract.md',
        ];
        foreach (['knowledge-map', 'consumer-contract', 'request-handling', 'type-safety', 'frontend-integration', 'sessions', 'caching', 'date-time', 'errors', 'crud', 'static-analysis'] as $guide) {
            $entrypointReferences['docs/' . $guide . '.md'] = $framework . '/docs/' . $guide . '.md';
        }
        foreach (['project', 'architecture', 'configuration', 'data', 'request-policy', 'file-transfers', 'integrations', 'operations', 'observability', 'jobs', 'cli', 'migrations', 'websockets', 'workbench', 'testing'] as $guide) {
            $entrypointReferences['.ai/' . $guide . '.md'] = $context . '/.ai/' . $guide . '.md';
        }
        $failures = [...$failures, ...guidanceDocumentFailures($context . '/AGENTS.md', $entrypointReferences)];
        $compatibilityReferences = [
            'AGENTS.md' => $context . '/AGENTS.md',
            '.ai/rules.md' => $context . '/.ai/rules.md',
            '.ai/change-workflow.md' => $context . '/.ai/change-workflow.md',
            'vendor/phpthis/framework/docs/consumer-contract-upgrades.md#contract-version-18' => $framework . '/docs/consumer-contract-upgrades.md',
        ];
        $failures = [...$failures, ...guidanceDocumentFailures($context . '/.ai/README.md', $compatibilityReferences)];
        foreach (['rules', 'change-workflow'] as $guide) {
            $failures = [...$failures, ...guidanceDocumentFailures($context . '/.ai/' . $guide . '.md', ['AGENTS.md' => $context . '/AGENTS.md'])];
        }
        $failures = [...$failures, ...guidanceDocumentFailures($context . '/.ai/configuration.md', [
            'vendor/phpthis/framework/docs/configuration.md#scope-database-setup-before-implementation' => $framework . '/docs/configuration.md',
        ])];
    }
    return $failures;
}
