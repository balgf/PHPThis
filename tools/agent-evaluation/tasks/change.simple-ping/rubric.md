# `change.simple-ping` rubric

`AGENT_EVALUATION_PUBLIC_SMOKE_ONLY`

This revision is a visible harness smoke case. It verifies one small PHPThis application change and cannot support a comparison between models, prompts, context strategies, or skills.

## Admissibility

A run is admissible only when:

- the prompt, task manifest, base fixture, prepared dependencies, event record, and scorer retain their recorded hashes;
- every changed path is permitted by `workspace_policy`, every protected path is unchanged, the changed-file and line budgets are met, and the candidate adds no symlink, submodule, special file, or unexpected executable;
- no write escapes the disposable candidate root and no unapproved external action occurs; and
- the candidate patch is frozen before any official external scorer is made available.

An inadmissible run has `automated_status: fail` regardless of its numeric dimensions.

## Mandatory checks

The v1 score record uses these mandatory keys:

- `manifest_valid`: the explicit inventory, manifest, prompt, and public-scorer hashes are valid;
- `workspace_policy`: the candidate change satisfies every path and size bound;
- `application_check`: the unchanged `composer check` command exits successfully; and
- `public_scorer`: `public/holdout.php.fixture` exits successfully against the prepared candidate application; and
- `resource_bounds`: separate controller-owned inspection proves the dependency-free handler performs none of the prohibited I/O or state work that the visible scorer cannot observe.

Every mandatory value must be `true` for `automated_status: pass`.

## Dimensions

- `observable_behavior` — `40%`: exact successful `GET /ping` behavior and unchanged exact `GET /health` behavior.
- `boundary_behavior` — `20%`: routing-owned `POST /ping` rejection and unknown-route behavior retain their required headers.
- `resource_bounds` — `15%`: `PingHandler` is dependency-free and the operation performs no database, session, server-side cache, configuration, decorator, filesystem, process, or external I/O work. The public scorer does not prove this dimension by itself.
- `application_gate` — `15%`: the complete unchanged application validity gate passes.
- `change_locality` — `10%`: the agent consults the installed current guide and changes only the existing route-area manifest, one handler, and the nearest behavior test, without changing root composition or dependencies.

Calculate the integer automated score as:

```text
intdiv(
    40 * observable_behavior
    + 20 * boundary_behavior
    + 15 * resource_bounds
    + 15 * application_gate
    + 10 * change_locality,
    100
)
```

The automated status is `pass` only when the run is admissible, every mandatory check is true, and `weighted_score` is at least `85`. Human review remains a separate `pending`, `pass`, or `fail` result and must not be folded into the automated score.

## Public scorer limits

The checked-in scorer is visible to the agent and tests only observable HTTP behavior. It does not prove hidden-test resistance, scorer isolation, tool or filesystem confinement, token accounting, absence of unrecorded external attempts, or comparative performance. An official evaluation supplies a separately versioned scorer only after generation; a future official-evaluation schema revision must identify and hash that scorer instead of reusing this v1 public-smoke score contract.
