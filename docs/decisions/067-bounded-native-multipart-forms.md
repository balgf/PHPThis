# ADR 067: Bounded native multipart forms

Status: accepted

## Context

Issue #104 needs text-only multipart forms, metadata with a file, and multiple distinctly named uploads. ADR 026's one-file/no-fields transport restriction prevents those operations. Simply accepting more `$_POST` and `$_FILES` entries would permit PHP limit-driven truncation to appear successful. Normalized counts cannot detect that loss when repeated names collapse.

The maintainer accepted the API, Contract 21 migration and PHP-normalization limitations on 2026-10-15. An isolated PHP-FPM 8.4.19/Nginx preflight showed that explicit native parsing throws for request/field/file/part limit excesses where automatic parsing returns empty or partial arrays. It also showed remaining silent malformed-part loss and acceptance of text without a closing boundary. Strict raw-body validation is outside this decision.

## Decision

Consumer Contract 21 replaces the integer multipart limit with immutable `MultipartLimits` (request, per-file and aggregate file bytes), and introduces private-constructor `MultipartForm::fromPhp` for bounded flat fields and uploads. [Request ingestion](../file-transfers/request-ingestion.md) owns the exact API, grammar, limits, failures and normalized-input semantics.

`RequestReader` becomes the sole body reader. Its existing protected boundary calls `request_parse_body()` once with explicit finite options, then constructs the typed form before session begin or dispatch. The front controller and coordinator forward only server/query arrays. Enabled multipart requires automatic POST parsing disabled and native uploads enabled; incompatible settings fail operationally. The ordinary-body path and multipart opt-out remain explicit.

`Request` accepts the optional typed form instead of an upload-array constructor argument, exposes `multipart`, and retains `uploads` as its readonly projection. Copies retain the same form and peer metadata. This is an explicit source migration, including for applications whose multipart remains disabled: their canonical front-controller/coordinator handoff changes to two arguments. PHT007 follows that exact current tuple; its configuration-boundary rule, other exclusions, Strict Profile 4 and all PHT identifiers remain unchanged.

Native exceptions reject the complete parse. Recognized per-file outcomes remain typed and require exhaustive application handling before operation work. Provenance, actual-size checks, domain parsing, required/unknown fields, storage naming, persistence, cleanup, authorization and quotas stay application-owned. The existing local document operation and accepted Amazon S3 profile remain single-file storage policies. Transport expansion does not authorize a multi-file storage transaction, directory upload, resumable transfer or new S3 operation.

## Consequences and evidence

Applications receive bounded text-only, mixed and multiple-file input with one visible native parsing path and no dependency, parser interface or new storage abstraction. Configuration, direct Request construction and terminal-coordinator calls must migrate together under the [upgrade guide](../consumer-contract-upgrades.md#contract-version-21).

PHP still normalizes names, overwrites repeated scalars and may ignore malformed raw parts or a missing closing boundary. Approval accepts those semantics. Required-field validation cannot prove complete raw-body validity. Applications needing that stronger guarantee require a separately selected and tested upstream validator; none is supplied here.

Unit, installed-consumer and real-SAPI evidence cover exact and overflowing limits, exposed malformed shapes, errors, empty/missing fields, request copies, provenance and actual sizes, incompatible settings, failure before session/handler work, cleanup and retained normalization counterexamples. Nginx/FPM evidence remains a bounded local deployment proof. No paid evaluation or production certification follows from these tests.

This decision supersedes ADR 026 only for multipart ingress and its one-file/no-fields restriction. Its storage and response boundaries, ADR 053's narrow S3 profile, and all unrelated decisions remain in force.
