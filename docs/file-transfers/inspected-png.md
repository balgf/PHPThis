# Checked PNG inspection recipe

Use this optional application recipe when accepting a small, deliberately restricted PNG format is sufficient. It implements `INSPECTED_CONTENT` under the existing `LOCAL_ADR026` profile. It adds no framework API, dependency, automatic storage service or starter configuration.

The [catalog reference](https://github.com/balgf/PHPThis/tree/main/tools/inspected-media) contains explicit PHP upload/read handlers, direct SQLite statements, an isolated inspector and executable local/real-tool proofs. Its `.ai/file-transfers.md` is the sole operation policy; `.ai/configuration.md` owns process inputs. The public composition denies all access, while the HTTP test fixture supplies synthetic policies. This remains a disposable reference rather than production adoption.

## Selected format and limits

| Choice | Checked subset |
| --- | --- |
| Media | Still PNG, 8-bit RGB or RGBA, non-interlaced |
| Container | Only `IHDR`, consecutive `IDAT`, final `IEND`; at most 64 chunks |
| Input | 1–1,048,576 file bytes; 2 MiB native multipart request ceiling |
| Image | 1–1,024 pixels on each axis; at most 4,195,328 decompressed scanline bytes |
| Toolchain | PHP 8.4.26, pngcheck 4.0.1, zlib 1.3.2; hash-verified sources and immutable image identity |
| Isolation | One network-disabled, read-only, non-root container per file; 64 MiB, eight PIDs, two CPU seconds per process, ten-second inspection deadline |

This subset rejects many valid PNGs: metadata/color-profile chunks, palettes, grayscale, interlacing, animation and other bit depths. A separate [bounded JPEG operation](inspected-jpeg.md) shares this catalog; the PNG route still rejects JPEG. WebP, SVG, audio and video have no inspection claim. Neither filename nor submitted MIME type chooses the classifier. No rendering, transcoding, antivirus scanning, streaming or range playback is implied.

The application first validates the envelope, CRCs, dimensions, exact bounded scanlines and complete zlib stream consumption, then requires `pngcheck -q` success on the same bytes. Both checks must pass before promotion. Successful format inspection is not a general content-safety guarantee.

## Visible lifecycle

1. Authorize the upload, enforce admission and quota, verify native upload provenance/size, then move into private quarantine and retain SHA-256. Return `202`; pending content cannot be downloaded.
2. Run `images:inspect-one` explicitly. It selects at most one file, checks identity, invokes the isolated tool and verifies cleanup before promotion. Rejection, outage, timeout, resource termination and ambiguous cleanup remain non-serving.
3. Authorize each read before lookup, require the current accepted definition, unexpired retention and matching digest, then return a fixed PNG attachment with `nosniff` and private/no-store. A shared lock stays held through emission; mutations use the exclusive lock.
4. Activate a reviewed new inspection definition when tools, configuration, accepted formats or application policy change. Previous acceptance becomes ineligible until bounded reinspection succeeds. Running code must match the active source fingerprint, and retired definitions cannot be restored to revive old acceptance. Failed work needs explicit retry, with at most two attempts per definition.
5. Expire serving eligibility after 24 hours. Bounded cleanup removes the exact retained object before releasing capacity; failures remain private, accounted and operator-owned. Scheduled cleanup and incident handling are required for timely physical deletion.

The reference's global ceiling is 32 retained files/32 MiB, including rejected and failed work, with one inspection start per second. The original bytes are the only retained output. There are no replicas, backups or restores. Digest checks complement the required immutable-path/exclusive-writer invariant; they cannot defend against an unrelated writer replacing bytes after verification.

## Adoption and evidence

Run the reference's offline tests, its real-tool/HTTP runner and the framework's complete `composer check`. Its dated `evidence.json` binds recorded versions, cases and sources; dedicated CI reruns the real tool and OS resource failures. The local reference proves native upload provenance, quarantine, promotion, owner separation, changed-definition behavior, retry bounds, integrity checks, quotas and deletion. See its policy for exact commands, failure ownership and remaining deployment obligations.

On a real-parser failure, CI names the format/resource case, failing stage and cleanup result, for example `FAIL real_parser_format_valid_rgb_inspect_cleanup_passed`. A cleanup failure preserves any earlier assertion's label. Only the fixture's finite labels cross the subprocess boundary; unexpected output remains `real_parser_failed`. Payloads, paths, image references, native errors and process streams stay private. Offline diagnostic controls run with `composer test:inspected-media`; the real-tool job remains required evidence.

The real memory-limit proof requires cgroup v2. Its test-image supervisor checks fresh kernel `oom`/`oom_kill` counters and the allocating child's termination, retaining the cgroup until both are observed. The [kernel counter definitions](https://docs.kernel.org/admin-guide/cgroup-v2.html#memory-interface-files) distinguish reaching the memory limit from an OOM kill. Docker's `OOMKilled` flag can miss the event when a short-lived container exits; exit 137 alone also does not establish an OOM kill. Controls prove that a successful small allocation and an unrelated SIGKILL cannot satisfy the memory proof. Isolation settings, termination evidence and cleanup have separate failure labels. This changes the test fixture only; the application inspector and its resource limits remain unchanged.

An adopter still owns actual authentication and applicable CSRF, strict-raw-multipart suitability, ingress/TLS/rate limits, separate HTTP/supervisor authority, temporary/durable volumes, capacity and inode monitoring, filesystem crash behavior, retention supervision and incident recovery. The built-in server and ordinary local permissions do not prove a production deployment or prohibit an OS owner from chmodding its root. Complete the existing [storage](storage-ownership.md), [deployment](deployment.md) and [testing](testing.md) requirements before making those claims.

Existing applications opt in explicitly; installing/upgrading PHPThis does not change an opaque-byte policy or migrate retained objects. No Consumer Contract or Strict Profile migration is required. Adopting the recipe needs its own schema/provisioning, pinned inspector image, authoritative operation policy and reinspection plan.

The shared catalog now persists `png`/`jpeg` and binds that value to its trusted routes and reads. Existing PNG adopters follow the [format-column migration](inspected-jpeg.md#adopting-the-format-column), activate the coherent new definition and reinspect PNG records before serving. The PNG subset and response headers remain unchanged; the migration intentionally makes prior acceptance temporarily unavailable. Both formats share the same quota, retention and recovery ownership.

The same catalog also has a separately bounded [silent MP4 operation](inspected-mp4.md). Adopting that extension requires its format-constraint migration and reinspection; still-image inspection alone supplies no video-decoding evidence.

The optional [WAV operation](inspected-wav.md) shares this catalog with its own PCM policy. Its retained-format migration preserves existing identities and requires a new definition and reinspection of all adopted formats.
