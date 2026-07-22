# Security baseline

AI-oriented explicitness does not replace security review.

- Separate SQL data from SQL structure. Bind every application or external data value with a unique named parameter, even after validation.
- Keep SQL passed to the three canonical direct `Connection` calls within the finite non-blank compile-time constant-string set enforced by `PHT006`.
- Keep statements static by default. Map a genuine structural choice to finite, code-owned, operation-specific reviewed statements and reject unknown choices before database work.
- Do not interpolate data or use an ORM, repository, generic SQL sanitizer, escaping helper, identifier validator, query builder, SQL/binding/placeholder helper, generic paginator, generated or dynamic SQL, transaction callback, SQL template engine, or dialect abstraction to hide statement structure or parameter ownership.
- Validate untrusted input before domain or database use.
- Reject unknown fields and coercive values at the boundary; do not cast malformed input into an accepted type.
- Distinguish absent keys from explicit `null`, apply the operation's recorded total and applicable field or collection bounds, and validate in a deterministic code-owned order before downstream operation behavior.
- Enforce `PHT001` so scalar conversion cannot silently turn unresolved `mixed` input into a trusted value.
- Keep normalization operation-specific and explicit; never strip or rewrite malformed input into an apparently valid default.
- Encode or escape output only for its actual sink; sink encoding is not input validation or authorization.
- Keep credentials outside committed configuration.
- Map only named client failures to generic public responses; broad built-in exceptions remain unknown failures.
- Select the generic unknown-failure 500, then include only its concrete class in the one application-owned terminal summary attempt; never include its message, code, previous exception, source location, stack, SQL, or parameters.
- Generate the 128-bit lowercase-hex correlation ID in the application and replace any case-insensitive response spelling with it; do not echo an arbitrary caller-controlled `X-Request-ID`.
- Give known denials only the common generic known-failure outcome and selected status. Omit credentials, request values, principals, tenants, resources, SQL, bindings, and all other denial-specific data.
- Isolate the single terminal sink invocation attempt from the already selected response. Do not retry, fall back to another logger, expose sink failure, or claim durable delivery.
- Keep superglobal reads in the front controller and enforce body, request-target, query-count, header-count, and header-value bounds in `RequestReader`.
- Keep raw `$_FILES` in the front controller and normalize only the ADR 026 POST-only, no-text, one-normalized-file shape. Record that PHP may already have collapsed duplicate raw scalar parts. Configure compatible PHP, server, proxy, transport, and operation limits.
- Treat upload filename, path, media type, reported size, and temporary path as untrusted until their specific boundary is proved. Client metadata never selects a storage path, executable behavior, response header, or log value.
- Verify `is_uploaded_file` and actual bytes before one visible `move_uploaded_file`; generate the durable identity and fixed destination server-side, restrict authority and permissions, and record exactly when cleanup ownership passes from PHP to the application.
- Keep local-file download authorization and storage resolution application-owned. Use exact full-response framing, `nosniff`, an explicit cache policy, a code-owned download name, and `Accept-Ranges: none` while ranges remain deferred.
- Query traces contain only SHA-256 SQL fingerprints and aggregate metrics; never add SQL, parameters, credentials, exception messages, or driver details.
- Keep application code out of `$_SESSION` and native `session_*` calls. Access optional session state through narrowly named typed services with non-overlapping key ownership over one `SessionLifecycle`.
- Regenerate the session identifier before committing authenticated identity or another privilege elevation, invalidate it at logout, and reject malformed, attacker-selected, and obsolete identifiers.
- Keep session cookies `HttpOnly`, use the environment's explicit `Secure` and SameSite policy, omit Domain, and emit them only as validated `ResponseCookie` values.
- Add CSRF, authentication, authorization, idle and absolute session expiry, rate limiting, and security headers as explicit application policies when required. `SameSite` is not a replacement for CSRF validation.
- Treat cache keys and values as sensitive external storage: bound their sizes, separate application, environment, version, and tenant ownership, and never log complete keys or values.
- Parse every cache hit into its expected typed projection and treat malformed, stale-schema, cross-tenant, or otherwise invalid values as a recorded miss or failure; never deserialize PHP objects from a cache.
- Keep authentication, authorization, permissions, sessions, CSRF material, secrets, and other security decisions out of the initial application data-cache slice.
- Give personalized, session-affecting, authenticated, and sensitive HTTP responses an explicit `private, no-store` policy until the application records and tests a different safe policy; test cookie-emitting responses separately because `Set-Cookie` is not a cache prohibition.
- Treat every stored job envelope as untrusted input: bound bytes and nesting, reject unknown or coercive fields, select handlers only from a finite code-owned type/version map, and never deserialize PHP objects or resolve a class from storage.
- Fence job completion, retry, and dead-letter writes with the leased state, job identity, attempt, opaque token, and a freshly observed unexpired deadline. Store only finite redacted diagnostic codes, never envelopes, payloads, idempotency keys, exception details, SQL, bindings, credentials, or external response bodies.
- Do not deserialize untrusted PHP values or execute generated PHP.

Security mechanisms must remain visible in the route-to-handler path or in the one explicitly registered request boundary. Hidden defaults are not considered protection.

## WebSocket limits

ADR 034 keeps WebSockets in a separate application-owned process using a pinned mature third-party runtime. A successful protocol upgrade is not permanent authentication or authorization. Authenticate explicitly after upgrade even when the handshake also rejects invalid credentials, enforce current expiry and revocation policy, and authorize every command and continuing or emitted resource-specific action at the points recorded by the application. Do not place identity or authorization in a PHPThis request, a generic connection context bag, a cache, or a global.

Require an exact raw request target, explicit URI-normalization policy, strict raw credential grammar before parser normalization, and explicit origin policy; an origin allowlist does not replace authentication or authorization. Bound raw frames, aggregate messages, UTF-8, parser depth, fields, frame and byte rates, accepted commands, total and per-principal connections, concurrent work, outbound frame size, in-flight sends, application idle, absolute lifetime, heartbeat, send, close, and shutdown join. Await sends under a finite deadline and close a slow consumer; do not add an unbounded gateway or application queue. Keep blocking database, filesystem, network-client, sleep, and subprocess work out of event-loop callbacks unless the chosen runtime provides and the application proves an explicit nonblocking boundary.

Treat delivery as best effort unless a separately reviewed durable protocol proves more. Ordered send attempts do not prove peer processing. Reconnect does not imply replay, and an application completion message is not a peer acknowledgement. Do not add hidden retry, replay, deduplication, acknowledgement, resume, reconnect, or exactly-once claims.

Connection summaries use finite code-owned outcomes and counters and omit paths, origins, headers, cookies, credentials, principals, request and resource identifiers, payloads, frame contents, exception messages, source locations, and stacks. Startup, shutdown, TLS, proxy trust, supervisor ownership, forced stop, memory/capacity, load balancing, affinity, multi-process connection state, broker topology, and incident recovery remain deployment security decisions. Prove protocol behavior with real sockets and process behavior with a real child process. The exact Amp 4.0.0 consumer limits are one local recipe, not security defaults.

## Request-handler decorator limits

ADR 033 permits an optional application-owned request-handler decorator only at an explicit route's handler seam. The complete order is visible beside the route, each final decorator owns exactly one downstream `RequestHandler`, and it either short-circuits or invokes that handler once with the same immutable request. No generic middleware interface, pipeline, registry, priority, discovery, `$next` callable, or request-context bag is accepted.

A security-related decorator names its exact policy and dependencies rather than claiming generic security coverage. Its tests prove early-response non-entry, exact request and exception propagation, explicit immutable response-field preservation, and every finite I/O or side-effect bound. A decorator outside ADR 020's request-policy adapter cannot read protected data or perform protected mutation before current authentication and authorization. A response-header decorator preserves all unrelated headers, cookies, body, and local-file framing; replacing an immutable response is not permission to drop an existing cache, correlation, cookie, download, or security field.

Decorators cannot wrap `Application`, `RequestBoundary`, the terminal request-summary coordinator, or `ResponseEmitter`. They do not move session finalization, exact error mapping, correlation, terminal-summary redaction, sink invocation, or emission into a route wrapper and do not add a framework security mechanism.

## Typed input boundaries

ADR 021 keeps field validation and any deliberate normalization in one operation-owned parser. The parser constructs a final readonly command only after the exact object or list shape, runtime types, ranges, and bounds succeed. Invalid input stops before operation-owned downstream I/O and mutation and makes zero calls to a separate typed operation seam when one exists. On a protected route, ADR 020's separately bounded authentication, tenant, and authorization work may deliberately occur before the protected handler parses its operation input.

Validation answers whether a representation is accepted. Normalization changes it under a recorded field policy. Output encoding answers how an accepted value is represented at one sink. Authorization answers whether the current principal may perform the named action. None substitutes for another: a normalized value can remain unauthorized, HTML encoding does not make SQL interpolation safe, and a validated SQL value remains a named parameter.

The first proof returns only generic prebuilt input failures and never includes submitted values or internal validation messages. Native JSON decoding rejects malformed UTF-8 but does not expose duplicate object keys and does not normalize valid Unicode. Applications must not claim either property without a separately reviewed parser or Unicode policy. See [Type safety](type-safety.md) and [ADR 021](decisions/021-application-owned-typed-input-boundaries.md).

## File-transfer limits

ADR 026 narrows PHP's normalized multipart runtime shape but does not certify uploaded content or reject duplicate raw scalar parts that PHP already collapsed. `RequestUpload` deliberately labels the client filename and media type as untrusted, discards client `full_path`, and labels the parsed byte count as reported. The application requires its exact field, maps every upload error, applies a smaller file limit, verifies PHP provenance and actual size, then calls one concrete storage operation. Sanitizing a filename, trusting an extension, or echoing a media type is not a substitute for that path.

The example stores opaque bytes under a server-generated identifier outside the public tree and returns `application/octet-stream` with a fixed attachment name. Applications separately own authentication, authorization, tenant isolation, quota, malware/content inspection, archive and decompression limits, retention, deletion, legal hold, backup, recovery, audit, and incident response. `LocalFileBody` is not authorization and a file identifier is not access permission.

The emitter checks regular-file type and exact size before headers and reads fixed chunks, but other layers may buffer. Production deployment evidence covers PHP upload settings, temporary storage, server and proxy limits, filesystem authority, output buffering, timeouts, disconnects, and multi-host topology. See [File transfers](file-transfers/README.md) and [ADR 026](decisions/026-bounded-file-transfers.md).

## Request policy

ADR 020 keeps protected-request policy in one application-owned action-specific adapter. The visible order is authenticate, resolve tenant, authorize the current named action, then invoke the protected handler with concrete immutable principal and tenant values. Applications manually wire independently replaceable policies; PHPThis adds no framework middleware, generic pipeline, request context, identity provider, tenant model, permission engine, discovery, or service location.

The reference proof is stateless. Its checked-in authenticator is deny-all and its consumer replacement is synthetic and I/O-free; PHPThis supplies no credential parser or verifier. A concrete Bearer implementation maps missing, malformed, and rejected credentials to one generic `401` response with `WWW-Authenticate: Bearer`. Ordinary forbidden and cross-tenant attempts share one generic `403`, so the response does not reveal whether a different tenant relationship exists. Known denials contribute only the generic known-failure outcome and selected status to the common terminal summary; unexpected failures contribute only their concrete class. Authenticated and denied responses start as `private, no-store`.

Authorization is current per request. Session or cache state may supply strictly parsed identity input but never a current authorization decision. Any policy reads use named connections, budgets, and traces distinct from protected handler work. Every denial stops before protected queries, writes, session mutation, cache mutation, and external business side effects. A successful decision does not install an implicit database scope: every protected statement still binds the applicable tenant and resource identifiers, and the application records any authorization-to-write race policy.

Credential issuance, verification algorithm, expiry, rotation, revocation, CSRF, rate limiting, audit events, identity-provider availability, and tenant-discovery semantics remain application and deployment decisions. See [Request policy](request-policy.md) and [ADR 020](decisions/020-application-owned-request-policy.md).

## Session limits

The optional session boundary certifies only PHP 8.4's native file handler with fixed identifier settings and an exact application-isolated save path. It bounds stored values and shortens native lock duration, but it does not authenticate a principal, authorize an operation, select expiry windows, revoke every session for an account, or prove a multi-node storage topology. Applications record and test applicable choices and must not treat possession of stored identity as current authorization.

Session identifiers, cookie fields, CSRF tokens, and complete snapshots are sensitive and do not enter logs, query traces, exception messages, or public errors. See [Session state](sessions.md) and [ADR 015](decisions/015-explicit-native-session-lifecycle.md).

## Cache limits

A cache is not an authorization boundary or source of truth. Tenant-aware key construction reduces collision risk but does not replace authorization before a response is returned. Expiration does not prove immediate invalidation, eviction can remove an unexpired value, and a database commit plus cache invalidation is not one atomic operation. An in-flight miss can also repopulate stale data after a concurrent writer commits and invalidates. Applications record whether invalidation failure or stale refill causes a request failure, bounded staleness, a version fence, or another explicit outcome and test that choice.

Cache backends receive the least network and command authority the application path needs. Network exposure, transport protection, authentication, memory policy, persistence, replication, and administrative access remain backend-specific deployment responsibilities. See [Caching policy](caching.md) and [ADR 016](decisions/016-cache-policy-before-cache-mechanism.md).

ADR 028's document cache runs only after current authentication, tenant resolution, and authorization. A cache hit carries no credential, membership, permission, or authorization decision. Wrong-owner, malformed, duplicate-field, and other non-canonical JSON encodings are rejected as untrusted input before the authoritative SQLite fallback. Authoritative mutations commit before invalidation; invalidation failure cannot roll back that commit, and the accepted stale-refill race may expose the older representation until the finite TTL expires and a later read recovers from SQLite.

The Redis schedule lease defaults to `127.0.0.1:6380/0` on a `noeviction` process separate from the eviction-capable cache at `127.0.0.1:6379/0`; logical databases in one process do not provide that isolation. `SET NX PX` plus owner-checked renewal and release prevents a stale owner from deleting a later owner's current key, but the random owner token is not a fencing token. It does not stop stale work after expiry or prove mutual exclusion through pauses, partitions, client timeouts with uncertain outcomes, Redis restart, failover, replication, or clock anomalies. The SQLite worker's own lease checks, transactions, and idempotent effect remain its correctness boundary. See [Redis cache and schedule coordination](redis-coordination.md) and [ADR 028](decisions/028-application-owned-redis-cache-and-schedule-lease.md).

## SQL data and structure

PDO placeholders represent complete data literals. They cannot stand for identifiers, keywords, operators, ordering directions, or arbitrary fragments. A validated value is still data and remains bound; validation is not permission to interpolate it. See the [PHP `PDO::prepare` contract](https://www.php.net/manual/en/pdo.prepare.php) and [PHP SQL-injection guidance](https://www.php.net/manual/en/security.database.sql-injection.php).

When an operation needs variable structure, prefer mapping a typed choice to complete constant statements. A finite constant fragment is acceptable only when it is code-owned, local to that operation, and every resulting statement remains a compile-time constant-string choice. Unknown external choices fail rather than being stripped, escaped, or silently converted to a default. OWASP likewise recommends parameterized queries, finite allowlisting where binding cannot represent structure, and least privilege; it warns that generic table validation can be unsafe across different query contexts. See the [OWASP SQL Injection Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/SQL_Injection_Prevention_Cheat_Sheet.html).

Tests use valid domain values containing quotes, semicolons, SQL comment markers, and Unicode and prove exact round-trip through parameters without a changed statement fingerprint or leaked trace value. Every variable structural option has success evidence, and malformed or unknown choices fail before the query budget or trace records work. These adversarial examples test a separation property; they are not a blacklist of forbidden characters.

ADR 022's protected document-list statements additionally bind requested account, resolved tenant account, principal membership, cursor values, and every category separately in complete raw SQLite statements. The explicit predicates prove only the exercised path and are not a global authorization scope. Identifier-shaped structural input is rejected, SQL-looking category values remain bound data, and document-key values are also bound; those probes are not universal SQL-injection certification. The composite cursor is not a snapshot and cannot by itself prevent changes between requests.

## Database authority

Each runtime connection uses only the database objects and actions its process needs. The web runtime does not receive schema-owner, migration, role-management, grant-management, or administrative credentials. Migrations and administration run through a separately authorized path whose credentials are unavailable to request handling. The application records and verifies these engine-specific grants in `.ai/data.md`; SQLite applications record the equivalent file ownership and process boundary.

Least privilege limits impact but does not replace application authorization. A permitted statement can still expose another tenant's data or perform the wrong domain action.

## Migration authority and history

ADR 027 migrations run only from the application's explicit operational console under separately authorized schema authority. The web runtime does not receive migration credentials or SQLite file permissions and never migrates during request startup. Protect the migration command, database, ledger, and lock path through the deployment identity, filesystem or database privileges, and an explicit human-approved release procedure.

Treat the ledger as untrusted persisted state. Read its position, identifier, and checksum history with a finite maximum, parse every selected scalar, and reject unknown, duplicate, missing, reordered, malformed, overflowing, or checksum-mismatched history before pending SQL. Constrain the separately inspected timestamp in the engine schema and never use it for order or authorization. Never execute SQL, PHP classes, callbacks, or paths stored in the ledger. Output only finite code-owned redacted results; omit paths, DSNs, credentials, SQL, bindings, exception details, schema contents, and application data.

A checksum detects divergence from the reviewed identifier and statement bytes; it does not prove statement safety, authorization, reversibility, availability, or recovery. Per-migration transactions and a same-host `flock` likewise do not prove another engine's DDL atomicity or distributed exclusion. Production use requires exact-engine privilege, lock, timeout, backup, restore, capacity, and incident evidence. See [Explicit application migrations](migrations.md) and [ADR 027](decisions/027-application-owned-explicit-sqlite-migrations.md).

## Durable-job limits

Commit-visible publication is atomic only for the business write and job insert executed through the same `Connection`, explicit transaction, and SQLite database. It does not include another connection, broker, external service, or later handler execution. The worker process receives only the database-file and table authority its one-shot path requires; schema management, replay, cancellation, and inspection use separately authorized operational paths.

Lease ownership does not prevent duplicate or overlapping execution. An idempotency key and unique database effect constrain the exercised local effect but do not prove exactly-once execution or exactly-once external delivery. External destinations require separately reviewed credentials, timeouts, provider idempotency, durable receipts, reconciliation, compensation, redaction, and least privilege. See [Durable jobs](jobs.md) and [ADR 024](decisions/024-application-owned-sqlite-durable-jobs.md).

## Proof limits

PHT006 recognizes only the three direct canonical `Connection` calls and the native finite string type passed to them. It does not parse SQL, review statement intent, inspect stored procedures or server-side dynamic SQL, validate grants, or cover reflection and non-canonical invocation. A finite statement may still be destructive, logically incorrect, overprivileged, or unsafe inside the database. Parameterization, static analysis, runtime tests, authorization review, least-privilege verification, and engine-specific integration tests are complementary evidence. ADR 024's file-backed SQLite fixtures do not prove production filesystem durability, real concurrency, capacity, clock correctness, backup recovery, dead-letter operations, or another database engine.
