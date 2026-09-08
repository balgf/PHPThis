# Public application API

This is a synthetic SQLite application, not a deployed service. All product decisions needed by the task are fixed here. Both conditions implement identical external behavior.

`App\Evaluation\Application` is explicitly constructed with its condition's injected database connection and the supplied `Policy`. Its public method is `handle(string $method, string $path, array $query, array $headers, string $body)`. It returns an immutable response carrying integer `status`, string-valued `headers` and a JSON `body` followed by one newline. The PHPThis condition uses actual framework routing, requests, responses and Connection; ordinary PHP uses explicit native-PHP dispatch and PDO.

Protected routes use canonical positive decimal account/document identifiers without leading zeros. Malformed or unmatched paths return404. The unrelated `GET /health` behavior remains200 with `{"status":"ok"}`. This task's request methods and paths are fixed; no additional routes, HTTP server or front controller are requested.

Call the supplied policy methods in order: `authenticate($headers)`, `resolveTenant($principal,$accountId)`, `authorize($principal,$tenant)`. Stop on the first denial. The lower-case `authorization` header's only public sample value is `Bearer public-fixture`; the supplied collaborator also owns the synthetic decision. Authentication fails401; tenant/action denial fails403. Validate operation inputs after policy and before operation SQL. SQL must independently restrict requested/resolved account and current membership. A principal id is not a document id.

Every application response uses `Content-Type: application/json; charset=utf-8` and `Cache-Control: private, no-store`. Errors contain exactly `{"error":{"code":"..."}}`, using `invalid_request`400, `unauthenticated`401, `forbidden`403, `not_found`404 or `internal_server_error`500. Omit raw exception messages, SQL, credentials and private fields.

Add task behavior tests in `tests/behavior.php`; the protected smoke entry loads that file when present, and the complete profile analyzes it. Preserve the smoke entry and its public input. The driver owns schema and seed preparation. `memberships` records `(principal_id,account_id)`. `documents` has integer primary key `id`, `account_id`, account-unique `document_key`, `title`, `category` and private `private_note`. `events` has integer `id`, `document_id`, `event_type`. `outbox` has integer `id`, `document_id`, `job_type`. The database contains only synthetic rows. The operation uses the injected connection. No second connection, instrumentation access, installer or external service is part of application code.

## Protected document lookup

Implement the existing404 handler stub for `GET /accounts/{account_id}/documents/{document_id}`. On success return200 and exactly `{"data":{"id":1,"title":"Example","category":"general"}}` with the actual public values. Reject any query key or nonempty body with400 after policy. Missing documents, other-account documents and absent current membership return404; this lookup does not disclose whether an inaccessible document exists. Read requests make no durable changes.
