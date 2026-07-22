# Type boundary contract

`mixed` is permitted only where external data enters the application. Parse it once into a concrete final readonly value, then use native types.

Canonical factories:

- `Projection::fromDatabaseRow(array<string, mixed>)` for a selected database row.
- `Command::fromJson(string)` for a JSON request body.
- `PageRequest::fromQuery(array<string, mixed>)` for operation-specific query parameters.

ADR 026 adds one framework runtime boundary value before application parsing: `RequestReader` converts the flat PHP upload entry to `RequestUpload`. The operation still parses the complete `Request::$uploads` map into its own narrow value, requires its exact field, exhaustively maps `RequestUploadError`, applies its file limit, and verifies provenance and actual size. Client filename and media type remain untrusted; client `full_path` is discarded.

Routing likewise exposes only typed metadata. Choose the narrowest declaration among `positive-int`, `uuid`, `ulid`, and `token`, reserving `token` for genuinely opaque identifiers. Read the value only through the matching `PathParameters::positiveInteger()`, `uuid()`, `ulid()`, or `token()` accessor, then immediately wrap the unchanged value in an application-owned route-specific identifier and apply any narrower domain validation before database work. Do not normalize it, bind or look up a domain object, or fall back between route types.

Every factory must:

1. Reject missing required fields and unknown fields; use `array_key_exists` to distinguish absence from an allowed explicit `null`.
2. Check the runtime type before conversion and parse fields in a fixed code-owned order.
3. Accept only documented canonical representations, ranges, list shapes, and bounds supplied by the operation or source schema.
4. Throw without including the rejected value and before that invalid value enters typed application behavior.
5. Use a private constructor so invalid instances cannot be created.

Inbound request and command factories bound their complete representation before detailed parsing. After success, downstream behavior uses only the resulting typed value; add a separate typed operation seam only when it owns an independently meaningful business or transaction responsibility. Rejection makes zero seam calls when present and prevents all operation-owned downstream I/O and mutation. Earlier transport or protected-request policy work follows its separately recorded order and bounds. For JSON, require an object, `JSON_THROW_ON_ERROR`, and an explicit depth. JSON booleans use `is_bool`; JSON integers use `is_int` plus a range; canonical integer strings receive a complete lexical check before range-checked conversion; enums require an exact backing type and accepted case; dates require one format and an exact parse-and-format round trip; arrays require an explicit list or object decision, applicable count bound, and per-item parsing. Individual field bounds are application decisions. A deliberately recorded total representation bound may be the effective ceiling for its fields; do not invent redundant limits. When a string has its own limit, record whether it counts bytes or characters. Database projections instead parse the exact selected row after I/O and add field bounds only when the schema or operation supplies them.

No normalization is implicit. A field-specific normalization must name its transformation, order, bounds, collision policy, and canonical retained value. Validation decides acceptance; output escaping or encoding belongs at the final sink; authorization remains a separate current action decision. Validated SQL data still uses a named binding.

Validate inbound fields deterministically rather than following submitted property order. Keep public input failures finite, stable, generic, and free of submitted values or internal messages. Native `json_decode` does not expose duplicate object keys and retains the last value; do not claim duplicate-key rejection without a separately accepted parser.

Do not use scalar casts, `intval`, `floatval`, `boolval`, `strval`, `settype`, inline `@var`, or `assert` as validation. Do not add reflection hydration, a generic collection, or a second parsing style.

`PHT001` enforces this for scalar casts and conversion functions while PHPStan still resolves the operand as `mixed`. Narrowing through an explicit runtime check makes a documented conversion eligible; it does not make an invalid representation valid.

PHPStan `list<T>`, array shapes, and `@template` are static contracts only. They supplement boundary parsing; they never replace it.

ADR 021 adds application-owned command and projection evidence without a generic input API or diagnostic. ADR 026 adds only the concrete typed upload value. ADR 032 adds fixed UUID and ULID route syntax without a framework identifier type. ADR 033 and Consumer Contract v9 add no request or response type and leave Strict Profile v2 unchanged.
