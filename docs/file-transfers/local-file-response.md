# Local-file response

`LocalFileBody` contains one bounded absolute local path and one expected non-negative byte count. The application has already authenticated, resolved any tenant, authorized the named download, and resolved storage identity before constructing it; the path is not a route token or client filename. When file existence is confidential, authorization precedes storage lookup. `LocalFileBody` carries no principal, tenant, policy, or permission evidence and must not become an authorization shortcut.

A file `Response` must have:

- an empty ordinary string body;
- a status of at least 200 other than `204`, `205`, `206`, or `304`;
- canonical `Content-Length` exactly equal to `LocalFileBody::$bytes`;
- no `Transfer-Encoding` or `Content-Range`; and
- no control-bearing or case-insensitively duplicated header.

The handler owns representation policy. The example sets `application/octet-stream`, a fixed `attachment; filename="document.bin"`, `private, no-store`, `nosniff`, and `Accept-Ranges: none`. Correlation and session response copies preserve `fileBody` exactly.

`LocalFileBody` does not check existence in its constructor and carries no open resource. The emitter opens a handle and rechecks regular-file type and exact expected size immediately before headers. Missing, non-regular, or differently sized state fails before response start; an equal-size replacement or in-place mutation can satisfy that check. An application adopting this path-only local-filesystem profile must record and prove that the selected pathname and bytes remain immutable under exclusive-writer authority from authorized selection through completed emission, with no replacement, mutation, unlink, relink, or symlink substitution. A digest is additional integrity evidence only and does not replace that mandatory invariant; a pre-emission digest cannot prevent a later same-size swap. An identity-bound body, open-handle body, or different response primitive requires a separately accepted core/response decision. File size alone is not byte identity.
