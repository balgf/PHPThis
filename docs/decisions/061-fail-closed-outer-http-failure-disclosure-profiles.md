# ADR 061: Fail-closed outer HTTP failure disclosure profiles

Status: accepted

## Context

Before this decision, the skeleton and executable-example front controllers required `bootstrap.php` before any outer `Throwable` catch. Composer autoloading, application configuration, dependency construction, and composition could therefore fail before the terminal request coordinator existed. If the effective web SAPI had native error display enabled, PHP could disclose an uncaught exception, source paths, and a stack trace.

The accepted request boundary already maps an unknown failure to `UnknownFailureBoundary::respond()` and records only its concrete safe class in the terminal request summary. That protection starts only after composition. `ResponseEmissionFailed` is later still: it describes output ownership after a response has been selected and must remain a separate front-controller boundary.

A controlled development diagnostic is useful, but a development label, debug input, source checkout, or private-looking URL is not an isolation or authorization boundary. Native PHP error display is also too broad: it can run outside application response framing and can expose call arguments and values the application never selected for disclosure.

On 2026-09-01 in Asia/Manila, the accountable human approved this decision, including its strict safe-message allowlist. This accepts Consumer Contract version 18 for Issue #65's local implementation while retaining Strict Profile version 4 and permanent diagnostics `PHT001` through `PHT008`. It authorizes no commit, push, issue closure, tag, package, GitHub release, announcement, production configuration, deployment, or other external operation.

## Decision

The application's sole HTTP front controller establishes one visible application-owned outer failure boundary after ordinary Composer autoloading and generic-boundary setup but before application configuration, infrastructure composition, and request-response selection. Contract version 18 supports only that sole HTTP entrypoint; an alternate deployed HTTP adapter is ineligible until a separate decision defines its equivalent boundary, SAPI settings, and evidence without creating a parallel canonical path. The first setup after Composer autoload loads `UnknownFailureBoundary` and `Response` and constructs the exact generic response through `UnknownFailureBoundary::respond()` before application work. Once that setup succeeds, the application catch starts in `GENERIC` mode around configuration, bootstrap, composition, and coordinator invocation.

The catch first selects that already-constructed generic response. The best-effort outer event and the optional detailed renderer then run as separate protected attempts: failure of the event does not alter either response mode, and a detailed response replaces the generic response only after its complete immutable `Response` has been constructed successfully. Any renderer or renderer-autoload failure therefore retains the generic response rather than escaping the catch.

Response emission remains outside that catch. The visible front controller retains one separate `ResponseEmissionFailed` catch with the current response-start-aware fallback behavior. The outer boundary does not retry composition or emission, install a global exception handler, or create another HTTP path.

The terminal coordinator continues to own ordinary request, handler, registered-failure, unknown-failure, correlation, and summary behavior. A failure it has already converted to a response does not re-enter the outer boundary merely because development details are enabled. Extending detailed disclosure into that existing coordinator would change its response and observability contract and requires a separate decision. A failure before the coordinator exists receives no `X-Request-ID` or terminal-summary guarantee.

### Disclosure selection

The two finite modes are:

- `GENERIC`, which is the default and is mandatory for staging and production; and
- `DEVELOPMENT_DETAILS`, which is an explicit opt-in available only to an application that records and proves an isolated local, development, or test deployment.

Exact process-environment input names and the finite deployment-profile vocabulary remain application-owned. An adopter reads them in its existing single typed configuration boundary. The outer variable remains `GENERIC` until the complete disclosure setting and applicable runtime profile have both been parsed and their combination has been validated. Only then may the application atomically select `DEVELOPMENT_DETAILS`.

Any HTTP application may deliberately remain code-owned `GENERIC` without adopting external disclosure or runtime-profile selection inputs; the configuration-free health-only skeleton is the canonical example. Once an application adopts external selection, both inputs are required: a missing, empty, unknown, unavailable, or malformed input, or a contradictory profile-and-mode pair, fails configuration before application-controlled I/O and is caught as a generic `500`. Staging or production combined with `DEVELOPMENT_DETAILS` is always contradictory. There is no partially adopted or missing-input fallback to continued application execution.

A local, development, or test label is eligible only when application and deployment records prove restricted access, isolation from production traffic and data, and least process authority. No environment label, hostname, caller IP, request header, cookie, query parameter, request body, source-checkout path, `.env` filename, secret URL, or other request-controlled or inferred fact enables details.

### Controlled detailed response

`DEVELOPMENT_DETAILS` selects an application-rendered response with:

- status `500`;
- `Content-Type: text/plain; charset=utf-8`;
- `Cache-Control: private, no-store`;
- `X-Content-Type-Options: nosniff`;
- at most 65,536 body bytes, including its final newline and any fixed truncation marker;
- at most four linked exceptions; and
- at most 32 stack frames total across that chain.

The body uses ASCII LF and starts exactly with `PHPThis development failure\n`. It walks the outermost throwable first and then `getPrevious()`, with zero-based exception indexes. For each throwable it emits these lines in order:

```text
exception[0].class="JSON string"
exception[0].message=<omitted>
exception[0].file="JSON string"
exception[0].line=123
```

An allowed message replaces only `<omitted>` with its JSON string. The exception class uses ADR 023's exact safe-class rule: use `$failure::class` unless it contains `@anonymous`; for an anonymous throwable use its parent when `get_parent_class($failure)` is a string and that parent is a `Throwable`, otherwise use `Throwable::class`.

Each throwable's trace is traversed in native array order with a zero-based frame index that resets for that throwable and one shared 32-frame counter across the body. Every frame first emits `exception[0].frame[0]\n`, then emits only native-typed members in fixed `file`, `line`, `class`, `type`, `function` order as `exception[0].frame[0].file="JSON string"\n` or `exception[0].frame[0].line=123\n`. A missing or wrongly typed member is omitted. Frame `class` is the bounded engine-provided detailed value, not the outer-event safe class, and never enters logs.

Every string is limited to its first 4,096 input bytes and encoded with `json_encode()` using `JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES`; the quotes shown above are the encoded value's quotes. The renderer never reads a trace `args` member, object dump, request data, header, cookie, configuration value, SQL, binding, credential, environment input, or arbitrary object state. It never calls `Throwable::__toString()` or `getTraceAsString()`.

The renderer reserves space for exactly `[diagnostic truncated]\n`. Encountering a fifth throwable or a thirty-third total frame stops traversal before that item and appends the marker exactly once. For a string longer than 4,096 input bytes, the renderer emits the complete line containing the encoded 4,096-input-byte prefix when that line and the reserved marker both fit; otherwise it emits no part of that field. It then stops traversal and appends the marker exactly once. When the next complete encoded line would exceed the remaining 65,536-byte body budget while preserving the marker, the renderer emits no part of that line, stops traversal, and appends the marker exactly once. An encoded line is never cut. Without truncation, the last ordinary line retains its LF and no marker is present.

Arbitrary exception messages cannot simultaneously be exposed and guaranteed free of credentials or deployment values. The fail-closed rule therefore omits the message by default. A renderer may expose it only for a finite exact allowlist of application-owned exception classes whose message grammar is code-owned, documented, and proved not to incorporate external input, configuration, SQL or bindings, credentials, or dependency text. Matching is exact rather than inheritance-based. Strict Profile rule PHT002 makes every compliant named application class final, so a compliant subclass cannot exist; a deliberately non-profile test-owned negative hierarchy may prove that a would-be subclass remains omitted but is not valid application source. Native and dependency exception messages, including PDO messages, remain omitted. Every mode retains the existing prohibition against placing secrets or deployment values in exception messages; omission is defense in depth, not permission to construct a sensitive message. Approval of this record accepts that deliberate diagnostic tradeoff; weakening it requires a separate security decision.

The application may make at most one best-effort outer-failure event attempt before emission. Its application payload is exactly `application.http_outer_failure failure_class=<ADR023-safe-class>`, where the value uses the same exact ADR 023 normalization defined above. It has no other field; destination-owned timestamp or transport framing is not part of the application payload. Anonymous-class source suffixes, messages, paths, lines, traces, modes, and values are excluded. Failure of that attempt cannot replace or mutate the selected response. Existing terminal summaries and any adopted structured operational logs retain their current class-only and redacted contracts even under `DEVELOPMENT_DETAILS`.

### Web-SAPI boundary

Every HTTP profile, including an isolated detailed-development profile, configures the actual web SAPI with:

- error reporting that includes every `E_ALL` bit;
- `display_errors = Off`;
- `display_startup_errors = Off`;
- `log_errors = On` with a private controlled destination; and
- `zend.exception_ignore_args = On`.

The documented built-in-server command supplies the equivalent explicit `-d` settings, including `error_reporting=-1`; evidence requires `(error_reporting() & E_ALL) === E_ALL` rather than strict equality between `-1` and PHP 8.4's current positive `E_ALL` integer. Its inherited standard error is the local operator-controlled error destination. Automated real-SAPI evidence captures that stream in a private test-owned file and proves it does not enter the response. Application `ini_set()` calls are not a substitute because startup, front-controller parsing, ordinary Composer autoloading, and other earlier failures can precede them. Static source checks can preserve the documented command and configuration fields but cannot prove the effective deployed SAPI; each deployment owns dated runtime evidence.

Failures before Composer autoload completes; failure while loading or constructing the framework-owned generic-boundary types before the generic response exists; process-startup errors; a parse error in the front controller or ordinary Composer autoload file; uncatchable termination; failures after response emission starts; and SAPI, server, proxy, TLS, network, or client delivery remain separate deployment boundaries. A `ParseError` raised while requiring or autoloading application code inside the established `try` is catchable. The application makes no broader catchable-response claim and does not duplicate the generic response in native front-controller statements to conceal a corrupt or incomplete framework package.

## Compatibility migration

Consumer Contract version 18 carries Contract version 17 and Strict Profile version 4 forward with permanent diagnostics `PHT001` through `PHT008`. It adds no framework-core type or line, runtime dependency, framework configuration service, middleware, logger, discovery mechanism, global handler, Strict Profile rule, or `PHT` diagnostic.

Because the current starter authority advances to Consumer Contract version 18, the maintainer-only `change.simple-ping` evaluation task advances from revision 25 to revision 26 and pins the resulting source-skeleton Git tree and fixture digest. Its prompt, rubric, scorer, workspace policy, budgets, and non-comparative boundary remain unchanged. This fixture maintenance records no model result and makes no comparative claim.

Before adopting Contract version 18, an HTTP application must:

1. Move ordinary Composer autoloading ahead of the catchable application boundary and keep all application configuration and composition inside it.
2. Install the one generic-first outer selection while retaining the separate current emission boundary.
3. Either deliberately remain code-owned `GENERIC` without selection inputs, or record the exact typed disclosure/profile inputs, finite eligibility map, injection owner, failure behavior, restart policy, and non-disclosure evidence in `.ai/configuration.md`.
4. Record the effective web-SAPI settings, private error destination, deployment isolation and access policy, source, and verification date in `.ai/operations.md`.
5. Add the complete application and deployment evidence below and run the canonical zero-argument `composer check` gate on PHP 8.4.x.

The framework skeleton remains configuration-free and generic. A transient configured consumer or checked reference proves the optional detailed profile rather than inventing skeleton configuration. The executable example and application template remain coherent with the same single path.

## Required evidence

Real process or real-SAPI evidence must prove:

- a forced bootstrap or configuration failure in `GENERIC` returns the exact existing generic `500` and excludes a supplied sentinel, message, class, path, line, trace, `Fatal error`, `Uncaught`, and `SQLSTATE`;
- explicit eligible `DEVELOPMENT_DETAILS` returns the exact status and headers, disclosure-safe controlled fields, four-exception and 32-frame ceilings, 65,536-byte ceiling, and fixed truncation behavior;
- one exact allowlisted application exception exposes its bounded code-owned safe message, while a native exception, dependency exception, deliberately non-profile test-owned subclass of an allowed class, and non-allowlisted application exception each retain `<omitted>`;
- a deliberately code-owned generic application, including the configuration-free starter, composes and serves normally without disclosure inputs, while a missing adopted input, malformed selection, or staging or production plus details fails before application-controlled I/O and returns generic;
- query, header, cookie, caller-address, and other request inputs cannot change the selected mode;
- trace arguments, disallowed messages, synthetic sensitive values, SQL bindings, and credentials are absent even when present in the thrown object;
- an outer-event attempt that throws cannot change either selected mode, and a detailed renderer that throws retains the already-constructed generic response; neither path retries the event or renderer;
- a successful outer-event attempt receives exactly `application.http_outer_failure failure_class=<ADR023-safe-class>` as its application payload and no other field;
- normal coordinator behavior and correlation and summary behavior remain unchanged; the existing pre-start `ResponseEmissionFailed(false)` one-fallback branch and response-started `ResponseEmissionFailed(true)` no-replacement branch remain executable, and pending output after either generic or detailed outer selection proves no replacement;
- the local server runs with the exact safe PHP settings; and
- deployment evidence reads the effective settings from the real target SAPI without claiming a static checker proves them.

The complete framework, skeleton, executable-example, package-distribution, and installed-consumer gates must pass. The accepted request-boundary, error, configuration, security, operations, testing, Consumer Contract, upgrade, template, skeleton, and example behavior must remain coherent.

## Consequences

Composition failures become catchable and generic by default without adding framework runtime. Isolated developers can opt into bounded explicit diagnostics while PHP's native error display remains disabled. The most sensitive diagnostic field, the exception message, is deliberately unavailable for unknown native and dependency failures; developers retain class and bounded topology and can add only reviewed application-owned safe-message cases.

The boundary cannot protect code that fails before autoload or after response ownership is lost. Operational configuration and real-SAPI evidence remain necessary, and the fixed outer event is not a durable-delivery guarantee.

## Reconsider when

Reconsider the smallest affected boundary when a real application proves that the selected byte, chain, frame, or field bounds prevent diagnosis; when a supported SAPI cannot enforce or expose the required settings; when a dependency offers a typed disclosure-safe diagnostic contract; or when representative consumer evidence shows the application-owned renderer is repeated without material variation. Do not respond by enabling native display, exposing arbitrary messages, adding request-controlled diagnostics, moving emission into the catch, or introducing a framework configuration or logging runtime without a separate accepted decision.
