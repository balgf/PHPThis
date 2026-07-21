# Redis stale-refill boundary

The proof intentionally accepts this order:

```text
reader misses -> reader reads old SQLite value
writer commits -> writer invalidates
reader writes old value into Redis
```

There is no miss coalescing, generation fence, revision key, compare-and-set refill, or loader lease. The stale representation may remain for at most the 30-second TTL measured from refill, unless evicted earlier. This is bounded staleness, not read-after-write consistency.

Tests force the ordering, observe the stale hit while its TTL remains finite, wait for the key to expire, and prove the next miss returns and stores the newer committed SQLite value. Concurrent cold callers may each execute the one bounded authoritative read. Applications that cannot accept this window need a separate approved revision-aware design; shortening TTL alone does not provide strong consistency.
