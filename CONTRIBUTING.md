# Contributing

PHPThis expects AI to author most changes, but it does not require exclusive AI authorship. Every contribution has an accountable human directing or reviewing it. Read `AGENTS.md` whether the change is authored by a person or an AI, then read the specific `.ai/` guide for the area being changed.

A contribution should:

1. State the behavior and its observable cost.
2. Prefer an existing pattern over a second equivalent API.
3. Add or update tests before broadening the core.
4. Update the relevant guide in the same change.
5. Add a decision record for a new architectural mechanism.
6. Run `composer check`, which includes guardrails, maximum-level PHPStan analysis, and tests.

AI may draft and update a proposed decision record and analyze tradeoffs. A consequential product, architecture, security, data, release, or operational decision becomes accepted only through explicit accountable-human maintainer approval; AI may record that approval.

`composer check` is the PHPThis validity gate, not an optional quality report. Fix Strict Profile diagnostics at their cause; do not add a baseline, inline ignore, wildcard exclusion, or comment exemption.

## Local checks

Use PHP 8.4.1 or a later 8.4.x patch release, Composer 2, Git and Python 3, with the PHP extensions required by `composer.json` and the locked development dependencies, including PDO SQLite and `ext-redis ^6.3`. The optional generated-S3 dependencies set this maintainer PHP minimum. Install Redis server `>=7.4` and `<9.0` plus `redis-cli` on your PATH for the complete gate. Redis is a development test dependency; the framework runtime does not require it.

From the repository root, install the locked dependencies:

```sh
composer install --no-interaction --no-progress --prefer-dist
composer install --working-dir=tools/generated-s3 --no-interaction --no-progress --prefer-dist
```

The optional generated-S3 and metadata-upload references share their SDK dependencies under ignored `tmp/generated-s3-vendor`; maximum-level analysis and the offline tests use that exact lock. Its tests additionally need cURL, SimpleXML, POSIX and loopback sockets, with no AWS account or credentials. These are maintainer test dependencies, not framework runtime requirements.

The tests need two distinct disposable Redis processes and change their configuration. Use unused loopback ports and empty temporary data directories, never shared or production instances. Start each server in its own terminal and leave it running until the checks finish.

Cache terminal:

```sh
phpthis_cache_dir=$(mktemp -d)
redis-server --bind 127.0.0.1 --port 16379 --dir "$phpthis_cache_dir" \
  --save "" --appendonly no --maxmemory 16mb --maxmemory-policy allkeys-lru
```

Lease terminal:

```sh
phpthis_lease_dir=$(mktemp -d)
redis-server --bind 127.0.0.1 --port 16380 --dir "$phpthis_lease_dir" \
  --save "" --appendonly no --maxmemory 16mb --maxmemory-policy noeviction
```

In a third terminal at the repository root, check readiness. Continue when both commands return `PONG`:

```sh
redis-cli -h 127.0.0.1 -p 16379 ping
redis-cli -h 127.0.0.1 -p 16380 ping
```

Run the complete gate with those endpoints:

```sh
PHPTHIS_REDIS_CACHE_HOST=127.0.0.1 PHPTHIS_REDIS_CACHE_PORT=16379 \
PHPTHIS_REDIS_LEASE_HOST=127.0.0.1 PHPTHIS_REDIS_LEASE_PORT=16380 \
  composer check
```

Without overrides, the tests use `127.0.0.1:6379` for cache and `127.0.0.1:6380` for leases. If either example port is occupied, choose another unused port and update its server, readiness and test commands together. Separate logical databases in one Redis process do not satisfy the required separation; see [Redis proof requirements](docs/redis-coordination.md#two-redis-roles).

After a successful or failed check, press Ctrl-C in each server terminal. Once each server exits, run `rmdir "$phpthis_cache_dir"` in the cache terminal and `rmdir "$phpthis_lease_dir"` in the lease terminal. Persistence is disabled, so these temporary directories should remain empty.

For focused work without running Redis, installed development dependencies still apply:

```sh
composer guard
composer test -- --group routing
composer test -- --group request-policy
```

These are partial checks. Run the complete gate before reporting a change complete. The [testing guide](https://github.com/balgf/PHPThis/blob/main/.ai/testing.md) lists other focused commands and their evidence scope.

## Review and release

The [PR template](https://github.com/balgf/PHPThis/blob/main/.github/pull_request_template.md) prompts for the existing [ADR 063 review duties](docs/decisions/063-maintainability-review-over-size-proxies.md). Keep answers proportional to the change; the accountable maintainer reviews the rationale and evidence before merge.

Release work additionally follows `RELEASING.md`. A proposal from unreleased `main` is not an approved candidate; proof or publication requires an exact human-approved version, framework and skeleton tags, framework candidate commit, planned release date, bounded scope, release notes, a distinct exact-candidate approval record, and separately authorized external operations. The exact skeleton candidate commit is recorded and approved before its tag or package write. Historical assessment uses the requested exact tag and its tagged records rather than later `main` documentation. A green local checkout does not authorize a push, tag, package publication, or announcement, and local archive proof does not replace verification of the actual version-pinned public distribution artifact.

A shorter call site is not sufficient justification. New framework code must reduce ambiguity, enforce a rule, or remove repeated application code without hiding I/O or control flow.
