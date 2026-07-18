# Canonical vocabulary

| Term | Meaning | Do not alias as |
| --- | --- | --- |
| route | HTTP method, literal path, handler | endpoint definition, action map |
| handler | object with `handle(Request): Response` | controller, action, responder |
| connection | instrumented PDO boundary | DB facade, query builder |
| query budget | maximum statements for one connection/request | throttle, limiter |
| projection | final readonly typed value parsed from a selected database row | model, entity, active record |
| command | final readonly typed input parsed at an external boundary | request array, payload bag |
| Strict Profile | versioned subset of PHP accepted by the complete check gate | style guide, optional lint |
| composition root | file that manually constructs the object graph | provider, container config |
| request | immutable normalized HTTP input | context, payload |
| request boundary | one bounded runtime-reader, handler, and exact error-map sequence | middleware, pipeline |
| error response registry | exact exception-class to immutable response map | global exception helper |
| response | immutable HTTP output | result, reply |
| application AI context | project-owned root `AGENTS.md` and task-routed `.ai/` guides | framework maintainer context, evaluation harness |
| AI-first authoring | workflow in which AI is the expected primary code author under human direction | autonomous approval, AI-only development |
| AI knowledge interface | AI grounded in the installed contract, knowledge map, application context, source, and tests | model memory, framework manual replacement without evidence |
| human accountability | human ownership of intent, consequential decisions, authorization, and outcomes | manual authorship of every line, responsibility delegated to AI |

Stable vocabulary narrows AI search and reduces duplicate abstractions.
