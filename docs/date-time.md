# Native date and time

PHPThis recommends PHP's native date and time API. The framework and default skeleton do not require Carbon or another date-time package as a runtime dependency. Application code starts with `DateTimeImmutable`, `DateTimeZone`, and `DateInterval`, plus an operation-specific injected clock when behavior depends on the current time.

This is authoring guidance, not a consumer-validity rule. An application may deliberately adopt a third-party package when a concrete requirement justifies it, but that package, version, temporal model, parsing policy, clock, serialization, upgrade policy, and evidence remain application-owned.

## Name the temporal concept first

Do not use one generic “date” value for different meanings:

- An **instant** is one point on the timeline. Its display timezone is not part of its identity. Record the exact wire and persistence representation, precision, range, and canonical comparison form.
- A **calendar date** such as `2026-08-10` has a year, month, and day but no time, offset, timezone, or unique instant. Do not convert it to midnight UTC merely to store it. Keep validated components or one canonical scalar in a final readonly application value.
- A **local date-time** contains civil clock fields whose meaning depends on a separately selected offset or named timezone. It is not a unique instant until the application resolves that policy. Keep the validated local components and zone policy in a final readonly application value rather than attaching an arbitrary timezone to a `DateTimeImmutable`.
- An **elapsed duration** is a measured amount of time. Use a monotonic source for in-process measurement; do not infer it from a wall clock that can move.
- A **calendar interval** such as one month or one day is calendar-relative. It is not necessarily a fixed number of seconds across month ends, leap days, or daylight-saving transitions.

An operation records which concept it owns before selecting a PHP type, database column, JSON representation, or arithmetic rule.

## Native PHP types and sources

- Use `DateTimeImmutable` for an instant or resolved date-time. Modification methods return another value rather than mutating shared state.
- PHP has no native calendar-date or unresolved-local-date-time value type. Represent either with a narrowly named final readonly application value containing validated integer components or its canonical scalar representation. Adopt a richer package explicitly when that application-owned type would otherwise need substantial calendar-domain behavior.
- Pass an explicit `DateTimeZone` whenever timezone affects parsing, conversion, display, or calendar arithmetic. Do not rely on the process-default timezone as an operation contract.
- Use `DateInterval` only after recording whether the interval is calendar-relative or represents a fixed elapsed amount. Test its application at the relevant calendar boundaries.
- Use an integer Unix timestamp only where a wire, schema, protocol, or coordination contract explicitly selects its unit, precision, range, and negative-value policy. An epoch value contains no timezone or original offset.
- Use `hrtime(true)` only for elapsed measurement inside one running system. Its value is not a timestamp and must not be persisted, serialized, compared across processes, or used for scheduling.
- A database expression such as `unixepoch()` or `CURRENT_TIMESTAMP` is a separately owned database clock. Record when that source is authoritative instead of silently mixing it with a PHP process clock.

Prefer concrete `DateTimeImmutable` values inside application behavior. When an integration accepts `DateTimeInterface`, copy it immediately with `DateTimeImmutable::createFromInterface()` before retaining or modifying it so a mutable implementation cannot change underneath the operation.

## Parse exact external representations

External date and time input follows the same named-parser boundary as every other `mixed` value. First enforce the documented native input type and effective representation bound. The effective ceiling may be a recorded total request bound; add a separate field byte bound only when the operation needs one. For application-owned structured body content, complete every field's shape and native-type phase before applying any timestamp value rule, so a value failure cannot mask a structural failure elsewhere in the body.

After that phase, reject a NUL byte and apply an operation-owned complete lexical grammar and component ranges before parsing one fixed format, inspecting warnings and errors immediately, and round-tripping through the same output format. PHP format tokens are parsers, not standards validators: a value can satisfy a token, produce no warning, and round-trip while still violating the operation's offset or component policy. For example, PHP 8.4 accepts `'2026-08-10T12:00:00+24:00'` with the `P` token without a warning and formats it back unchanged. PHP also throws `ValueError` when the parsed value contains a NUL byte, so that rejected representation must not escape as an unknown failure.

This example assumes the native-type phase has already established that `$value` is a string. It selects a separate 64-byte field limit, years `0001` through `9999`, whole seconds, and a required numeric offset from `-14:00` through `+14:00`; it rejects the RFC 3339 unknown-offset spelling `-00:00`. The field limit, grammar, and ranges are part of this example operation, not universal PHPThis policy. Omit the field-specific length condition when a recorded total representation bound is the effective ceiling. Keep the parser-only `!` reset modifier separate from the output format:

```php
$format = 'Y-m-d\TH:i:sP';
$pattern = '/\A(?<year>[0-9]{4})-(?<month>[0-9]{2})-(?<day>[0-9]{2})'
    . 'T(?<hour>[0-9]{2}):(?<minute>[0-9]{2}):(?<second>[0-9]{2})'
    . '(?<sign>[+-])(?<offset_hour>[0-9]{2}):(?<offset_minute>[0-9]{2})\z/';

if (
    strlen($value) > 64
    || str_contains($value, "\0")
    || preg_match($pattern, $value, $parts) !== 1
) {
    throw new InvalidTimestamp('Timestamp has an invalid representation.');
}

$year = (int) $parts['year'];
$month = (int) $parts['month'];
$day = (int) $parts['day'];
$hour = (int) $parts['hour'];
$minute = (int) $parts['minute'];
$second = (int) $parts['second'];
$offsetHour = (int) $parts['offset_hour'];
$offsetMinute = (int) $parts['offset_minute'];

if (
    !checkdate($month, $day, $year)
    || $hour > 23
    || $minute > 59
    || $second > 59
    || $offsetHour > 14
    || $offsetMinute > 59
    || ($offsetHour === 14 && $offsetMinute !== 0)
    || ($parts['sign'] === '-' && $offsetHour === 0 && $offsetMinute === 0)
) {
    throw new InvalidTimestamp('Timestamp has an invalid representation.');
}

$parsed = DateTimeImmutable::createFromFormat('!' . $format, $value);
$errors = DateTimeImmutable::getLastErrors();

if (
    !$parsed instanceof DateTimeImmutable
    || ($errors !== false && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0))
    || $parsed->format($format) !== $value
) {
    throw new InvalidTimestamp('Timestamp has an invalid representation.');
}
```

`InvalidTimestamp` is an illustrative operation-owned value failure, not a PHPThis type. Replace it with the exact failure recorded for that source and operation. An application-owned structured body maps the value failure only after the complete structural phase; query, header, route, and transport inputs retain their own contracts; a database projection uses its recorded persisted-state failure rather than inheriting an inbound mapping.

The chosen effective bound, offset range, precision, and treatment of `-00:00` are operation policies, not PHPThis universal limits. `getLastErrors()` returns `false` when the parse is clean on the supported PHP runtime, so `false` is success rather than failure. Call it immediately after `createFromFormat()` because it describes the most recent parse.

Do not use `new DateTimeImmutable($externalValue)`, `strtotime()`, a package's natural-language `parse()`, or an untrusted string passed to `modify()` as external validation. Those APIs intentionally accept broad relative and natural-language forms. Do not trim, fill a missing timezone, convert to UTC, or replace an offset unless the operation explicitly owns that normalization and its collision, bounds, and retained-value policy.

An offset such as `+08:00` identifies an offset at that instant; it does not preserve an IANA timezone such as `Asia/Manila` or its future daylight-saving rules. Persist the named timezone separately when future local scheduling or calendar recurrence depends on it.

Converting local clock fields in an IANA timezone into an instant requires a recorded daylight-saving transition policy. A skipped local time in a forward gap and a repeated local time in a backward overlap are distinct cases. A forward gap has no matching instant in that zone: reject it, or explicitly shift it to a different valid local time and retain the recorded normalization result. A supplied offset cannot make the skipped local fields valid in the named zone. For a backward overlap, reject the value, choose the explicitly recorded earlier or later candidate, or require an offset or equivalent fold indicator and validate it against an actual candidate for the named zone. PHP may silently choose one offset for an ambiguous local time, so successful parsing and round-trip are not evidence of that decision. Test both sides of every applicable gap and overlap; use the zone's transitions or an explicitly selected calendar package when the operation needs to detect and resolve them.

## Current time and deterministic behavior

When “now” affects a business decision, expiry, retry, schedule, identifier, or response, inject one narrowly named application clock into that operation. Its return type expresses the selected concept: an integer for an explicitly recorded epoch-seconds contract or `DateTimeImmutable` for an instant contract. Production composition supplies the system implementation; tests supply fixed values.

Do not add a framework clock, facade, global helper, static test time, or hidden fallback. A shared PSR clock or another package may be selected by an application that needs interoperability across several adopted components, but the application still owns its concrete composition and timezone policy. A scheduler additionally follows the explicit clock, timezone, cadence, missed-run, catch-up, and overlap contract in [Application-owned CLI and scheduler](cli.md).

## Persistence, serialization, and arithmetic

For every persisted or transmitted temporal value, record:

- the temporal concept and authoritative clock;
- exact format or integer unit, precision, accepted range, and canonical spelling;
- timezone, offset, or named-zone retention policy;
- database engine representation and projection parser;
- JSON or other sink format and normalization policy; and
- compatibility and migration behavior when the representation changes.

Do not infer that a database timestamp column is UTC, that an integer counts seconds, or that an RFC 3339 offset retains a named timezone. Bind temporal data like every other SQL value and parse selected rows immediately into an operation-owned projection.

Calendar arithmetic requires boundary evidence. Cover every applicable leap day, month end, daylight-saving gap and overlap, offset change, minimum and maximum accepted value, fractional precision, and serialization round trip. Fixed elapsed-time logic instead uses an explicitly selected unit or monotonic measurement and must not silently substitute a calendar interval.

## Optional third-party packages

Carbon, Chronos, Brick DateTime, PSR clocks, and similar packages are application dependencies, not PHPThis framework defaults. Adopt one only for a demonstrated need such as localized human presentation, a richer calendar-domain model, or interoperability with another selected component. Record the package and pinned version, why native PHP is insufficient for that application, which package types may cross application boundaries, and the update and test evidence.

If an application selects Carbon, prefer `CarbonImmutable` over mutable `Carbon\Carbon`. Do not let Carbon's permissive parsing, implicit current time, process-default timezone, macros, or global `setTestNow()` state replace the exact boundary and injected-clock rules above. Convert package values to the application's recorded native or scalar representation at integration boundaries when package ownership should not spread.

Selecting a package does not weaken the Consumer Contract or Strict Profile, and PHPThis adds no date-time facade, generic parser, normalization helper, clock API, persistence mapping, checker rule, or `PHT` diagnostic.
