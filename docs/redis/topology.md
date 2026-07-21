# Redis proof topology

The executable proof has two operationally separate Redis processes:

- the document-cache endpoint defaults to `127.0.0.1:6379`, logical database `0`, and may use its recorded finite capacity and eviction policy because every value is reproducible;
- the schedule-lease endpoint defaults to `127.0.0.1:6380`, logical database `0`, and is a single-primary topology configured with `noeviction`.

A logical database number is not separation: logical databases in one process share memory, eviction, restart, persistence, and failure domain. Tests compare the two server process identities rather than assuming different database numbers are isolated. Each endpoint has explicit ownership, authentication, transport, ACL, capacity, monitoring, and incident policy.

Repository integration evidence requires `ext-redis ^6.3` and Redis server `>=7.4` and `<9.0`. These are the recorded client and server ranges for this proof, not framework runtime dependencies or certification of another topology.

The proof does not certify Cluster, Sentinel, replicas, managed failover, multi-primary behavior, persistence across restart, cross-region latency, or partition handling. Changing topology requires new real integration and failure evidence before preserving any coordination claim.
