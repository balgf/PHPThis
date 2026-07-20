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

When content type matters, use an application-owned inspection mechanism with explicit accepted formats, parser limits, decompression behavior, failure handling, tool updates, sandboxing, and tests. PHPThis's example deliberately stores opaque bytes and downloads them as `application/octet-stream` with a fixed filename.
