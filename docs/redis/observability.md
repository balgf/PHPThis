# Redis proof observability

`DocumentDetailsCacheTrace` retains exactly one bounded snapshot:

```json
{
  "read": "not_attempted|hit|miss|corrupt|backend_unavailable",
  "write": "not_attempted|stored|payload_rejected|backend_unavailable",
  "invalidation": "not_attempted|deleted|absent|backend_unavailable"
}
```

Request-summary schema version `2` includes that object once as `document_cache` in the existing single sink attempt. It preserves every ADR 023 version-1 field and adds no per-operation log and no second request event.

Every successful `schedule:run` line has exact key order `command`, `outcome`, then `coordination`. `not_due` carries `[]`; contention carries `["connected","contended"]`; the demonstrated owned pass carries `["connected","acquired","renewed","released"]`. A Redis operational failure writes exactly one stderr line with key order `error`, then `coordination`; `error` remains `command_failed`. The bounded list has at most eight entries from `connected`, `connect_failed`, `acquired`, `contended`, `acquire_failed`, `renewed`, `lost_on_renewal`, `renew_failed`, `renewal_limit_reached`, `released`, `lost_before_release`, and `release_failed`. Non-Redis command failures retain the generic one-field `command_failed` object.

Never retain or emit complete cache or lease keys, JSON values, titles, tenant or document identities, owner tokens, Redis endpoints, credentials, exception details, raw Redis replies, SQL, bindings, paths, or request data. A sink attempt still does not prove durable delivery.
