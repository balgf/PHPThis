# ADR 035: Bounded Alpha 4 release scope

Status: accepted

## Context

Alpha 3 carried Consumer Contract version 7, Strict Profile version 2, permanent diagnostics `PHT001` through `PHT006`, the PHP 8.4.x runtime boundary, and the complete bounded Alpha 2 surface forward while adding only ADR 030's report-only consumer duplication advisory. Four accepted changes now need one honest prerelease boundary rather than being announced as unrelated additions.

ADR 032 added canonical UUID and ULID route types and advanced the Consumer Contract to version 8. ADR 033 accepted one narrowly bounded route-local application-owned request-handler decorator pattern and advanced the Consumer Contract to version 9. ADR 034 documented one independently proved application-owned WebSocket integration profile without adding a framework real-time runtime. The framework repository's PHPStan process also exhausted its former 256 MiB command limit in CI after the expanded proof surface, so that framework-analysis limit was raised to 512 MiB without changing analysis level, rules, runtime behavior, or the consumer checker contract.

A new prerelease must expose the Contract version 7 to version 9 migration, distinguish framework routing behavior from optional application-owned patterns, and preserve PHPThis's permanent exclusions. It must not turn the independent WebSocket recipe into framework runtime behavior or describe the request-handler decorator as generic middleware.

On 2026-07-23 in Asia/Manila, the accountable human approved this bounded Alpha 4 scope and exact release identity.

## Decision

Alpha 4 is accepted as the bounded rollup of the changes after Alpha 3:

- ADR 032 adds only the fixed lowercase `uuid` and `ulid` route parameter types, type-specific immutable access, canonical validation, and deterministic matching. It advances the Consumer Contract from version 7 to version 8 and raises the core-source ceiling from 2,500 to 2,600 physical lines for that bounded routing change only.
- ADR 033 advances the Consumer Contract from version 8 to version 9 by accepting one optional application-owned request-handler decorator pattern. It adds no core class, runtime behavior, runtime dependency, diagnostic, framework middleware interface, pipeline, registry, discovery, context bag, or automatic composition.
- ADR 034 keeps WebSocket integration wholly application-owned and separate from PHPThis HTTP. Its pinned-runtime consumer proof is evidence for the documented boundary, not a native PHPThis WebSocket server, client, event loop, connection manager, daemon, supervisor, channel, broadcaster, pub/sub, retry, replay, acknowledgement, reconnect, or delivery API.
- The framework repository's PHPStan command receives a 512 MiB analysis limit instead of 256 MiB so the unchanged maximum-level framework gate can complete in CI. This is development-gate capacity, not a PHP runtime requirement, consumer PHPStan configuration surface, weaker analysis rule, baseline, suppression, or framework runtime behavior.

The exact approved identity is:

- Composer version: `0.1.0-alpha.4`
- framework tag: `v0.1.0-alpha.4`
- skeleton tag: `v0.1.0-alpha.4`

Publication state is external. This decision accepts the bounded source claim and exact identity; it does not assert that either tag, either Packagist package, either GitHub prerelease, or the public installation path exists. Every external operation remains subject to the ordered gates, accountable-human authorization, and recorded evidence in `RELEASING.md`.

Alpha 4 carries forward unchanged:

- Strict Profile version 2 and permanent diagnostics `PHT001` through `PHT006`;
- the supported PHP 8.4.x Composer range `~8.4.0`;
- zero third-party framework runtime dependencies;
- explicit manual composition, immutable HTTP values, visible direct SQL through `Connection`, bounded query evidence, and separately certified PDO transport; and
- every accepted Alpha 3 and Alpha 2 ownership boundary not explicitly changed by ADR 032 through ADR 034.

Alpha 4 does not add or permit an ORM, Active Record, lazy loading, model or repository layer, query builder, generated SQL, binding helper, service container, facade, global helper, automatic discovery, observer magic, macro system, dynamic proxy, generic middleware, native WebSocket runtime, or another hidden execution path.

An Alpha 3 consumer upgrades sequentially from Consumer Contract version 7 through versions 8 and 9:

1. For version 8, retain literal, `positive-int`, and genuinely opaque `token` routes. Audit identifier-shaped token routes. Change only routes whose declared representation is a UUID or ULID to the corresponding fixed route type and `PathParameters` accessor, immediately wrap the unchanged value in an application-owned concrete identifier, apply narrower domain policy before downstream work, and add the canonical, malformed, alternate-spelling, overlap, wrong-accessor, method, and zero-downstream-work evidence required by the contract. This implies no data rewrite, schema change, identifier generator, automatic binding, lookup, or storage policy.
2. For version 9, an application that does not adopt request-handler decorators changes no source. An adopting application uses final named application classes implementing only `RequestHandler`, gives each exactly one downstream `RequestHandler`, keeps the complete nesting visible beside the affected `Route`, removes any generic pipeline or context mechanism, and proves exact request and exception identity, zero-or-one downstream invocation, response preservation, order, short circuit, and bounded named I/O.
3. WebSockets remain optional application architecture. An application without them changes nothing. An adopting application makes its own accountable decision, pins a mature third-party runtime, keeps a separate composition root and process, records exact policy and resource bounds, and supplies its own real process and socket evidence. It never converts frames into PHPThis `Request` or `Response` values.
4. Rerun the complete application gate on PHP 8.4.x. Consumers do not add or change a PHPStan configuration or memory policy because dependency root scripts are not inherited and the 512 MiB change belongs to the framework repository's release gate.

Before the framework tag is created, one clean pushed candidate must pass every applicable local and CI gate in `RELEASING.md`, including maximum-level PHPStan, framework tests, installed-consumer proof, exact package inventory, PDO transport certification, and candidate metadata review. The framework distribution must be proved before the dedicated skeleton is updated and locked to the exact approved prerelease. Both public artifacts and one clean Packagist-preferred `composer create-project` installation must pass before either release is announced. External evidence records exact commits, dates, CI runs, distribution references, public-installation output, and accountable-human publication authorization without mutating the candidate being proved.

## Consequences

Alpha 4 gives consumers canonical UUID and ULID routing plus two explicitly bounded application-owned architecture profiles while keeping the framework runtime small and literal. Contract version 9 makes the route-local decorator obligations reviewable without creating middleware, and the WebSocket profile makes transport and process ownership reviewable without creating a second PHPThis runtime.

The Contract version 7 to version 9 change requires an explicit route audit even when an application ultimately makes no source change. Applications adopting either optional pattern accept additional application-owned documentation, composition, operational policy, and test responsibility. The WebSocket consumer proof does not establish production capacity, TLS, proxy, supervisor, broker, affinity, scaling, or availability guarantees for another deployment.

Alpha 4 remains experimental prerelease software. It makes no production-readiness, backward-compatibility, support-SLA, security-response-SLA, complete-CRUD, portable application-SQL, deployment, capacity, or exactly-once-effect claim.

## Reconsider when

A supported PHP minor, Strict Profile version, permanent diagnostic, runtime dependency, core ownership boundary, or Consumer Contract requirement changes; independent consumer evidence demonstrates that one accepted Alpha 4 boundary causes a concrete correctness or review failure; or publication evidence reveals a mismatch between the approved source, package inventories, public distributions, and installation path. Reconsider the smallest affected contract and publish a separately approved identity rather than moving either Alpha 4 tag or silently expanding this scope.
