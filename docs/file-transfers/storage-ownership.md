# Storage ownership

Storage is an application operation, not a framework backend abstraction. The example's one concrete `LocalDocumentFiles` owns this visible sequence:

1. verify upload error, operation byte limit, `is_uploaded_file`, and actual size;
2. generate a random code-owned identifier;
3. require a non-symlink `0700` root and reserve one verified `0700` directory beneath it;
4. call `move_uploaded_file` to a fixed `content` destination;
5. restrict and verify destination permissions as `0600`; and
6. return only the generated identifier.

Before a successful move, PHP owns the temporary upload and removes it at request termination. After the move, the application owns the destination. If later setup fails, the operation visibly attempts to remove the destination and directory it created. Checked filesystem calls suppress native path-bearing warnings and return only named path-free failures. PHPThis performs no destructor cleanup, shutdown cleanup, retention sweep, or background deletion.

Reads recheck the non-symlink `0700` root and identifier directory plus the `0600` file before returning a local-file response. The application records authorization, tenant placement, collisions, overwrite behavior, quota, malware scanning, retention, legal hold, deletion, backup, replication, recovery, filesystem authority, and multi-host topology. A local filesystem proof does not establish network storage semantics.

Do not extract an interface merely to rename this operation as generic storage. A remote object store, pre-signed delivery, or proxy offload requires a separately accepted application path and failure contract.
