# Upload error ownership

`RequestUploadError` represents PHP's finite upload outcomes without converting them into hidden booleans:

| Case | Application classification in the example |
| --- | --- |
| `Success` | continue to operation limit, provenance, and actual-size verification |
| `IniSize`, `FormSize` | `RequestBodyTooLarge`, generic 413 |
| `Partial`, `NoFile` | `InvalidRequest`, generic 400 |
| `NoTemporaryDirectory`, `CannotWrite`, `Extension` | operational failure, generic 500 |

Malformed PHP array structure remains `InvalidRequest`. A total multipart `Content-Length` above the configured transport cap is `RequestBodyTooLarge`. A valid multipart representation while multipart is disabled is `UnsupportedMediaType`.

An integer outside the enum is an unexpected PHP/runtime condition and remains `RuntimeException`. Do not register `RuntimeException` in `ErrorResponseRegistry`; the outer unknown-failure path selects the generic 500 and retains only the class for the terminal summary.

An application may choose a different finite public mapping only through named application failures and explicit tests. Never return PHP's internal error text, temporary path, filename, media type, or rejected value.
