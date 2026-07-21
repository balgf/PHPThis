# Redis document-cache key

The exact key grammar is:

```text
phpthis_example:<environment>:tenant:<account-id>:document_details:v1:<document-key>
```

`<environment>`, `<account-id>`, and `<document-key>` are already validated bounded application values. The account identifier is the resolved tenant account, and the complete encoded key is at most 192 bytes. The key is application-, environment-, tenant-, purpose-, and schema-scoped; it carries no principal, credential, permission, session identifier, secret, title, or raw unbounded value.

Tests assert exact bytes with synthetic values and tenant/environment separation. Runtime evidence never emits the complete key. A key version change requires a payload and invalidation decision; silently reading both versions is a second cache path.
