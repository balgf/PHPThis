# Redis proof adoption gate

Copy the reasoning only after the accountable human accepts all of these application facts:

- one named reproducible projection, authoritative source, authorization order, exact key and value schema, TTL, invalidation, and stale-refill bound;
- backend outage behavior that preserves authoritative correctness without a hidden retry or default value;
- separate cache and lease endpoints, with the lease endpoint single-primary and `noeviction`;
- exact owner-token acquisition, finite renewal points, release, expiry, contention, timeout, and crash behavior;
- no fencing or exactly-once claim, with downstream correctness safe after lease expiry; and
- deterministic plus real-backend evidence for every recorded failure, concurrency, redaction, capacity, and topology assumption.

If any fact is unknown, keep the mechanism application-owned and incomplete rather than substituting a generic cache, Redis wrapper, or distributed-lock interface. A different projection, backend, topology, or consistency requirement needs its own accepted decision and tests.
