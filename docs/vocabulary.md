# Canonical vocabulary

| Term | Meaning | Do not alias as |
| --- | --- | --- |
| route | HTTP method, literal path, handler | endpoint definition, action map |
| handler | object with `handle(Request): Response` | controller, action, responder |
| connection | instrumented PDO boundary | DB facade, query builder |
| PDO transport | native-driver connection, binding, fetch, transaction, budget, and trace behavior certified without SQL translation | database abstraction, portable dialect |
| application-owned SQL dialect | complete engine-specific SQL and database semantics recorded and tested by the consuming application | framework query language, automatic dialect selection |
| SQL data | a complete value passed through one unique named parameter | interpolated value, escaped SQL text |
| finite SQL structure | one or more non-blank compile-time constant statements selected from code-owned reviewed choices | arbitrary SQL string, sanitized identifier |
| runtime database authority | engine-specific objects and actions granted to one runtime connection | schema ownership, migration credential, administrator access |
| CRUD reference profile | optional feature-first placement and naming guidance for explicit application operations | generic CRUD engine, enforced directory layout |
| query budget | maximum statements for one connection/request | throttle, limiter |
| projection | final readonly typed value parsed from a selected database row | model, entity, active record |
| command | final readonly typed input parsed at an external boundary | request array, payload bag |
| Strict Profile | versioned subset of PHP accepted by the complete check gate | style guide, optional lint |
| composition root | file that manually constructs the object graph | provider, container config |
| request | immutable normalized HTTP input | context, payload |
| request boundary | one bounded runtime-reader, handler, and exact error-map sequence | middleware, pipeline |
| error response registry | exact exception-class to immutable response map | global exception helper |
| response | immutable HTTP output | result, reply |
| response cookie | validated cookie value emitted as its own `Set-Cookie` field | encoded header string, cookie array convention |
| session lifecycle | one lazy request-scoped boundary over PHP's certified native file session engine | helper, middleware, session repository |
| session snapshot | bounded immutable scalar or `null` state returned by the lifecycle | session bag, domain object store |
| session unavailable | explicit stale-mutation failure that emits no competing cookie | automatic anonymous replacement, silent retry |
| typed application session service | one narrowly named application-owned meaning and non-overlapping key set placed in front of the single `SessionLifecycle` | generic key-value helper, authentication framework |
| application AI context | project-owned root `AGENTS.md` and task-routed `.ai/` guides | framework maintainer context, evaluation harness |
| AI-first authoring | workflow in which AI is the expected primary code author under human direction | autonomous approval, AI-only development |
| AI knowledge interface | AI grounded in the installed contract, knowledge map, application context, source, and tests | model memory, framework manual replacement without evidence |
| human accountability | human ownership of intent, consequential decisions, authorization, and outcomes | manual authorship of every line, responsibility delegated to AI |

Stable vocabulary narrows AI search and reduces duplicate abstractions.
