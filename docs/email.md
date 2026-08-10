# Application-owned transactional email

PHPThis provides no mail transport or email-rendering runtime. It ships no mailer, MIME builder, SMTP or provider client, template engine, CSS inliner, queue, worker, or webhook receiver, and it has no email runtime dependency. An application that needs transactional email selects, pins, composes, operates, and verifies that integration itself.

The checked `user.welcome` example is not an email implementation. `RecordUserWelcomeDelivery` records one idempotent database effect in the SQLite `welcome_deliveries` table; it does not compose or send a message. Its same-transaction proof cannot be replaced by an SMTP or provider call and retain the same atomicity or exactly-once claim.

Email HTML is a distinct MIME output sink. It does not create a PHPThis web view layer or change the optional application-owned browser HTML guidance in [Frontend integration](frontend-integration.md).

## Adoption and ownership

Keep email composition, the mail or MIME package, provider, credentials, templates, sender identity, delivery policy, and evidence application-owned. Before implementation, replace `NOT_APPLICABLE(EMAIL)` in application `.ai/integrations.md` with a reviewed adoption record. Do not add another always-read context file.

Use the existing application guides as the current owners:

| Concern | Application-owned record |
| --- | --- |
| Composition and dependencies | finite message types and templates, the pinned maintained mail or MIME package and exact version, supported contract, update policy, and visible composition |
| External integration | one named transport boundary, exact provider or SMTP contract, timeouts, rate limits, provider identifiers, failure classes, idempotency support, and retry owner in `.ai/integrations.md` |
| Configuration | exact input names without values, final readonly configuration, credentials, endpoint and TLS policy, sender identities, canonical application URL, rotation, and process separation in `.ai/configuration.md` |
| Durable delivery | producer transaction, bounded intent envelope, business-event idempotency key, one-delivery worker, retries, dead letters, and replay ownership in `.ai/jobs.md` |
| Security and operations | domain verification, deployment identity, supervisors, reconciliation, webhooks, retention, redaction, capacity, incident response, and provider account ownership in `.ai/operations.md` and the applicable security policy |
| Evidence | deterministic composition, semantic MIME inspection, transport contract tests, lifecycle tests, and approved integration boundary in `.ai/testing.md` |

Record unsupported email features explicitly instead of leaving their behavior implicit. Selecting a dependency or provider does not make it a framework default, a skeleton dependency, or a second framework execution pattern.

## Deterministic composition before transport

Separate deterministic message composition from transport and provider I/O. Composition receives one operation-specific final readonly message or view model containing already validated values. Database reads, token generation, clock reads, filesystem selection, and every other source of changing input complete before that value reaches the composer. Given the same typed input, selected template, locale, and code-owned configuration, composition produces the same semantic addresses, subject, bodies, links, and attachment declarations. Provider-generated boundaries, dates, message identifiers, and receipts remain outside that semantic guarantee unless the application supplies them explicitly.

Select a template only through a finite code-owned choice such as an explicit `match`; never derive a template path, class, function, or source from request data, persisted data, or provider input. Template execution performs no database or network I/O, filesystem discovery, service lookup, session or environment access, mutable-global access, or dynamic code execution. If the selected renderer loads files or compiled templates, it may load only the recorded finite code-owned set through the package's reviewed behavior.

Every message has an intentionally authored UTF-8 `text/plain` body. An optional UTF-8 `text/html` body may be supplied as the multipart alternative, but authoritative plain text must not be produced by naively stripping HTML. Test semantic parity between the two alternatives: recipients may receive or choose either representation, so neither may omit a required instruction, material limitation, destination, or security warning.

Record for each operation:

- the finite subject and template selection, supported locales, and character encoding;
- the renderer's finite public or operational failure mapping without template values or exception details;
- template input cardinality, execution timeout or other effective execution bound, and maximum rendered bytes;
- compiler and cache ownership, paths and permissions when applicable, warmup and invalidation behavior, and failure behavior;
- development reload behavior separately from immutable production deployment behavior; and
- the reviewed text and HTML semantic-parity contract.

A template package is optional. When explicit bounded string composition is no longer sufficient, select a mature maintained package, pin its exact version in the application's lockfile, record its escaping, compilation, cache, security-update, deployment, and removal policy, and keep its extensions and globals finite and explicit. The package remains an application dependency and must not introduce discovery, service location, a generic view model, or a PHPThis template API.

## Encode at each output context

Validate and encode values for their final sink. These are separate contexts:

- plain text uses its recorded Unicode, line-break, control-character, and byte policy rather than HTML encoding;
- HTML text uses HTML-text encoding;
- a quoted HTML attribute uses the encoder and quoting rules for that attribute context;
- each URL component is constructed and encoded according to its position before the reviewed URL is encoded for the surrounding plain-text or HTML context; and
- every deliberate raw-output boundary is finite, code-owned, named, reviewed, and tested with adversarial values.

Do not treat input validation, normalization, a template engine's default escaping, or one generic escape helper as proof for every context. Keep untrusted values out of executable script and style contexts, markup names, event-handler attributes, comments, and raw CSS or HTML. Email client behavior differs from browser-page behavior; do not import browser CSP or framework HTML assumptions as an email safety claim.

Build absolute reviewed HTTPS links from typed canonical application configuration. Never derive their origin, scheme, authority, or base path from an untrusted `Host`, `Forwarded`, or `X-Forwarded-*` request header. Generate bounded tokens before composition, encode them as URL components, and keep them out of logs and diagnostics.

## Addresses, headers, and MIME

Use a pinned maintained application-owned mail or MIME package, or a provider client that owns equivalent MIME construction. Do not use native `mail()` or hand-build MIME boundaries, header folding, address encoding, or transfer encoding.

Represent recipients and sender fields with operation-specific typed bounded values. Record maximum `To`, `Cc`, `Bcc`, and reply-address counts, individual and aggregate address bytes, display-name bytes, subject bytes, and allowed address forms. Syntactic acceptance does not prove that an address exists, is deliverable, belongs to a user, or is an authorized destination.

Keep these identities distinct:

- the envelope sender used for transport returns or bounce processing;
- the visible `From` identity shown to recipients; and
- the optional visible `Reply-To` identity.

Select each from reviewed application configuration or validated operation policy. Reject CR, LF, NUL, and the selected package's other invalid control characters in every value that can reach a header. Never accept a user-selected header name, raw header line, MIME part type, transfer encoding, or boundary.

Record and enforce limits for recipient count, header count and bytes, each text and HTML body, attachment count and bytes, inline-image count and bytes, and total encoded message bytes. When attachments or inline images are not supported, say so explicitly. When adopted, select their content, filename, disposition, media type, storage authority, read timing, retention, and cleanup through bounded typed application policy; templates do not discover files.

Tracking pixels, link rewriting, remote images, open tracking, and CSS inlining are absent unless the application or provider adopts each policy separately. Record its consent, privacy, security, deliverability, resource, provider, and evidence consequences rather than inheriting a provider default silently.

## One explicit transport boundary

An adopting application records one named application transport boundary for each selected delivery path. That record includes:

- the exact package and version, provider and API or SMTP contract version, and supported feature subset;
- credentials and least-authority process identity, with values held only by the selected application process;
- endpoint ownership, certificate verification, TLS mode and minimum policy, proxy trust when applicable, and local-versus-production differences;
- separate finite connect, operation, and total timeouts;
- provider rate and message-size limits, application admission or backpressure behavior, and one retry owner;
- request, provider message, and receipt identifier meaning, representation, bounds, sensitivity, and durable-storage policy;
- finite success, rejection, authentication, rate-limit, retryable, terminal, and ambiguous-timeout outcomes; and
- dependency and contract update cadence plus redacted behavior for every known and unknown failure.

Keep the call visible at that boundary. Do not hide it behind a PHPThis facade, generic notification system, service locator, automatic retry, or silent provider fallback.

Provider acceptance means only that the selected provider accepted a request under its contract. A later provider delivery state is a different observation, and neither state proves inbox delivery, display, reading, or action by a recipient. Do not make a deliverability guarantee.

## Synchronous and durable delivery

Permit synchronous sending only after the application explicitly accepts and tests its latency, timeout, provider-outage, client-disconnect, process-termination, duplicate-submission, and public-failure consequences for that request or command lifecycle. An external call does not belong inside the business database transaction. Sending before commit can escape a later rollback; sending after commit can fail after the business change is durable. Record the chosen ordering and compensation rather than describing either path as atomic.

When delivery must survive request or process termination, prefer a durable deferred intent and follow [Durable jobs](jobs.md) without adding a framework queue. The producer commits the bounded intent with its business write where the selected database recipe supports that guarantee. A later application-owned worker composes and attempts delivery through the named transport boundary. The current `jobs:run-one` example remains a one-delivery application console operation; PHPThis adds no queue, worker daemon, scheduler, or process manager.

Preserve at-least-once semantics. Record:

- one bounded business-event idempotency key distinct from recipient-controlled input;
- provider idempotency support and key scope, retention window, collision policy, and unsupported cases;
- durable internal request identity and any provider request, message, and receipt identifiers;
- ambiguous-timeout behavior when the provider may have accepted a request but the application received no conclusive response;
- finite attempt count and code-owned backoff, with retryable and terminal classifications;
- redacted dead-letter inspection and retention;
- authoritative reconciliation inputs, cadence, timeout, and unavailable-provider behavior;
- compensation policy when the external effect cannot be reversed; and
- the identity authorized to perform an operator replay and the checks that preserve idempotency and audit evidence.

A blind retry after an ambiguous timeout may duplicate delivery. Use the provider's proved idempotency contract when available; otherwise retain an ambiguous state for reconciliation or an explicit operator decision. Persisting a provider receipt after the network call cannot make that call atomic with SQLite. Do not claim exactly-once execution or delivery.

## Delivery feedback and sender operations

Treat bounce, complaint, suppression, unsubscribe, and delivery-status webhooks as separate external integrations. Each adopted endpoint has its own authentication or signature verification, raw-byte and parsed-input bounds, replay window, deduplication identity, current authorization and tenant policy where applicable, finite event mapping, retention, redaction, timeout, retry response, and integration evidence. A transport client does not imply a webhook receiver, and a webhook does not prove inbox delivery.

SPF, DKIM, DMARC, sender and domain verification, consent, unsubscribe and legal policy, suppression retention, reputation, provider quotas, account access, key rotation, incident response, and DNS or provider operations remain explicit application and deployment responsibilities. PHPThis does not select or certify them.

## Security and observability

Keep credentials, recipient data, message bodies, rendered HTML and text, link or action tokens, provider responses, exception details, and webhook payloads out of default logs and durable diagnostic codes. Do not assume that hashing a low-entropy address or provider identifier anonymizes it. Emit only bounded code-owned outcomes, aggregate counters and latency, and identifiers whose classification and operational need were explicitly accepted; failure to write observability must not trigger a second send.

Store only the minimum classified delivery intent and receipt data needed for the selected guarantee. Record encryption and access control where required, every field and byte bound, retention and deletion, backup and restore exposure, operator access, and safe inspection. Redaction is required independently at HTTP, console, job, provider, webhook, and observability boundaries.

Follow [Configuration](configuration.md), [Security](security.md), [Logging](logging.md), and [Observability](observability/README.md) for their existing concerns. This guide does not create a credential store, logger, audit system, or provider-response archive.

## Required evidence

Composition tests cover address and header injection, finite template selection, every output-context encoder, every deliberate raw boundary, intentional text and HTML semantic parity, absolute HTTPS links and token encoding, supported locales, recipient and byte limits, attachments and inline images when adopted, renderer failures, and deterministic semantic composition.

Inspect composed messages semantically through the selected mail or MIME package. Assert decoded addresses, headers, media types, charset, multipart relationship, body values, and attachments; do not snapshot random MIME boundaries, generated dates, provider-generated message identifiers, or other unstable serialization.

Transport tests cover success, provider rejection, authentication failure, rate limiting, retryable and terminal failures, ambiguous timeout, provider-idempotent retry, redaction, reconciliation, and dead-letter behavior without contacting production. Prove that synchronous failure follows its recorded public contract and that deferred failure follows its recorded at-least-once lifecycle. Webhook tests separately cover authentication, bounds, deduplication, replay, event mapping, and redaction for every adopted event type.

Use only a local fake, captured transport, or explicitly approved provider sandbox for integration evidence. Use synthetic non-production identities and never send test messages to real recipients. Record which provider and protocol claims remain unproved by the selected boundary.

The complete application validity gate includes these tests and the pinned dependency lock. PHPThis's maintainer `composer check` proves the packaged guide and unchanged framework boundary; it does not certify an application's provider account, sender reputation, DNS, production delivery, or legal compliance.

## Unsupported framework boundary

PHPThis ships no mail facade, notification API, message bus, event bus, SMTP client or server, provider adapter, email message or address type, attachment abstraction, template or renderer, CSS inliner, view registry, template discovery, asset pipeline, queue, worker, retry loop, dead-letter service, webhook receiver, tracking system, provider selection, or deliverability tooling.

Adopting email changes no Consumer Contract or Strict Profile requirement. It introduces only explicit application dependencies, policy, composition, external I/O, operations, and evidence.
