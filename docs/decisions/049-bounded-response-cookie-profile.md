# ADR 049: Bounded response-cookie profile

Status: accepted

## Context

ADR 015 made response cookies explicit immutable values and kept native-session cookie emission inside the single response path. That decision requires an HTTP-token name, a cookie-safe value, an absolute path, explicit security attributes, and separate `Set-Cookie` fields, but the accepted public constructor before this decision permitted several values that a conforming user agent could reject or interpret ambiguously.

The reproduced gaps were bounded and inside the already accepted cookie surface: a non-ASCII path, an oversized name and value, an expiration outside a four-digit year, an unbounded maximum age, same-name cookies on different paths, an unbounded response-cookie list, and security-prefix lookalikes checked with case-sensitive comparisons. The session-cookie profile itself remained deliberately narrow, but its executable evidence and public wording did not yet state every attribute and limitation precisely.

Correcting these cases rejects application constructions that the current public API accepts. This is therefore not a wording-only clarification. It needs an explicit compatibility decision, Consumer Contract migration, and bounded core allocation even though PHPThis remains prerelease software.

On 2026-08-11 in Asia/Manila, the accountable human approved this bounded correction, its exact compatibility migration, Consumer Contract version 12, and the 2,620-line core ceiling. This approval accepts the framework boundary only; it selects no release identity and authorizes no commit, push, tag, package, GitHub release, or announcement operation.

## Decision

PHPThis retains the existing `ResponseCookie` constructor and `Response::$cookies` execution path while narrowing their accepted values to this exact profile:

- `strlen($name) + strlen($value)` is at most 4,096 bytes. The existing HTTP-token name and cookie-safe ASCII value grammars remain unchanged.
- `Path` is an absolute 1-to-1,024-byte ASCII producer value. It begins with `/` and contains only bytes `0x21` through `0x7E` except `;`.
- `expiresAt`, when present, is a Unix timestamp from `1` through the smaller of `PHP_INT_MAX` and `253402300799`, inclusive, so every supported platform serializes a four-digit UTC year. The upper bound is `253402300799` and year 9999 on 64-bit PHP; the parameter's native `int` range is the tighter bound on a narrower platform.
- `maximumAgeSeconds`, when present, is from `0` through `34560000`, inclusive. Zero retains immediate deletion. When both `Max-Age` and `Expires` are present, `Max-Age` is authoritative and `Expires` is the fallback for a recipient that does not honor `Max-Age`; PHPThis does not infer a clock-relative equality between them.
- One `Response` contains at most 50 cookies, no two cookies have the same case-sensitive name even when their paths differ, and the sum of their serialized `ResponseCookie::headerValue()` bytes is at most 8,192. The aggregate excludes transport-owned `Set-Cookie: ` field-name and line-framing bytes.
- The `__Secure-`, `__Host-`, `__Http-`, and `__Host-Http-` prefix comparisons are ASCII case-insensitive without normalizing the caller's name. `__Secure-` requires `Secure`; `__Host-` requires `Secure` and `Path=/`; `__Http-` requires `Secure` and `HttpOnly`; and `__Host-Http-` requires `Secure`, `HttpOnly`, and `Path=/`.
- `SameSite=None` continues to require `Secure`. Distinct accepted cookies continue to emit as separate `Set-Cookie` fields, and application code still cannot place `Set-Cookie` in the ordinary response-header map.

Every accepted `ResponseCookie` remains host-only because the type has no `Domain` attribute. `Domain`, `Partitioned`/CHIPS, and `Priority` remain unsupported rather than being silently encoded through another path. A cross-subdomain or embedded third-party requirement needs a separate problem statement, browser and deployment evidence, and accepted decision.

The native-session profile retains its fixed 32-character lowercase-hexadecimal identifier, `Path=/`, `HttpOnly`, no `Domain`, and explicit SameSite and deployment-selected `Secure` values. A live session cookie has no `Expires` or `Max-Age`; browser restoration may retain such a cookie, so server-side idle and absolute expiry remain application policy. Its deletion cookie retains the same name and scope, an empty value, a past `Expires`, and `Max-Age=0`.

`HttpOnly` prevents ordinary script access to cookie bytes but does not prevent script-initiated authenticated requests and does not replace output encoding, CSP, endpoint security, CSRF, or logging controls. Production authentication and session cookies normally use `Secure` through an end-to-end reviewed HTTPS deployment; an insecure cookie is limited to an explicitly isolated development profile. Prefix behavior depends on a supporting user agent and does not isolate ports. Applications should prefer a canonical `__Host-` session-cookie name when that host-only root-path profile is compatible.

Consumer Contract version 12 carries version 11 forward and retains Strict Profile version 3 and permanent diagnostics `PHT001` through `PHT007`. This decision adds no checker rule, accepted-PHP syntax, runtime dependency, cookie jar, middleware, global helper, authentication mechanism, CSRF policy, browser runtime, or alternate session lifecycle.

The accepted core ceiling is 2,620 physical lines for this response-cookie correction. The final readable implementation occupies 2,618 lines, 23 more than the accepted pre-Issue-43 2,595-line source. The remaining two lines are unallocated and authorize no adjacent cookie attribute, helper, authentication mechanism, session mechanism, or response feature.

## Compatibility migration

Before adopting Contract version 12, an application must:

1. Inventory every direct `ResponseCookie` construction and every response-copy path carrying `Response::$cookies`.
2. Replace a non-ASCII or oversized path, an oversized name-plus-value pair, an unsupported expiration or maximum age, and an insecure prefixed cookie with one value inside the profile above.
3. Remove or rename same-name response cookies even when their paths differ, then keep every response within the cookie-count and aggregate serialized-byte bounds.
4. Confirm that any simultaneous `Expires` and `Max-Age` values deliberately use `Max-Age` as the authoritative lifetime.
5. Add exact live and deletion session-cookie assertions when native sessions are adopted, including name, value, path, security attributes, SameSite, expiration fields, and serialized header.
6. Record the application's HTTPS, cookie, authentication, expiry, logout, revocation, CSRF, browser, and deployment policies without treating this transport profile as those policies.
7. Run the complete application gate on PHP 8.4.x.

A later prerelease that includes this decision must name these rejected constructions and the migration above as an intentional prerelease compatibility break. This decision does not select or authorize a release identity, candidate commit, tag, package update, GitHub release, or announcement.

## Consequences

Accepted response cookies have one finite, inspectable serialization profile that fits the framework's bounded incoming-header posture and fails before emission when a response is ambiguous or likely to be discarded. Session cookies retain their existing lifecycle and policy ownership while gaining exact attribute evidence and clearer browser-security limitations.

Some previously constructible generic cookies now fail immediately. Applications needing cross-subdomain scope, partitioned storage, priority hints, more cookies, longer persistence, or a larger header budget do not bypass the typed value; they bring concrete consumer and deployment evidence for a separate decision.

## Reconsider when

Independent consumer evidence shows that one selected bound rejects a necessary first-party host-only cookie; a supported user agent changes the applicable interoperable limit or prefix behavior; a real application needs `Domain`, `Partitioned`, `Priority`, or another cookie attribute; or the response-header and deployment limits change. Reconsider the smallest affected producer rule with browser and deployment evidence rather than adding an unbounded escape hatch.

## References

- [RFC 6265](https://www.rfc-editor.org/rfc/rfc6265.html)
- [HTTP State Management Mechanism editor draft](https://httpwg.org/http-extensions/draft-ietf-httpbis-rfc6265bis.html)
- [Layered Cookies editor draft](https://httpwg.org/http-extensions/draft-ietf-httpbis-layered-cookies.html)
