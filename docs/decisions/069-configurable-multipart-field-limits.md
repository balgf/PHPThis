# ADR 069: Configurable multipart field limits

Status: accepted

## Context

Issue #136 needs a bounded text field larger than the fixed 8,192-byte ceiling, without modifying the framework or changing native multipart parsing. Applications also need smaller per-field and aggregate ceilings. The maintainer approved the additive API and Consumer Contract 23 on 2026-10-16.

## Decision

Append optional `maximumFieldBytes = 8_192` and `maximumTotalFieldBytes = 65_536` arguments to the existing immutable `MultipartLimits`. The former counts each value's bytes; the latter counts normalized field names and values, including names of empty values. [Request ingestion](../file-transfers/request-ingestion.md) owns the complete API, relationships, error mapping and native-parser limitations.

All limits are positive integers below `PHP_INT_MAX`. Require field <= aggregate field and retain file <= aggregate file <= request. Field ceilings may exceed the request ceiling so every existing three-argument configuration remains valid. Each actual request must still satisfy the raw request cap and combined normalized field/file bound. Do not silently clamp configured values or require the configured maxima to add up to the request limit.

`MultipartForm::fromPhp` checks each name and value against the remaining aggregate budget before adding their lengths. The execution path remains explicit: construct configuration, perform bounded native parsing, validate the immutable form, then begin sessions and dispatch. The change adds no dependency, parser option, helper abstraction, storage policy, Strict Profile rule or diagnostic.

## Consequences and evidence

Contract 23 replaces only the fixed field-byte ceilings carried forward from ADR 067 with configurable ceilings and unchanged defaults. Existing calls, copies, count/name rules, error mappings and single-file storage profiles retain their behavior. Higher ceilings require deliberate application adoption and resource evidence; the [upgrade guide](../consumer-contract-upgrades.md#contract-version-23) records that process.

PHP materializes a field before the framework's byte check. Request/native/upstream budgets still bound earlier work, and PHP's documented normalization and incomplete-input limits remain. A larger text field is not automatic JSON parsing, streaming or an expanded storage operation.

Unit and real-SAPI cases cover defaults, larger and smaller ceilings, byte counting, exact/one-over aggregate name/value budgets, integer-boundary configurations, request/file interaction, request copying and rejection before session/handler work. An invalid configuration fails before reader construction. Installed-package evidence verifies the same API and boundaries without changing the starter's multipart opt-out. The complete framework gate remains required.
