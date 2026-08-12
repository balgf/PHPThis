# Client metadata trust

The multipart parser proves shape, types, and transport bounds. It does not make client metadata truthful.

Never use `untrustedClientFilename`, `untrustedClientMediaType`, or discarded `full_path` to select:

- a storage root, directory, filename, or extension;
- executable behavior or a PHP class;
- a response `Content-Type` or `Content-Disposition` filename;
- image, archive, or document processing;
- authorization or retention; or
- log, trace, error, metric-label, or terminal-event content.

Do not "sanitize" a client filename into a server path. Generate an opaque identifier, use a fixed code-owned storage filename, and keep the client value unused unless the product explicitly needs bounded display metadata in a separately reviewed data store.

Record one content posture in the authoritative application file-transfer context:

- `OPAQUE_BYTES`: fixed code-owned stored and download names, no application parser or active-content serving, no type claim derived from client metadata, and only an `application/octet-stream` attachment with `nosniff`, explicit cache policy, and the operation's recorded authentication/authorization policy. This posture does not certify content safety, absence of malware, or harmlessness to downstream parsers; or
- `INSPECTED_CONTENT`: one versioned inspection definition containing exact byte-derived classifiers and accepted formats, pinned parser or scanner versions/configuration/signatures, isolation, time and memory bounds, nesting and decompression limits, quarantine, failure and outage behavior, and tests using malformed and adversarial fixtures. Pending, scanner-error, rejected, and quarantined states are non-serving until one recorded accepted promotion. Define which tool, configuration, signature, allowlist, or application-policy changes create a new definition. For each change, record whether retained bytes are re-inspected or deliberately remain governed by their earlier definition. Reinspection owns bounded selection and order, rate, retry, non-serving state, result transition, accepted promotion, completion evidence, and recovery; no-reinspection names the retained definition and accepted stale-inspection consequence. An external inspection service additionally records data processing, uploaded-byte retention and deletion, region and residency, transport authentication, least credential authority and rotation, timeout, ambiguous failure, retry and duplicate-submission behavior.

Inspection is an application operation, not a truth flag added to `RequestUpload`. Decide whether inspection happens before durable placement or through an explicit quarantine-to-accepted transition, and ensure unaccepted bytes cannot reach a download or processor. PHPThis's example deliberately chooses `OPAQUE_BYTES`; it stores bytes without parsing them and downloads them as `application/octet-stream` with a fixed filename.
