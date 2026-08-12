<?php

declare(strict_types=1);

function proveInstalledTransactionalEmailGuidanceDistribution(
    string $project,
    string $installedFramework,
): void {
    /** @var array<string, list<string>> $artifactMarkers */
    $artifactMarkers = [
        $project . '/.ai/README.md' => [
            '| Compose or deliver transactional email | installed `vendor/phpthis/framework/docs/email.md` | `.ai/integrations.md` and the operation-specific composer and transport; add configuration, jobs, operations, and testing context only when entered |',
        ],
        $project . '/.ai/integrations.md' => [
            '`NOT_APPLICABLE`: the starter application contacts no external services and performs no external side effects.',
            '`NOT_APPLICABLE(EMAIL)`',
            'Before adoption, read installed `vendor/phpthis/framework/docs/email.md`',
            'Do not add a framework mailer, renderer, notification system, queue, worker, or webhook receiver.',
        ],
        $installedFramework . '/docs/email.md' => [
            '# Application-owned transactional email',
            'PHPThis provides no mail transport or email-rendering runtime.',
            'The checked `user.welcome` example is not an email implementation.',
            '`RecordUserWelcomeDelivery` records one idempotent database effect',
            'Email HTML is a distinct MIME output sink.',
            'replace `NOT_APPLICABLE(EMAIL)` in application `.ai/integrations.md` with a reviewed adoption record.',
            'Separate deterministic message composition from transport and provider I/O.',
            'one operation-specific final readonly message or view model containing already validated values.',
            'Select a template only through a finite code-owned choice',
            'Template execution performs no database or network I/O, filesystem discovery, service lookup, session or environment access, mutable-global access, or dynamic code execution.',
            'Every message has an intentionally authored UTF-8 `text/plain` body.',
            'authoritative plain text must not be produced by naively stripping HTML.',
            'the renderer\'s finite public or operational failure mapping without template values or exception details;',
            'template input cardinality, execution timeout or other effective execution bound, and maximum rendered bytes;',
            'A template package is optional.',
            '## Encode at each output context',
            'every deliberate raw-output boundary is finite, code-owned, named, reviewed, and tested with adversarial values.',
            'Build absolute reviewed HTTPS links from typed canonical application configuration.',
            'Never derive their origin, scheme, authority, or base path from an untrusted `Host`, `Forwarded`, or `X-Forwarded-*` request header.',
            'Do not use native `mail()` or hand-build MIME boundaries, header folding, address encoding, or transfer encoding.',
            'Keep these identities distinct:',
            'Reject CR, LF, NUL, and the selected package\'s other invalid control characters in every value that can reach a header.',
            'Never accept a user-selected header name, raw header line, MIME part type, transfer encoding, or boundary.',
            'Record and enforce limits for recipient count, header count and bytes, each text and HTML body, attachment count and bytes, inline-image count and bytes, and total encoded message bytes.',
            '## One explicit transport boundary',
            'the exact package and version, provider and API or SMTP contract version, and supported feature subset;',
            'separate finite connect, operation, and total timeouts;',
            'dependency and contract update cadence plus redacted behavior for every known and unknown failure.',
            'Provider acceptance means only that the selected provider accepted a request under its contract.',
            'Permit synchronous sending only after the application explicitly accepts and tests its latency, timeout, provider-outage, client-disconnect, process-termination, duplicate-submission, and public-failure consequences',
            'An external call does not belong inside the business database transaction.',
            'When delivery must survive request or process termination, prefer a durable deferred intent',
            'The ADR 024 `jobs:run-one` example is specifically a one-delivery application console operation.',
            'For another selected backend under ADR 052, assume delivery may occur more than once, make the email effect duplicate-safe, prove the positive durable-publication and loss envelope',
            'one bounded business-event idempotency key distinct from recipient-controlled input;',
            'provider idempotency support and key scope, retention window, collision policy, and unsupported cases;',
            'durable internal request identity and any provider request, message, and receipt identifiers;',
            'ambiguous-timeout behavior when the provider may have accepted a request but the application received no conclusive response;',
            'finite attempt count and version-controlled backoff/redrive with its exact application-code, broker-configuration or infrastructure-as-code owner, plus retryable and terminal classifications;',
            'the selected terminal destination or state, privileged redacted inspection and retention;',
            'authoritative reconciliation inputs, cadence, timeout, and unavailable-provider behavior;',
            'compensation policy when the external effect cannot be reversed;',
            'the identity authorized to perform an operator replay and the checks that preserve idempotency and audit evidence; and',
            'Treat bounce, complaint, suppression, unsubscribe, and delivery-status webhooks as separate external integrations.',
            'SPF, DKIM, DMARC, sender and domain verification, consent, unsubscribe and legal policy',
            'Keep credentials, recipient data, message bodies, rendered HTML and text, link or action tokens, provider responses, exception details, and webhook payloads out of default logs and durable diagnostic codes.',
            'Composition tests cover address and header injection, finite template selection, every output-context encoder, every deliberate raw boundary, intentional text and HTML semantic parity, absolute HTTPS links and token encoding, supported locales, recipient and byte limits, attachments and inline images when adopted, renderer failures, and deterministic semantic composition.',
            'Inspect composed messages semantically through the selected mail or MIME package.',
            'Transport tests cover success, provider rejection, authentication failure, rate limiting, retryable and terminal failures, ambiguous timeout, provider-idempotent retry, redaction, reconciliation, and the selected terminal behavior without contacting production.',
            'Prove that synchronous failure follows its recorded public contract and that deferred failure follows the deliberately selected adoption\'s actual delivery, acknowledgement, retry/redrive and terminal semantics.',
            'Use only a local fake, captured transport, or explicitly approved provider sandbox for integration evidence.',
            '## Unsupported framework boundary',
            'Adopting email changes no Consumer Contract or Strict Profile requirement.',
        ],
        $installedFramework . '/docs/knowledge-map.md' => [
            '| Compose, deliver, or review transactional email | `docs/email.md`;',
            'verify that the welcome-delivery example remains a database-effect proof and that no framework mailer, renderer, queue, worker, or webhook receiver was implied',
        ],
        $installedFramework . '/templates/application/.ai/README.md' => [
            '| Compose or deliver transactional email | installed `vendor/phpthis/framework/docs/email.md` | `.ai/integrations.md` and the operation-specific composer and transport; add configuration, jobs, operations, and testing context only when entered |',
        ],
        $installedFramework . '/templates/application/.ai/integrations.md' => [
            '## Transactional email boundary',
            'Adoption or `NOT_APPLICABLE(EMAIL)`',
            '{{EMAIL_COMPOSITION_BOUNDARY_OR_NOT_APPLICABLE}}',
            '{{EMAIL_PACKAGE_CONTRACT_OR_NOT_APPLICABLE}}',
            '{{EMAIL_RENDERING_POLICY_OR_NOT_APPLICABLE}}',
            '{{EMAIL_OUTPUT_AND_ADDRESS_POLICY_OR_NOT_APPLICABLE}}',
            '{{EMAIL_RESOURCE_BOUNDS_OR_NOT_APPLICABLE}}',
            '{{EMAIL_TRANSPORT_POLICY_OR_NOT_APPLICABLE}}',
            '{{EMAIL_DELIVERY_POLICY_OR_NOT_APPLICABLE}}',
            '{{EMAIL_WEBHOOK_POLICY_OR_NOT_APPLICABLE}}',
            '{{EMAIL_OPERATIONS_AND_EVIDENCE_OR_NOT_APPLICABLE}}',
            'PHPThis provides no mail transport or email-rendering runtime;',
            'Do not use native `mail()`, hand-build MIME, or add a framework mailer, renderer, notification system, queue, worker, webhook receiver, provider, or hidden retry.',
        ],
        $installedFramework . '/docs/guardrails.md' => [
            'transactional-email guidance, installed task and integration routes, exact package inventory, and Composer dependency checks keep composition, MIME, rendering, delivery, provider operations, and evidence application-owned',
            'without adding a framework mailer, renderer, notification system, queue, worker, webhook receiver, or runtime dependency',
            'The transactional-email guidance guard pins the dedicated installed guide',
            'It adds no framework mailer, renderer, notification system, provider adapter, queue, worker, webhook receiver, runtime dependency, consumer-checker rule, behavior requirement, or `PHT` diagnostic.',
        ],
    ];

    requireInstalledArtifactMarkers($artifactMarkers, 'application-owned transactional email guidance');
    requireInstalledNativeRuntimeDependencyBoundary($project, $installedFramework);

    fwrite(STDOUT, "PASS installed application-owned transactional email guidance distribution\n");
}

function proveInstalledOneShotWorkerSupervisionGuidanceDistribution(
    string $project,
    string $installedFramework,
): void {
    /** @var array<string, list<string>> $artifactMarkers */
    $artifactMarkers = [
        $project . '/.ai/jobs.md' => [
            '`NOT_APPLICABLE(JOBS)`',
            'Before adopting jobs, read installed `vendor/phpthis/framework/docs/jobs/README.md`.',
            'Accepted ADR 052 supplies the optional backend-neutral contract and verification structure.',
            'The current accepted checked profile remains installed `vendor/phpthis/framework/docs/jobs/sqlite.md` under ADR 024.',
        ],
        $installedFramework . '/docs/jobs.md' => [
            'Status: current optional guidance under accepted [ADR 052]',
            'PHPThis currently accepts this backend-neutral application-owned contract and one checked backend-specific profile: the application-owned [SQLite recipe](jobs/sqlite.md) recorded by ADR 024.',
            'The minimum successful-publication invariant keeps work recoverable or deliverable within its named fault envelope',
            'The accepted [SQLite profile](jobs/sqlite.md) remains the first and only checked profile under its existing ADR 024 evidence;',
            'The stricter exact service/client version and real-service bar above applies to every profile added under ADR 052.',
        ],
        $installedFramework . '/docs/jobs/sqlite.md' => [
            '[externally supervised one-shot durable jobs](operations.md) is the focused canonical production-operations guide.',
            'failure-only restart behavior does not provide continual consumption.',
            'Each enabled supervisor slot requires a positive bounded idle delay or equivalent pacing',
            'Production one-offs also stay in the finite tested application console rather than an arbitrary expression process.',
            'A production adopter also proves its actual supervisor\'s successful-exit repetition, fresh-process backlog draining, idle pacing, clean stop, post-expiry crash recovery, concurrency and timeout bounds, and capacity alarms',
        ],
        $installedFramework . '/docs/jobs/operations.md' => [
            '# Externally supervised one-shot durable jobs',
            'This is the focused canonical operations guide for continual consumption',
            'The supervisor is long-running; each PHP worker process is one-shot:',
            'Each invocation creates a fresh connection',
            'It is not the ordinary queue-draining worker.',
            'A supervisor configured to restart only after failure therefore stops after the first expected result',
            'the separately owned supervisor and the location and owner of its configuration, without making a named process manager or platform part of PHPThis;',
            'the exact application console invocation, deployment identity, configuration source and redaction boundary, and access to the selected database path;',
            'the invocation or restart delay, worker-slot count, total concurrency limit, process timeout, and forced-termination policy;',
            'the deployment replacement and configuration-change behavior, including when old children stop and fresh composition begins;',
            'the shutdown behavior and the finite allowance before a current child is terminated;',
            'restart-storm protection for repeated startup, configuration, database, or other operational failures.',
            'Every enabled slot uses a positive bounded idle delay or equivalent supervisor pacing',
            'Clean stopping has three steps:',
            'Recovery relies on the existing finite SQLite lease:',
            'SQLite permits one writer at a time.',
            '## Required production evidence',
            'launch another fresh process after a successful expected exit `0`;',
            'drain multiple queued jobs through multiple fresh processes, with each process claiming and finalizing at most one delivery;',
            'raise the recorded queue-depth, oldest-due-age, duration, operational-failure, and dead-letter-growth capacity alarms.',
            'A bounded multi-delivery process or an indefinite worker loop requires a separate accountable decision and evidence.',
            'queue-age or throughput objectives remain unmet after the application tunes and proves one-shot supervision;',
            'PHP startup and fresh-composition cost materially dominates delivery work;',
            'independent applications reproduce the same smaller lifecycle need.',
            'Strict Profile diagnostic `PHT003`',
        ],
        $installedFramework . '/docs/jobs/README.md' => [
            '[Externally supervised one-shot operations](operations.md): canonical production topology, direct continual consumption, supervisor policy, SQLite capacity, monitoring, recovery, and reconsideration triggers.',
        ],
        $installedFramework . '/docs/jobs/testing.md' => [
            'Production supervisor evidence is separately required by [externally supervised one-shot durable jobs](operations.md).',
        ],
        $installedFramework . '/docs/cli.md' => [
            'Continual durable-job consumption follows the separate [externally supervised one-shot operations guide](jobs/operations.md).',
            '`schedule:run` remains a bounded cadence-gated scheduled pass that may call the same operation once; it is not the ordinary queue-draining worker.',
            'A supervisor configured to restart only after failure will stop after any expected outcome.',
            'Continual consumption therefore launches another fresh process after expected exit `0`',
            'the production evidence required by [the durable-job operations guide](jobs/operations.md)',
        ],
        $installedFramework . '/docs/cli/README.md' => [
            'continual job consumption instead follows [externally supervised one-shot operations](../jobs/operations.md).',
        ],
        $installedFramework . '/docs/cli/scheduling-locking.md' => [
            'It is a bounded scheduled pass, not the ordinary queue-draining worker;',
            'continual consumption directly supervises fresh `jobs:run-one` processes under [the durable-job operations guide](../jobs/operations.md).',
        ],
        $installedFramework . '/docs/knowledge-map.md' => [
            '| Adopt, change, or review the accepted optional backend-neutral application-owned durable-job contract |',
            '`docs/jobs.md`, `docs/jobs/verification.md`, `docs/jobs/README.md`, accepted ADR 052, and `docs/security.md`',
            'verify fail-closed no-skip/no-mock release behavior and that PHPThis provides no runtime, adapter, generic validator or backend checker',
            '| Adopt, change, or review ADR 024\'s optional SQLite durable-job recipe |',
            '`docs/jobs/sqlite.md`, `docs/jobs/operations.md`, `docs/jobs/testing.md`, `docs/security.md`, ADR 024',
            'do not generalize its transaction, lease, query bounds, one-shot lifecycle or outcomes to another backend or a framework queue or worker API',
        ],
        $installedFramework . '/templates/application/.ai/jobs.md' => [
            '{{JOBS_WORKER_LIFECYCLE_OR_NOT_APPLICABLE}}',
            'Before adoption, read installed `vendor/phpthis/framework/docs/jobs.md` and `vendor/phpthis/framework/docs/jobs/verification.md`',
            'If deliberately adopting the current checked SQLite profile, also follow installed `vendor/phpthis/framework/docs/jobs/sqlite.md`',
            '`jobs:verify` is the accepted guidance\'s canonical application-owned Composer evidence script name',
        ],
        $installedFramework . '/docs/guardrails.md' => [
            'the canonical externally supervised continual-consumption policy',
            'The installed proof rereads the packaged job, CLI, operations, index, testing, and application-context guidance.',
            'it does not inspect or run a process manager, drain a real production queue, certify a filesystem or SQLite deployment, measure throughput or contention, activate an alarm',
        ],
    ];

    requireInstalledArtifactMarkers($artifactMarkers, 'one-shot worker supervision guidance');
    requireInstalledNativeRuntimeDependencyBoundary($project, $installedFramework);

    fwrite(STDOUT, "PASS installed one-shot worker supervision guidance distribution\n");
}

/** @param list<string> $archiveFiles */
function proveInstalledAgentEvaluationGuidanceDistribution(
    string $installedFramework,
    array $archiveFiles,
): void
{
    requireInstalledArtifactMarkers(
        [
            $installedFramework . '/docs/evaluation.md' => [
                '## Agent Evaluation Kit v0.1 and controller v0.2',
                'It is maintainer tooling and is not part of the installed framework package.',
                'Its scorer source is visible',
                'It cannot show that one model, skill, prompt, context strategy, or framework condition is better than another.',
                'ADR 048 accepts the separately located `tools/agent-evaluation-controller.php` entrypoint',
                '`prepare -> generate -> freeze -> score -> validate -> retain -> cleanup`',
                'deterministic test-only `fake-codex` runner',
                'The normal `composer check` path uses only synthetic fixtures',
                'The sole accepted future real runner is `codex-exec`',
                'There is no native macOS, `sandbox-exec`, direct-host, arbitrary-shell, discovered-runner, or second-runner fallback.',
                'Version 0.2 does not yet implement or exercise that real adapter.',
                '`AGENT_EVALUATION_CONTROLLER_OCI_ONLY`',
                '`AGENT_EVALUATION_CONTROLLER_FAKE_RUNNER_CI_ONLY`',
                '`AGENT_EVALUATION_CONTROLLER_NO_NATIVE_FALLBACK`',
                '`comparative_claims` to `false`',
                'The scorer must not be available to the agent during generation',
                'Human semantic review remains separate.',
                '`AGENT_EVALUATION_EXTERNAL_HOLDOUT_AFTER_GENERATION`',
            ],
            $installedFramework . '/docs/guardrails.md' => [
                'The Agent Evaluation Kit guard requires',
                'installed-consumer evidence pins only the packaged `docs/evaluation.md` boundary',
            ],
        ],
        'agent evaluation guidance',
    );

    $excludedControllerPaths = [
        'tools/agent-evaluation-controller.php',
        'tools/test-agent-evaluation-controller.php',
    ];

    foreach ($archiveFiles as $archiveFile) {
        if (
            in_array($archiveFile, $excludedControllerPaths, true)
            || str_starts_with($archiveFile, 'tools/agent-evaluation-controller/')
        ) {
            throw new RuntimeException(
                "The maintainer-only agent-evaluation controller escaped into the framework archive: {$archiveFile}",
            );
        }
    }

    foreach (
        [
            $installedFramework . '/tools/agent-evaluation-controller.php',
            $installedFramework . '/tools/agent-evaluation-controller',
            $installedFramework . '/tools/test-agent-evaluation-controller.php',
        ] as $excludedInstalledPath
    ) {
        if (file_exists($excludedInstalledPath) || is_link($excludedInstalledPath)) {
            throw new RuntimeException(
                "The maintainer-only agent-evaluation controller escaped into the installed package: {$excludedInstalledPath}",
            );
        }
    }

    fwrite(STDOUT, "PASS installed agent evaluation guidance distribution\n");
}

function proveInstalledDatabaseSetupGuidanceDistribution(string $project, string $installedFramework): void
{
    /** @var array<string, list<string>> $artifactMarkers */
    $artifactMarkers = [
        $project . '/AGENTS.md' => [
            '## Early database setup gate',
            'Ask one combined clarification: configuration only, connection to an existing server, or project-local server provisioning; and deferred migrations or an application-owned migration foundation.',
            'Local development is context, not authorization to connect to or probe a server, install, provision, or mutate anything.',
            'Resume the ordinary read order after scope is resolved.',
            'An explicit request proceeds without a redundant question; `.ai/change-workflow.md` owns the complete gate.',
        ],
        $project . '/.ai/change-workflow.md' => [
            '## Ambiguous database setup scope',
            'configuration only, connection to an existing server, or project-local server provisioning',
            'deferred migrations or an application-owned migration foundation',
            '> Please setup PostgreSQL as our main DB.',
            'Treat a current `NOT_APPLICABLE` marker as present-state evidence',
        ],
        $project . '/.ai/README.md' => [
            '| Select or set up a database engine | `.ai/change-workflow.md` | prompt and current configuration/data facts before any external action |',
        ],
        $project . '/.ai/configuration.md' => [
            'Database-engine selection does not authorize a connection attempt, server provisioning, or migration adoption.',
            'one separately named factory, final readonly output type, and process identity for each adopted process profile',
        ],
        $project . '/.ai/testing.md' => [
            'Provisioning and production evidence is required only for explicitly selected scopes.',
        ],
        $installedFramework . '/docs/consumer-contract.md' => [
            'Ask all unresolved choices in one concise message',
            'Do not perform external database I/O, provision or mutate a server',
        ],
        $installedFramework . '/docs/configuration.md' => [
            '## Scope database setup before implementation',
            '> Please setup PostgreSQL as our main DB.',
            'should I only add PostgreSQL configuration, connect this project to an existing PostgreSQL server, or provision a project-local PostgreSQL server?',
            'Configuration-only scope records infrastructure injection and connection evidence as deferred and does not create dead wiring.',
            'For PostgreSQL or another engine, first record the exact accepted initial baseline',
            'When migrations are deferred, omit the migration inputs, type, factory, entrypoint, and tests',
            'Provisioning and production evidence is required only for an explicitly selected scope.',
        ],
        $installedFramework . '/docs/evaluation.md' => [
            '## Database setup scope-gate evaluation',
            'A starter not-applicable marker does not answer that adoption question.',
            'no connection attempt or other external database I/O',
            'they do not prove that a particular model follows them or meets a duration target',
        ],
        $installedFramework . '/docs/knowledge-map.md' => [
            '| Select or set up a database engine |',
            'load and prove only the selected slice',
        ],
        $installedFramework . '/docs/guardrails.md' => [
            "It also verifies that the local skeleton and installed framework distribute ADR 037's database setup guidance.",
            'This distribution proof does not establish that an AI asks the scope question, avoids external database I/O, or meets a duration target',
        ],
        $installedFramework . '/templates/application/.ai/change-workflow.md' => [
            '## Ambiguous database setup scope',
            '> Please setup PostgreSQL as our main DB.',
            'An explicit request such as “Provision a project-local PostgreSQL server, configure it, and do not add migrations” proceeds without this scope question.',
        ],
        $installedFramework . '/templates/application/.ai/README.md' => [
            '| Select or set up a database engine | `.ai/change-workflow.md` | prompt and current configuration/data facts before any external action |',
        ],
        $installedFramework . '/templates/application/AGENTS.md' => [
            '## Early database setup gate',
            'Ask one combined clarification: configuration only, connection to an existing server, or project-local server provisioning; and deferred migrations or an application-owned migration foundation.',
            'Local development is context, not authorization to connect to or probe a server, install, provision, or mutate anything.',
            'Resume the ordinary read order after scope is resolved.',
            'An explicit request proceeds without a redundant question; `.ai/change-workflow.md` owns the complete gate.',
        ],
        $installedFramework . '/templates/application/.ai/configuration.md' => [
            'Record only adopted external input contracts.',
            'do not store task scope or task history here',
        ],
        $installedFramework . '/templates/application/.ai/data.md' => [
            '{{ELEVATED_PROFILE_1_HISTORY_OR_ADMIN_NAME_OR_NOT_APPLICABLE}}',
            '{{ELEVATED_PROFILE_1_EFFECTIVE_AUTHORITY_BOUNDARY_OR_NOT_APPLICABLE}}',
        ],
        $installedFramework . '/templates/application/.ai/testing.md' => [
            'Provisioning and production evidence is required only for explicitly selected scopes.',
        ],
    ];

    requireInstalledArtifactMarkers($artifactMarkers, 'database setup guidance');

    fwrite(STDOUT, "PASS installed database setup guidance distribution\n");
}

/**
 * @param list<string> $profileCommand
 * @param array<string, string> $environment
 */
function proveInstalledWorkbenchGuidanceDistribution(
    string $project,
    string $installedFramework,
    array $profileCommand,
    array $environment,
): string
{
    /** @var array<string, list<string>> $artifactMarkers */
    $artifactMarkers = [
        $project . '/.ai/README.md' => [
            '| Change the development Workbench | `.ai/workbench.md` | approved package, checked bootstrap, explicit workspace, and retained tests |',
        ],
        $project . '/.ai/workbench.md' => [
            '`NOT_APPLICABLE(WORKBENCH)`',
            'the dedicated development operating-system identity, inherited environment, independently loaded child CLI configuration',
            'the absence of a Workbench execution timeout or CPU, memory, resource, and operating-system termination isolation',
            'the selected ADR 052 adoption\'s existing application-owned publication operation and recorded delivery/operational entrypoint and process shape',
            'for ADR 024 specifically, its same-connection business-write/job-insert operation and finite one-delivery command',
            'installed `vendor/phpthis/framework/docs/jobs/README.md` and `.ai/jobs.md`',
            'deliberately selected checked profile or exact application adoption record under accepted ADR 052.',
            'Production artifacts install with `--no-dev`',
        ],
        $installedFramework . '/docs/workbench.md' => [
            '# PHPThis Workbench',
            'returns exactly one concrete application-owned object',
            'Composer\\\\Config::disableProcessTimeout',
            'fresh `PHP_BINARY` child',
            'Workbench supplies no execution timeout, CPU limit, memory limit, resource limit, or operating-system termination isolation.',
            'current accepted ADR 041/ADR 024 path',
            'existing application-owned business operation whose same-connection transaction already owns the business write and job insert',
            'Accepted ADR 052 keeps Workbench publication on the selected adoption\'s existing application-owned publication operation',
            'An ADR 024 SQLite adoption retains the same-connection operation and finite one-delivery command above.',
            'Follow the [backend-neutral jobs index](jobs/README.md) and the deliberately selected profile or application adoption record.',
            'Workbench supplies no `dispatch()`',
            'Workbench output is exploratory evidence, not application validity evidence.',
        ],
        $installedFramework . '/docs/decisions/041-optional-development-workbench.md' => [
            'Status: accepted',
            'optional separate `phpthis/workbench` development package',
            'The generated child program is not a security boundary.',
            'Workbench provides no execution timeout, CPU limit, memory limit, resource limit, or operating-system termination isolation',
            'This decision adds no framework-core PHP, runtime dependency, command, checker rule, `PHT` diagnostic',
        ],
        $installedFramework . '/docs/consumer-contract.md' => [
            '## Optional development Workbench',
            'Existing applications need not add `.ai/workbench.md`',
            'This changes neither the carried-forward Workbench contract nor Strict Profile version 3',
        ],
        $installedFramework . '/docs/security.md' => [
            '## Workbench limits',
            'Workbench is not a sandbox, dry run, redactor, authorization layer, output bound, environment verifier, or production-safety control.',
            'Workbench also provides no execution timeout, CPU limit, memory limit, resource limit, or operating-system termination isolation.',
        ],
        $installedFramework . '/docs/jobs/sqlite.md' => [
            'existing adopted business operation',
            'recorded finite tested one-delivery console command',
        ],
        $installedFramework . '/templates/application/.ai/workbench.md' => [
            '{{WORKBENCH_ADOPTION_OR_NOT_APPLICABLE}}',
            '{{WORKBENCH_EXCLUDED_AUTHORITY_OR_NOT_APPLICABLE}}',
            '{{WORKBENCH_RESOURCE_LIMITS_OR_NOT_APPLICABLE}}',
            '{{WORKBENCH_SIDE_EFFECT_POLICY_OR_NOT_APPLICABLE}}',
            '{{WORKBENCH_JOB_PATH_OR_NOT_APPLICABLE}}',
            'Selected ADR 052 adoption\'s existing application-owned publication operation and recorded delivery/operational entrypoint and process shape; for ADR 024 specifically, its same-connection business-write/job-insert operation and finite one-delivery command',
            'installed `vendor/phpthis/framework/docs/jobs/README.md` and `.ai/jobs.md`',
            'exact application adoption record under accepted ADR 052.',
        ],
    ];

    requireInstalledArtifactMarkers($artifactMarkers, 'Workbench guidance');

    $consumerComposer = file_get_contents($project . '/composer.json');

    if (!is_string($consumerComposer)) {
        throw new RuntimeException('Unable to read the installed skeleton Composer manifest for Workbench proof.');
    }

    if (
        str_contains($consumerComposer, '"phpthis/workbench"')
        || is_file($project . '/vendor/bin/phpthis-workbench')
    ) {
        throw new RuntimeException(
            'The skeleton adopted phpthis/workbench without explicit application approval and verified Composer-source availability.',
        );
    }

    $workbenchContext = $project . '/.ai/workbench.md';
    $optionalContextProof = $project . '/.ai/workbench.md.optional-context-proof';

    if (!is_file($workbenchContext) || file_exists($optionalContextProof)) {
        throw new RuntimeException('Unable to prepare the optional Workbench context compatibility proof.');
    }

    if (!rename($workbenchContext, $optionalContextProof)) {
        throw new RuntimeException('Unable to remove the optional Workbench context for compatibility proof.');
    }

    try {
        $withoutWorkbenchContext = runProcess($profileCommand, $project, $environment);
        requireSuccess(
            $withoutWorkbenchContext,
            'The installed checker rejected a consumer only because .ai/workbench.md was absent.',
        );
        requireOutputContains($withoutWorkbenchContext, 'PASS PHPThis application check');
    } finally {
        if (!rename($optionalContextProof, $workbenchContext)) {
            throw new RuntimeException('Unable to restore the optional Workbench context after compatibility proof.');
        }
    }

    fwrite(STDOUT, "PASS installed Workbench guidance distribution\n");

    return 'installed-workbench-guidance-proved';
}

function proveInstalledStartupProbeGuidanceDistribution(string $project, string $installedFramework): void
{
    /** @var array<string, list<string>> $artifactMarkers */
    $artifactMarkers = [
        $project . '/.ai/README.md' => [
            '| Change liveness, readiness, deployment, or runtime operation | `.ai/operations.md` | entrypoint, exact probe claim, owners, bounds, and evidence |',
        ],
        $project . '/.ai/operations.md' => [
            '`GET /health` is the starter liveness route; no readiness route exists.',
            'It does not establish external-service-independent liveness because the deployment-configured `error_log` destination and its latency are unverified.',
            'covering success, mapped failure, unknown failure, captured summaries, throwing-sink isolation, and the real front controller.',
            '`Connection::connect()` constructs PDO eagerly and may fail during composition',
            'Do not preserve a liveness claim through a hidden bypass or second HTTP execution path.',
        ],
        $project . '/.ai/observability.md' => [
            'calls deployment-configured `error_log` synchronously before the coordinator returns',
            'throwing-sink response isolation',
        ],
        $project . '/.ai/testing.md' => [
            'This proves the current HTTP composition and response path, not external-service-independent liveness',
            'the coordinator invokes deployment-configured `error_log` synchronously and no destination or latency bound is recorded.',
            'do not treat connection construction as database-authority or complete-readiness evidence.',
        ],
        $installedFramework . '/docs/configuration.md' => [
            '### Eager composition and probe semantics',
            '`Connection::connect()` constructs native `PDO` immediately rather than returning a deferred handle.',
            'Depending on the selected driver and DSN, construction may perform database, filesystem, or network I/O and may fail during composition.',
            'Successful connection construction is also not evidence of schema compatibility, migration completion, capacity, per-operation database authority, or complete application readiness.',
            'Failure isolation that preserves a selected response does not by itself bound a synchronous sink\'s latency or make that probe external-service-independent.',
            'Do not disguise a dependency bypass as the ordinary application bootstrap or add a second hidden HTTP execution path.',
        ],
        $installedFramework . '/docs/knowledge-map.md' => [
            'Define, change, or review startup, liveness, dependency health, or readiness semantics',
            'verify that no framework probe API, lazy connection, hidden bypass, or second HTTP execution path was introduced',
        ],
        $installedFramework . '/docs/vocabulary.md' => [
            '| external-service-independent liveness |',
            '| readiness | application-owned operational claim that its recorded conditions for receiving traffic are satisfied |',
        ],
        $installedFramework . '/docs/guardrails.md' => [
            'A separate installed distribution proof checks the eager-composition and probe-semantics clarification',
            'the current starter does not claim external-service independence while its deployment-configured `error_log` destination and latency remain unverified',
            'does not connect to a service, prove that a deployment classified a probe correctly, establish dependency availability or traffic readiness',
        ],
        $installedFramework . '/templates/application/.ai/README.md' => [
            '| Change liveness, readiness, deployment, or runtime operation | `.ai/operations.md` | entrypoint, exact probe claim, owners, bounds, and evidence |',
        ],
        $installedFramework . '/templates/application/.ai/operations.md' => [
            '{{HEALTH_AND_READINESS_PATHS}}',
            '`Connection::connect()` constructs PDO eagerly and, depending on the selected driver and DSN, may perform I/O or fail during composition.',
            'must not be described as external-service-independent liveness.',
        ],
        $installedFramework . '/templates/application/.ai/testing.md' => [
            'Every adopted health, readiness, or non-HTTP probe proves the exact claim recorded in `.ai/operations.md`',
            'A caught sink failure proves response isolation, not a latency bound or independence from that sink\'s destination.',
            'Connection construction alone is not exact-statement database-authority or complete-readiness evidence.',
        ],
    ];

    requireInstalledArtifactMarkers($artifactMarkers, 'startup and probe guidance');

    fwrite(STDOUT, "PASS installed startup and probe guidance distribution\n");
}

/** @param array<string, string> $environment */
function proveInstalledRequestHandlerDecorator(string $project, array $environment): string
{
    $proofPath = $project . '/installed-handler-decorator-proof.php';
    writeFile(
        $proofPath,
        <<<'PHP'
<?php

declare(strict_types=1);

use PHPThis\Application;
use PHPThis\Http\Request;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\Response;
use PHPThis\Routing\Route;
use PHPThis\Routing\Router;

require __DIR__ . '/vendor/autoload.php';

final class InstalledDecoratorTrace
{
    /** @var list<string> */
    private array $steps = [];

    private int $downstreamCalls = 0;

    private ?int $decoratorRequestId = null;

    private ?int $downstreamRequestId = null;

    public function recordBefore(Request $request): void
    {
        $this->steps[] = 'before';
        $this->decoratorRequestId = spl_object_id($request);
    }

    public function recordAfter(): void
    {
        $this->steps[] = 'after';
    }

    public function recordHandler(Request $request): void
    {
        $this->steps[] = 'handler';
        $this->downstreamCalls++;
        $this->downstreamRequestId = spl_object_id($request);
    }

    /** @return list<string> */
    public function steps(): array
    {
        return $this->steps;
    }

    public function downstreamCalls(): int
    {
        return $this->downstreamCalls;
    }

    public function decoratorRequestId(): ?int
    {
        return $this->decoratorRequestId;
    }

    public function downstreamRequestId(): ?int
    {
        return $this->downstreamRequestId;
    }
}

final readonly class InstalledHeaderDecorator implements RequestHandler
{
    public function __construct(
        private RequestHandler $downstream,
        private InstalledDecoratorTrace $trace,
    ) {
    }

    public function handle(Request $request): Response
    {
        $this->trace->recordBefore($request);
        $response = $this->downstream->handle($request);
        $this->trace->recordAfter();

        return new Response(
            $response->status,
            [...$response->headers, 'X-Decorator-Proof' => 'present'],
            $response->body,
            $response->cookies,
            $response->fileBody,
        );
    }
}

final readonly class InstalledRejectingDecorator implements RequestHandler
{
    public function __construct(
        private RequestHandler $downstream,
        private bool $reject,
    ) {
    }

    public function handle(Request $request): Response
    {
        if ($this->reject) {
            return new Response(429, ['Cache-Control' => 'no-store'], "Rejected\n");
        }

        return $this->downstream->handle($request);
    }
}

final readonly class InstalledDecoratedHandler implements RequestHandler
{
    public function __construct(private InstalledDecoratorTrace $trace)
    {
    }

    public function handle(Request $request): Response
    {
        $this->trace->recordHandler($request);

        return new Response(200, ['Cache-Control' => 'no-store'], "Decorated\n");
    }
}

function assertInstalledDecoratedResponse(
    Response $response,
    InstalledDecoratorTrace $trace,
): void {
    if (
        $response->status !== 200
        || $response->headers !== [
            'Cache-Control' => 'no-store',
            'X-Decorator-Proof' => 'present',
        ]
        || $response->body !== "Decorated\n"
        || $trace->steps() !== ['before', 'handler', 'after']
        || $trace->downstreamCalls() !== 1
        || $trace->decoratorRequestId() === null
        || $trace->decoratorRequestId() !== $trace->downstreamRequestId()
    ) {
        throw new RuntimeException('Installed route decorator did not preserve explicit composition.');
    }
}

function assertInstalledDecoratorRejection(
    Response $response,
    InstalledDecoratorTrace $trace,
): void {
    if ($response->status !== 429 || $trace->downstreamCalls() !== 1) {
        throw new RuntimeException('Installed rejecting decorator entered downstream work.');
    }
}

function assertInstalledDecoratorIsolation(InstalledDecoratorTrace $trace): void
{
    if (
        $trace->downstreamCalls() !== 1
        || $trace->steps() !== ['before', 'handler', 'after']
    ) {
        throw new RuntimeException('Route miss or method rejection entered decorated work.');
    }
}

$trace = new InstalledDecoratorTrace();
$application = new Application(new Router([
    new Route(
        'GET',
        '/decorated',
        new InstalledHeaderDecorator(
            new InstalledDecoratedHandler($trace),
            $trace,
        ),
    ),
    new Route(
        'GET',
        '/rejected',
        new InstalledRejectingDecorator(
            new InstalledDecoratedHandler($trace),
            true,
        ),
    ),
    new Route('GET', '/plain', new InstalledDecoratedHandler($trace)),
]));
$request = new Request('GET', '/decorated');
$response = $application->handle($request);
assertInstalledDecoratedResponse($response, $trace);

$rejectedResponse = $application->handle(new Request('GET', '/rejected'));
assertInstalledDecoratorRejection($rejectedResponse, $trace);

$application->handle(new Request('POST', '/decorated'));
$application->handle(new Request('GET', '/missing'));
assertInstalledDecoratorIsolation($trace);

fwrite(STDOUT, "PASS installed request-handler decorator composition\n");
PHP,
    );

    $result = runProcess([PHP_BINARY, $proofPath], $project, $environment);
    requireSuccess($result, 'The installed framework failed request-handler decorator proof.');
    requireOutputContains($result, 'PASS installed request-handler decorator composition');

    return $proofPath;
}
