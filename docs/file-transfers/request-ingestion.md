# Multipart request ingestion

Under Consumer Contract 24, carrying forward the Contract 21 handoff, the sole front controller passes `$_SERVER` and `$_GET` unchanged through the terminal coordinator and `RequestBoundary`. `RequestReader` owns the body read. It calls PHP 8.4's native `request_parse_body()` once for enabled multipart POST, validates the result into immutable `MultipartForm`, and leaves `Request::$body` empty. Ordinary input still uses the configured URI and ordinary-body limit. Handlers never read superglobals or the input stream.

## Explicit configuration

```php
new RequestReader(
    maximumBodyBytes: 8_192,
    inputUri: 'php://input',
    multipartLimits: new MultipartLimits(
        maximumRequestBytes: 2_097_152,
        maximumFileBytes: 1_048_576,
        maximumTotalFileBytes: 1_048_576,
    ),
);
```

`null` leaves multipart disabled. These numbers retain the document example's policy, not universal storage defaults. The three-argument constructor retains its existing behavior: text values default to 8,192 bytes each, with 65,536 aggregate name/value bytes. Contract 23 adds two optional final arguments for explicit application limits:

```php
new MultipartLimits(
    maximumRequestBytes: 2_097_152,
    maximumFileBytes: 1_048_576,
    maximumTotalFileBytes: 1_048_576,
    maximumFieldBytes: 32_768,
    maximumTotalFieldBytes: 131_072,
);
```

All five limits must be positive and below `PHP_INT_MAX`, with file <= aggregate file <= request and field <= aggregate field. Invalid configuration throws `InvalidArgumentException` at construction, before body consumption. Zero does not disable fields; an operation still validates its complete permitted field set. Lowering the aggregate field ceiling below the default per-field ceiling requires explicitly lowering both.

Field ceilings may exceed the request ceiling; they are upper bounds, not reserved allocations. For example, `new MultipartLimits(1_024, 512, 700)` remains valid with the default field ceilings. Every actual request must fit the raw request limit and its normalized field-name/value plus reported file bytes must fit its declared size. The configured field/file maxima need not add up to the request maximum. Values are not silently clamped.

Configure the actual web SAPI with `enable_post_data_reading=Off` and `file_uploads=On`; otherwise enabled multipart fails operationally before parsing. No earlier reader may consume the request body. See [deployment](deployment.md), the [Contract 21 handoff migration](../consumer-contract-upgrades.md#contract-version-21) and [Contract 23 field-limit adoption](../consumer-contract-upgrades.md#contract-version-23).

Multipart requires POST, one canonical non-negative Content-Length within the configured request limit, no Transfer-Encoding, and one boundary parameter of 1–70 accepted bytes. The unquoted boundary permits ASCII letters, digits, apostrophe, plus, underscore, period and hyphen. The quoted form permits the wider existing MIME set and forbids a trailing space. Other parameters and duplicate boundaries fail. Framing and declared size are checked before native parsing, session begin and dispatch.

## Normalized representation and limits

`Request::$multipart` is `null` for ordinary input, or the immutable form with `fields: array<string,string>` and `uploads: array<string,RequestUpload>`. An empty form differs from no multipart representation. `Request::$uploads` exposes the same upload values for existing handlers. Routing copies preserve the same form instance. `MultipartForm::fromPhp($fields, $files, $declaredRequestBytes, $limits)` also validates explicitly supplied PHP arrays; it does not prove their runtime provenance.

All bounds below are inclusive and apply to PHP-normalized values:

| Input | Bound |
| --- | --- |
| Fields / uploads | 32 fields; 8 upload entries, including `NoFile` |
| Names | Case-sensitive ASCII `[A-Za-z_][A-Za-z0-9_-]{0,63}`; no field/file name collision |
| Field values | Flat strings; configured `maximumFieldBytes` per value (default 8,192); `maximumTotalFieldBytes` for all names plus values (default 65,536), including names of empty values; aggregate accounting cannot overflow |
| File bytes | Configured per-file and aggregate reported-byte limits; aggregate addition cannot overflow |
| Client filename / media type | 255 bytes each; untrusted |
| Client full_path / temporary path | 4,096 bytes each; validate then discard full_path |

File metadata retains the exact flat keys and scalar types: `name`, optional `full_path`, `type`, `tmp_name`, `error`, `size`. Reject controls, unknown keys, negative or request-exceeding sizes, incomplete success metadata and contradictory `NoFile` metadata. Normalized field-name/value bytes plus reported file bytes cannot exceed the declared request. Exposed nested fields and file arrays fail.

Preserve field bytes, including empty strings and line breaks. Absence differs from `''`. Limits count bytes, not characters. Do not trim, cast, decode JSON or infer numbers/booleans; the operation's private-constructor value factory defines required/unknown fields, UTF-8 and domain representations. Successful zero-byte uploads remain representable. Validate the complete permitted field/file set and every upload error before storage or other operation work. [Upload values](upload-value.md) retain provenance and actual-size requirements.

## Native parsing and incomplete input

The native call explicitly sets `post_max_size` to the request limit, `upload_max_filesize` to the per-file limit, `max_input_vars=32`, `max_file_uploads=8`, and `max_multipart_body_parts=40`. These options own this parse even when global automatic-parser limits differ. A native `RequestParseBodyException` fails the complete parse as redacted `InvalidRequest` (400); messages are never parsed to classify a PHP error. Declared or normalized byte excesses yield `RequestBodyTooLarge` (413); invalid names/shapes/counts/collisions yield 400; disabled multipart yields 415. Per-file native errors remain exhaustive `RequestUploadError` values for the operation. Unknown runtime failures remain generic 500.

The supported guarantee is bounded **PHP-normalized input**, with whole-parse rejection of native count/request limit exceptions. PHP still collapses duplicate scalar names, normalizes spaces/dots, can discard malformed or over-nested parts, and can accept text without a closing MIME boundary. Neither array cardinality nor required-field validation proves that every raw part survived. An operation whose requirements exclude these semantics needs separately verified upstream strict validation before adoption. PHPThis adds no raw multipart parser or strict whole-body validation claim.

Normalized field/file sums exclude discarded or overwritten raw parts. The total request cap and native raw-part/file limits bound earlier parsing and temporary storage. PHP materializes a field before its per-value framework check, so the request cap remains the pre-materialization bound. HTTP truncation before Content-Length completion belongs to the buffering server/proxy; it differs from malformed MIME inside a complete HTTP body.

The checked local document operation still accepts exactly one `document` upload and no text fields. Expanded transport alone does not authorize multiple-file storage or expand `AMAZON_S3_ADR053`.

Sources: [PHP native request parsing](https://www.php.net/manual/en/function.request-parse-body.php), [PHP upload configuration](https://www.php.net/manual/en/ini.core.php#ini.file-uploads). Tests retain the native normalization counterexamples as well as successful and rejected requests.
