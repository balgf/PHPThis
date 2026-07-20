# File-transfer deployment

Application limits must align with the complete ingress path. Record and verify at least:

- PHP `file_uploads`, `upload_max_filesize`, `post_max_size`, `max_file_uploads`, `upload_tmp_dir`, and request execution limits;
- web-server and reverse-proxy request limits, buffering, timeout, temporary storage, and rejection responses;
- upload and durable-storage ownership, permissions, capacity, inode limits, cleanup, mount behavior, backups, recovery, and multi-host visibility;
- response buffering, acceleration/offload, timeouts, client disconnect behavior, TLS termination, and maximum supported file size; and
- authorization, rate limiting, quotas, malware/content inspection, retention, deletion, audit, privacy, and incident response.

The example transport cap is 2 MiB and its file cap is 1 MiB. Its real-SAPI test sets `upload_max_filesize=2M`, `post_max_size=3M`, `max_file_uploads=2`, and an isolated upload temp directory so PHP can expose the cases the application intends to reject. These are test settings, not production guidance.

If PHP rejects a request before populating `$_POST` and `$_FILES`, PHPThis may see only missing upload state. Configure upstream and PHP limits so the intended generic status and observability occur at the correct owner. A local filesystem test does not prove network storage, shared-host, container, serverless, or rolling-deployment behavior.

References: [PHP file-upload configuration](https://www.php.net/manual/en/ini.core.php#ini.file-uploads), [PHP POST upload behavior](https://www.php.net/manual/en/features.file-upload.post-method.php).
