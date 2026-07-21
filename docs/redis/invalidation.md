# Redis document-cache invalidation

`RedisInvalidatingDocumentTitleUpdate` owns one visible sequence:

```text
authoritative SQLite autocommit title update
-> invalidate the exact Redis document-details key
-> return a typed database and invalidation outcome
```

The SQLite result is authoritative. Redis is not enlisted in that commit, and invalidation failure cannot roll it back or convert it to failure after the fact. Tests observe the committed SQLite row and exact-key deletion, prove a rejected authoritative write leaves the existing cache value untouched, and prove an invalidation outage leaves the new SQLite value committed. The typed outcome keeps the committed update and invalidation result separately visible to the caller and trace.

Do not invalidate before the update, hide invalidation in a callback, add a transaction callback, retry implicitly, scan keys, use tags, or infer related keys. A new mutation must name every finite affected key explicitly.
