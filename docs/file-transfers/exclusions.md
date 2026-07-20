# File-transfer exclusions

ADR 026 deliberately does not add:

- ORM behavior, model binding, mass assignment, implicit/global scopes, observers, or automatic persistence;
- a generic upload, storage, disk, filesystem, stream, or response interface;
- a facade, helper, service container, provider, discovery, attributes, reflection, or callback registry;
- trusted client filenames, extensions, directory paths, media types, or disposition values;
- automatic retention, deletion, cleanup, backup, quota, malware scanning, image processing, transcoding, or metadata extraction;
- multiple files, mixed multipart fields, resumable or chunked uploads, remote object stores, pre-signed delivery, or proxy offload; or
- byte-range parsing, `206`, `416`, `Content-Range`, multipart ranges, or conditional range logic.

Do not stretch `RequestUpload` into a storage object or `LocalFileBody` into an arbitrary stream. When an application needs one excluded capability, document the concrete product, security, operational, and performance requirement and seek one bounded decision with executable evidence.

The current application-local `LocalDocumentFiles` name is intentionally backend-specific. Replacing it with a generic interface merely to make the example look extensible would erase the ownership and failure facts PHPThis is designed to preserve.
