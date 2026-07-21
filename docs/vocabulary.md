# Canonical vocabulary

| Term | Meaning | Do not alias as |
| --- | --- | --- |
| route | HTTP method, explicit literal or at-most-two-full-segment typed path declaration, and handler | discovered endpoint, action map, regular-expression rule |
| route match | immutable selected route plus immutable path parameters | controller arguments, binding result |
| path parameters | empty routing metadata or at most two validated `positive-int` or bounded `token` values carried by the matched request copy and read through type-specific access | request bag, domain context, automatic model binding |
| handler | object with `handle(Request): Response` | controller, action, responder |
| connection | instrumented PDO boundary | DB facade, query builder |
| PDO transport | native-driver connection, binding, fetch, transaction, budget, and trace behavior certified without SQL translation | database abstraction, portable dialect |
| application-owned SQL dialect | complete engine-specific SQL and database semantics recorded and tested by the consuming application | framework query language, automatic dialect selection |
| SQL data | a complete value passed through one unique named parameter | interpolated value, escaped SQL text |
| finite SQL structure | one or more non-blank compile-time constant statements selected from code-owned reviewed choices | arbitrary SQL string, sanitized identifier |
| finite application data path | operation-owned parser, complete engine-specific statements, explicit named parameter arrays, projections, resource bounds, and tests for one data behavior | ORM, repository, generic paginator, generated SQL, dialect abstraction |
| runtime database authority | engine-specific objects and actions granted to one runtime connection | schema ownership, migration credential, administrator access |
| CRUD reference profile | optional feature-first placement and naming guidance for explicit application operations | generic CRUD engine, enforced directory layout |
| query budget | maximum statements for one connection/request | throttle, limiter |
| terminal request summary | one closed redacted application-owned event built after response selection with correlation, status, duration, and bounded finite database-source evidence | request log bag, audit record, per-query event |
| correlation ID | application-generated 128 random bits encoded as exactly 32 lowercase hexadecimal characters and propagated as `X-Request-ID` | trusted caller identity, trace payload, domain identifier |
| request-summary sink | application-owned destination boundary invoked once with the final summary | framework logger, facade, global helper |
| sink invocation attempt | one synchronous call after response selection whose failure cannot alter the response and whose return does not prove durable delivery | guaranteed log, retry policy, delivery receipt |
| database source | one bounded code-owned label paired with a distinct connection budget and trace in the terminal summary | connection registry, DSN metadata, database facade |
| durable-job envelope | bounded versioned stored JSON parsed as untrusted input into one concrete readonly job value | serialized object, class name, arbitrary event payload |
| commit-visible job publication | business change and job insert made durable by the same `Connection`, explicit transaction, and database commit | enqueue callback, after-commit hook, cross-connection atomicity |
| job lease | finite claim ownership identified by job, leased state, attempt number, opaque token, and unexpired deadline | permanent lock, exactly-once ownership, heartbeat service |
| one-shot worker | fresh application process or invocation that claims and finalizes at most one delivery before exit | daemon loop, framework worker, queue consumer abstraction |
| at-least-once delivery | a durable job may be delivered again after failure or lease expiry, so its effect must tolerate duplicates | exactly-once execution, unique attempt, single handler call |
| dead letter | terminal job state with one finite redacted diagnostic code after poison input or exhausted delivery | exception archive, automatic replay queue, failure log payload |
| application migration | one permanent engine-specific forward schema change owned and explicitly invoked by the application | framework migration, discovered script, reversible schema object |
| migration manifest | finite reviewed application source that names concrete migration steps in permanent order and invokes pending private methods without database I/O in a loop | directory scan, registry, automatic discovery |
| migration ledger | bounded inspectable table of committed manifest position, permanent identifier, content checksum, and explicitly sourced timestamp | executable migration source, SQL store, rollback history |
| migration drift | mismatch between validated committed ledger history and the current permanent manifest identity, order, or checksum-covered content | pending migration, automatic repair target |
| projection | final readonly typed value parsed from a selected database row | model, entity, active record |
| command | final readonly typed input parsed at an external boundary | request array, payload bag |
| Strict Profile | versioned subset of PHP accepted by the complete check gate | style guide, optional lint |
| composition root | file that manually constructs the object graph | provider, container config |
| request | immutable normalized HTTP input | context, payload |
| request boundary | one bounded runtime-reader, handler, and exact error-map sequence | middleware, pipeline |
| request upload | immutable one-file transport value whose client filename and media type are explicitly untrusted | uploaded-file helper, storage object, trusted document |
| error response registry | exact exception-class to immutable response map | global exception helper |
| response | immutable HTTP output | result, reply |
| local file body | immutable absolute local path plus expected byte count for exact fixed-chunk response emission | generic stream, storage abstraction, callback body |
| response cookie | validated cookie value emitted as its own `Set-Cookie` field | encoded header string, cookie array convention |
| session lifecycle | one lazy request-scoped boundary over PHP's certified native file session engine | helper, middleware, session repository |
| session snapshot | bounded immutable scalar or `null` state returned by the lifecycle | session bag, domain object store |
| session unavailable | explicit stale-mutation failure that emits no competing cookie | automatic anonymous replacement, silent retry |
| typed application session service | one narrowly named application-owned meaning and non-overlapping key set placed in front of the single `SessionLifecycle` | generic key-value helper, authentication framework |
| HTTP cache policy | explicit response-specific `Cache-Control`, validator, and `Vary` behavior | server-side data cache, automatic middleware default |
| application cache service | narrowly named typed application-owned cache-aside boundary with one recorded backend and policy | cache facade, generic cache bag, transparent query cache |
| authoritative data path | source-of-truth read or committed write path that remains correct without a cache | warm-cache shortcut, cached truth |
| stale-refill race | cache-aside ordering where an in-flight miss reads old authoritative data and repopulates it after a concurrent writer commits and invalidates | invalidation failure, ordinary cache miss |
| application AI context | project-owned root `AGENTS.md` and task-routed `.ai/` guides | framework maintainer context, evaluation harness |
| AI-first authoring | workflow in which AI is the expected primary code author under human direction | autonomous approval, AI-only development |
| AI knowledge interface | AI grounded in the installed contract, knowledge map, application context, source, and tests | model memory, framework manual replacement without evidence |
| human accountability | human ownership of intent, consequential decisions, authorization, and outcomes | manual authorship of every line, responsibility delegated to AI |

Stable vocabulary narrows AI search and reduces duplicate abstractions.
