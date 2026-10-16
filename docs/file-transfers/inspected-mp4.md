# Checked silent MP4 inspection recipe

Use this optional `LOCAL_ADR026` / `INSPECTED_CONTENT` recipe for deliberately exported short, silent catalog clips. It extends the [catalog reference](https://github.com/balgf/PHPThis/tree/main/tools/inspected-media), sharing the [PNG](inspected-png.md) and [JPEG](inspected-jpeg.md) operations' local quarantine, ownership, quota, retention and recovery. The reference's `.ai/file-transfers.md` owns its complete policy. This adds no framework API, Composer dependency or starter adoption; Consumer Contract 26 and Strict Profile 4 remain unchanged.

## Accepted export profile

`CATALOG_MP4_AVC_BASELINE_V1` accepts exactly one video track:

| Boundary | Accepted value |
| --- | --- |
| Bytes/request | 1–1,048,576 actual bytes; existing 2 MiB multipart ceiling; exactly one `clip`, no scalar fields or query parameters |
| Container | Non-fragmented ISO BMFF; 32-bit explicit box lengths; `ftyp`, `moov`, optional empty `free`, then one terminal `mdat` |
| Brands | `isom` major, minor 512, exact compatible list `isom`, `iso2`, `avc1`, `mp41` |
| Codec | One `avc1` description, `avcC` prefix `01 42 c0 1e ff e1`, H.264 Constrained Baseline level 3.0, eight-bit 4:2:0, progressive I/P frames |
| Dimensions | Even display dimensions 2–640 × 2–480; coded dimensions also at most 640×480; SPS crop exactly matches the container; no changing dimensions or rotation |
| Timeline | Positive duration through 10 seconds, integer constant 1–30 fps, 1–300 frames; track timescale 1–90,000 divisible by the sample delta; matching rounded 1,000-unit movie duration |
| Codec setup | One SPS and one PPS, each at most 1,024 bytes; 1–4 reference frames, picture-order-count type 0 or 2, no frame-number gaps or CABAC |
| Samples | One contiguous chunk covers all of `mdat`; one VCL NAL per sample, four-byte length, 5–262,144 sample bytes; NAL headers `41/61/65`; sync table matches IDR samples |
| Metadata | Fixed generic labels, zero creation/modification timestamps, `und` language, square container pixels, numeric bitrate fields; optional exact empty FFmpeg metadata scaffold; bounded codec SPS/PPS control fields including VUI |

`BoundedMp4` checks the exact finite hierarchy and table consistency; `Mp4Structure.php` owns bounded box and parameter-set reads. Unknown boxes, audio/subtitle/data tracks, fragmentation, edits, composition offsets/B frames, encryption, external references, SEI/user metadata, parameter-set changes, other codecs/profiles, multiple chunks, 64-bit/open-ended lengths, and trailing bytes are rejected. The decoder validates the remaining codec fields and slice entropy.

This is a controlled export profile. Ordinary phone-camera MP4s, audio-bearing videos, variable frame rate, High-profile H.264 and many otherwise valid MP4 exports fail it. The synthetic fixture generator records one compatible export command; it is not a consumer upload converter. Preparation, transcoding, thumbnails, browser playback, range responses and adaptive delivery require separate decisions and evidence. Accepted uploads retain their original bytes.

## Full decoding and isolation

The image builds [FFmpeg 9.0.2](https://ffmpeg.org/releases/ffmpeg-9.0.2.tar.xz), source SHA-256 `8c3850283eb25fa026482078a04051e0be17347b09ef81a0849bec15a96e002e`, with only `ffprobe`, the MOV demuxer, H.264 parser/decoder and pipe protocol. Networking, external autodetection, other formats/codecs, assembly, threads and unused libraries are disabled. The Dockerfile retains the upstream license; the source URL and complete build options are explicit. No encoder enters the inspector image. Updates require a newly reviewed inspection definition.

After validating the envelope, `Mp4Decoder` runs one fixed argument array. It forces MOV, H.264 and pipe input, disables external/absolute-path references, enables strict error detection, limits coded pixels to 307,200 and individual allocations to 8 MiB, and requests [every decoded frame](https://ffmpeg.org/ffprobe.html). The finite JSON selection must report one matching stream and exactly one frame per sample, with matching timestamp, dimensions, pixel format and supported frame kind. Successful exit or metadata-only probing cannot establish acceptance. The SPS/container comparison precedes decoding because FFmpeg may otherwise apply a container crop and conceal inconsistent declarations.

The decoder has an eight-second inner deadline and live 65,536-byte stdout / 8,192-byte stderr caps. Pipes are drained concurrently with input. Overflow or failure terminates the child; the worker then verifies removal of the whole container. Any diagnostic at warning level, nonzero exit, missing/malformed/incomplete report, report disagreement, timeout or failed cleanup is `scanner_error`. Envelope failures are `rejected`; both states are non-serving.

The existing outer limits remain: fresh read-only container, no network or mounts, UID/GID 65534, dropped capabilities, no-new-privileges, eight PIDs, 64 MiB memory/swap, one CPU, two CPU seconds per process, 32 MiB PHP memory, ten-second inspection deadline, 8 KiB protocol streams and three seconds per cleanup call. An external supervisor must also bound the whole maintenance command. At most 300 maximum-sized accepted YUV420 frames contain 138,240,000 cumulative pixel bytes, consumed incrementally. That figure and the single-allocation limit do not describe total memory; the container enforces that bound.

Inspection establishes only this format/toolchain policy. It is not an antivirus result or a guarantee for another decoder. An adopter that later plays or processes the attachment owns that component's separate limits and isolation.

## Routes and lifecycle

`POST /catalog-video-clips` fixes `mp4`; `GET /catalog-video-clips/{image_id:token}` fixes the same expected format. The shared catalog retains its legacy `image_id` response key and internal names. Client MIME/filenames never select a decoder. Authentication and named upload/read authorization precede protected work; reads additionally bind owner, format, current definition and expiry. Missing, wrong-owner, wrong-format or ineligible objects share a private 404. The public composition denies every request; synthetic test authority is not deployable authentication.

PNG, JPEG and MP4 share 32 objects/32 MiB, including rejected/error reservations; one inspection start per second; two attempts per object/definition; and 24-hour eligibility. Existing digest checks, exclusive mutation lock, shared lock through emission, retry, reconciliation, quota accounting and supervised deletion apply. An interrupted inspection stays non-serving until its exact container is removed and recovery records the failure.

A successful download is the original full attachment with `video/mp4`, `attachment; filename="catalog-clip.mp4"`, exact length, private/no-store, nosniff and `Accept-Ranges: none`. A Range request receives full 200. Client receipt and streaming playback are not proved.

## Retained catalog migration

Drain HTTP and workers before changing the format CHECK to `png/jpeg/mp4`. SQLite needs a table rebuild. The exact constant statements and executable transition proof are in [`tests/mp4-migration.php`](https://github.com/balgf/PHPThis/blob/main/tools/inspected-media/tests/mp4-migration.php). They use the documented PNG-to-JPEG baseline, including its `DEFAULT 'png'`, reject a changed table definition or unreviewed table index/trigger, create the replacement, explicitly copy every column, drop the old table and rename the replacement inside one transaction. Rollback is checked after every DDL/copy stage; all existing values and the other constraints remain intact. Blind reruns are rejected before mutation.

This is a schema-transition proof, not a production coordinator or ledger. An adopter must use its recorded [migration history](../migrations.md), verify all dependent objects and exact engine/baseline, and preserve its own default/index/trigger/foreign-key semantics. A catalog created directly from the previous fixture has no format default; its replacement must preserve that distinct baseline. The disposable reference creates the complete schema outside HTTP and Composer.

Deploy coherent schema, source, policy and image, activate the new definition, then reinspect retained content. All prior PNG/JPEG/MP4 acceptance becomes ineligible until reinspection completes; compatibility does not promise uninterrupted availability. Keep the existing `PHPTHIS_PNG_*` configuration inputs. Rollback disables video traffic, preserves MP4 identity, ownership and quota until resolved, and uses a reviewed new definition. Never relabel MP4 as an image or revive retired acceptance.

## Evidence and adoption

Run `composer test:inspected-media`, the reference README's Docker builds and `python3 tools/inspected-media/run.py`, then complete `composer check`. Offline tests use frozen synthetic seed bytes/hashes, exercise envelope/report rejection and all three lifecycles, and prove the SQLite transition. The real runner uses the pinned decoder and native HTTP uploads, tests resource limits and inner decoder failures, and verifies owned-container cleanup. `evidence.json` retains dated versions, image identities and source hashes; CI repeats the real proof.

Production adoption still requires its existing local-profile authority, CSRF where applicable, ingress/TLS/rate controls, private volumes, separate HTTP/worker identities, capacity monitoring, deployment enforcement, supervised cleanup and incident recovery. Complete [deployment](deployment.md), [storage ownership](storage-ownership.md) and [testing](testing.md) for the actual application. This reference supplies no production or S3 media-inspection evidence.

The optional [WAV operation](inspected-wav.md) shares this catalog with its own PCM policy. Its retained-format migration preserves existing identities and requires a new definition and reinspection of all adopted formats.
