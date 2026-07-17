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
| response | immutable HTTP output | result, reply |

Stable vocabulary narrows AI search and reduces duplicate abstractions.
