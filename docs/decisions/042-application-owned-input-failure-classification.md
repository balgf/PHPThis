# ADR 042: Application-owned input failure classification

Status: accepted

## Context

ADR 021 established one operation-owned parser from bounded external input to a final readonly request or command. Its first executable proof deliberately mapped every rejected Create representation to one generic `400 invalid_request`, while leaving a different status to application policy. That conservative proof did not teach an AI how to distinguish an unreadable or structurally invalid request from a correctly shaped and typed request whose field values violate the operation contract.

Using `400` for both categories is permitted only when an application has explicitly recorded that different public contract. It is no longer the PHPThis authoring default. The default must remain precise enough that its result does not depend on submitted property order or on which field happens to be validated first.

## Decision

For application-owned structured request-body content, PHPThis adopts this canonical authoring default:

- `400 invalid_request` means the representation is malformed or its complete payload structure is invalid. This includes malformed JSON or UTF-8, excessive parser depth, a wrong top-level kind, missing or unknown fields, disallowed explicit `null`, a wrong native field type, and a wrong nested container or item shape.
- `422 unprocessable_content` means the complete field set, nullability, native types, and nested shapes are correct, but at least one value violates an operation-owned grammar, range, length, enum, date, canonical-representation, or cross-field rule.
- `413 request_body_too_large` remains the response for a whole request or operation body that exceeds its recorded byte limit.
- `415 unsupported_media_type` remains the response when the selected content type is not accepted.

The operation-specific parser completes its whole shape and native-type pass before beginning value validation. For example, a payload containing both an empty string and another field of the wrong native type returns `400`, regardless of schema order or submitted property order. Only a payload that passes that entire first phase may reach a rule capable of producing `422`.

The distinction is a default for application-owned structured request-body content after the applicable transport and media-type boundary accepts it. Query-string, header, route, PHP runtime transport, and multipart transport failures retain their separately recorded contracts and do not inherit this `422` default. It also does not reclassify database projections, authentication, authorization, resource absence, persisted-state conflicts, precondition failures, or unexpected operational failures. A database exception does not become `409` or `422` without a separately named application failure that proves the exact condition.

Both public failures remain finite, stable, generic, and disclosure-safe. Submitted field names and values, credentials, internal exception messages, and rule details do not enter the response, terminal summary, log, or trace. A protected route retains its recorded parser-versus-policy order, so this distinction does not silently move parsing before current authentication or authorization.

The application owns the unacceptable-value exception and registers its exact class to one immutable prebuilt `422` response in the visible `ErrorResponseRegistry`. PHPThis adds no core exception, validator, result object, field-error schema, string-rule language, renderer, hydrator, automatic request binding, or status inference. An application may deliberately adopt a different finite public status contract, but it records that override in application context and proves it with the same redaction, determinism, and zero-downstream-work evidence.

ADR 042 supersedes ADR 021 only for the canonical public status default. ADR 021's operation-owned parser, final readonly value, normalization, typed-operation, zero-downstream-work, duplicate-key, and no-generic-validator decisions remain unchanged. Consumer Contract version 10 and Strict Profile version 3 remain unchanged because this decision adds authoring guidance and application-owned evidence without accepting or rejecting PHP syntax, adding framework runtime, or adding a diagnostic.

## Consequences

An AI has one deterministic decision boundary instead of treating every rejected field as a malformed request or every decoded JSON object as automatically eligible for `422`. Consumers gain a useful machine-visible distinction without receiving submitted values or a framework-owned validation format.

Operation parsers may repeat two explicit phases. That repetition is intentional: it keeps the exact accepted structure, value policy, exception ownership, and failure precedence visible beside the command being constructed. Applications that override the default carry the compatibility cost of their recorded public contract.

## Reconsider when

Independent consumer evidence shows that the two-phase distinction cannot be applied consistently to materially different request-content formats; a protocol requires a more specific status contract; or generic response disclosure is insufficient for an accepted client requirement. Reconsider the smallest application-owned response contract, not a validator DSL, automatic field-error renderer, or framework status inference.
