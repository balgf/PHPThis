# Redis document-cache value

`RedisDocumentDetailsCache` stores canonical JSON schema version `1` with exactly these fields in this order:

```text
schema_version
tenant_account_id
document_key
title
```

`schema_version` is exactly integer `1`. The complete encoded value is at most 1,024 bytes. `title` is non-empty valid UTF-8 of at most 512 bytes. The tenant and document values must exactly equal the requested typed identities. After decoding, the parser re-encodes the exact ordered shape with `JSON_UNESCAPED_SLASHES` and accepts it only when the bytes equal the stored value. Missing, unknown, duplicate, reordered, differently spaced, wrongly typed, wrong-owner, empty, oversized, malformed, or excessively nested content is therefore corruption, not a hit.

Parsing occurs once as untrusted external input before constructing the concrete document projection. Do not use PHP serialization, object hydration, scalar coercion, partial defaults, or a generic cache-value type. Complete values are never logged.
