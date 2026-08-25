# PHPThis Agent Evaluation Controller v0.2

This maintainer-only controller implements the accepted ADR 048 lifecycle:

```text
prepare -> generate -> freeze -> score -> validate -> retain -> cleanup
```

It remains under export-excluded `tools/` and changes no framework runtime, public API, Consumer Contract, Strict Profile, application checker, skeleton requirement, or Composer dependency.

## Fixed composition

`tools/agent-evaluation-controller.php` explicitly requires, in order:

1. `contract.php`
2. `workspace.php`
3. `process.php`
4. `codex.php`
5. `scoring.php`
6. `controller.php`

There is no module or task discovery, runner selector, provider facade, dependency-injection container, arbitrary command setting, or arbitrary output-path setting. `process.php` is the only controller file that owns native process primitives. The unchanged v0.1 task inventory remains the sole task authority, and v0.2 accepts only `change.simple-ping` revision 24 with `comparative_claims: false`.

## Executable boundary

`composer test:agent-evaluation-controller` runs the complete deterministic `fake-codex` lifecycle with synthetic inputs. It performs no model request, needs no credential or OCI engine, contacts no external endpoint, and executes no AI-authored candidate. The fixture exercises controller state and evidence contracts; it is not an isolation certification, hidden holdout, or comparative evaluation.

Run the accepted v0.2 surfaces from the framework repository root:

```bash
composer test:agent-evaluation-controller
php tools/agent-evaluation-controller.php validate
php tools/agent-evaluation-controller.php run 00000000000000000000000000000042
```

The first command exercises the complete fake lifecycle. `validate` checks the fixed controller installation. The `run` example deliberately exits with `AGENT_EVALUATION_CONTROLLER_LIVE_CODEX_UNAVAILABLE`; it neither calls a model nor falls back to host execution.

The repository entrypoint can validate this fixed installation. A live run is intentionally unavailable in v0.2. The only accepted future live adapter is `codex-exec`, and it must fail closed until a separately implemented and proved Docker-compatible OCI image pinned by digest and host-only `responses-api-run-proxy` satisfy ADR 048. Direct host execution, native macOS execution, `sandbox-exec`, a raw provider credential, and fallback runners are forbidden.

## Evidence and cleanup

The controller prepares a standalone candidate without Git metadata, records the source and dependency manifests, freezes it into a separate read-only scoring copy before the scorer is used, destroys the generation workspace before scoring, derives v0.1-compatible run and score records from observed synthetic stage results, validates those records through the unchanged v0.1 validator, retains bounded hashed evidence, and removes every disposable workspace.

Cleanup is part of the result. The synthetic process proof records only that the original process group is absent; it cannot prove termination of a child that escapes into another native session, and it is not a substitute for the future OCI container-destruction proof. An unverified process group, disposable-resource removal failure, post-freeze mutation, artifact mismatch, workspace-policy violation, scorer-timing violation, or resource-limit failure makes the synthetic run fail. Retained evidence contains structural synthetic facts and hashes, never credentials or ambient environment values.

Stable boundary markers:

- `AGENT_EVALUATION_CONTROLLER_VERSION(2)`
- `AGENT_EVALUATION_CONTROLLER_OCI_ONLY`
- `AGENT_EVALUATION_CONTROLLER_FAKE_RUNNER_CI_ONLY`
- `AGENT_EVALUATION_CONTROLLER_NO_NATIVE_FALLBACK`
