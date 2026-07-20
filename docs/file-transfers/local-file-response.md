# Local-file response

`LocalFileBody` contains one bounded absolute local path and one expected non-negative byte count. The application has already resolved authorization and storage identity before constructing it; the path is not a route token or client filename.

A file `Response` must have:

- an empty ordinary string body;
- a status of at least 200 other than `204`, `205`, `206`, or `304`;
- canonical `Content-Length` exactly equal to `LocalFileBody::$bytes`;
- no `Transfer-Encoding` or `Content-Range`; and
- no control-bearing or case-insensitively duplicated header.

The handler owns representation policy. The example sets `application/octet-stream`, a fixed `attachment; filename="document.bin"`, `private, no-store`, `nosniff`, and `Accept-Ranges: none`. Correlation and session response copies preserve `fileBody` exactly.

`LocalFileBody` does not check existence in its constructor and carries no open resource. The emitter rechecks the file immediately before headers so a deletion or replacement between handler selection and emission fails closed.
