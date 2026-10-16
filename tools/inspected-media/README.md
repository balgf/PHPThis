# Inspected catalog PNG/JPEG/MP4/WAV reference

Optional application-owned proof for issues #108 (PNG) and #140 (bounded baseline JPEG), plus #141 (silent baseline AVC MP4 clips) and #142 (bounded PCM WAV voice clips). Start with the [single file-transfer policy](.ai/file-transfers.md) and [configuration contract](.ai/configuration.md). The public composition denies all requests. Do not deploy the synthetic test policy.

From the framework root, with PHP 8.4, its development dependencies, Python 3 and a local Docker daemon using cgroup v2 (including Docker Desktop's Linux VM):

```sh
php tools/test-inspected-media.php
docker build -t phpthis-inspected-media:issue108 tools/inspected-media
docker build --build-arg INSPECTOR_IMAGE=phpthis-inspected-media:issue108 -f tools/inspected-media/tests/fault.Dockerfile -t phpthis-inspected-media:issue108-faults tools/inspected-media
docker build --build-arg INSPECTOR_IMAGE=phpthis-inspected-media:issue108 -f tools/inspected-media/tests/update.Dockerfile -t phpthis-inspected-media:issue108-update tools/inspected-media
docker build --build-arg INSPECTOR_IMAGE=phpthis-inspected-media:issue108 --label phpthis.fixture-recovery=third -f tools/inspected-media/tests/update.Dockerfile -t phpthis-inspected-media:issue108-recovery tools/inspected-media
for mode in stdout stderr timeout exit; do
  docker build --build-arg INSPECTOR_IMAGE=phpthis-inspected-media:issue108 --build-arg PROBE_MODE="$mode" -f tools/inspected-media/tests/probe-fault.Dockerfile -t "phpthis-inspected-media:issue141-probe-$mode" tools/inspected-media
done
python3 tools/inspected-media/run.py
composer check
```

The runner creates only UUID-owned data, loopback servers and exact labeled containers and removes them on completion. Images remain local for reuse. A killed runner may leave its private `tmp/png-proof-*` directory; stop its owned processes and use its retained container identities for supervised recovery before deleting it. Never prune unrelated containers or storage.

The memory fault keeps a small supervisor alive while its child exceeds the same 64 MiB container limit. Exit 86 proves that the child received SIGKILL and the container's kernel `memory.events` counters recorded a limit-driven OOM event and one OOM kill. Allocation success must instead exit 87, and an unrelated SIGKILL exits 89; missing counters, setup failures and deadline expiry exit 88 and fail the proof. Docker's asynchronous `OOMKilled` flag is informational for this case because it can remain false after a memory kill. The supervisor has a five-second child deadline and one-second cleanup bound, inside the unchanged ten-second inspection deadline. All cases still require scanner failure, configured isolation and verified container removal.

The sole application console exposes `images:activate-definition`, `images:inspect-one`, `images:retry <32-lowerhex-id>`, `images:reconcile-one`, and `images:expire-one`. Commands are single-pass. Unknown/invalid arguments exit 2 before configuration; operational failure exits 1 with generic stderr JSON. Expected outcomes exit 0 with one JSON object on stdout. Provisioning/schema creation is separate fixture/deployment work. The Docker supervisor is application-owned reference code, not a framework process API or a production sandbox certification.

The JPEG operation is `POST /catalog-jpeg-images` and its matching GET route; `/catalog-images` stays PNG-only. Both use one stored format, catalog and lifecycle. See the [JPEG profile and migration](../../docs/file-transfers/inspected-jpeg.md) before adopting it. New inspector definitions require reinspection of retained formats. Existing `PHPTHIS_PNG_*` environment names stay unchanged.

The builder verifies the official libjpeg-turbo 3.2.0 archive, builds with SIMD/arithmetic/TurboJPEG/Java disabled and runs all enabled upstream tests. Only the static-libjpeg `djpeg` tool enters the final image. Frozen synthetic JPEG seeds and hashes are in `tests/jpeg-fixtures.json`; `python3 tools/inspected-media/tests/jpeg-fixtures.py` regenerates them with the recorded cjpeg 3.2.0 build. Host image tools are unnecessary for the offline gate. The real runner exercises each JPEG case through the final container and repeats the resource controls with both finite format arguments.

The MP4 operation is `POST /catalog-video-clips` with exactly one `clip`, plus its matching attachment GET route. Read the [MP4 profile and table-rebuild migration](../../docs/file-transfers/inspected-mp4.md): it accepts controlled silent exports through 1 MiB, 10 seconds, 640×480 and 300 frames. It shares the same catalog and maintenance commands; definition changes invalidate every retained format. Existing configuration names and the `image_id` response key remain compatible.

FFmpeg 9.0.2 is built from its hash-verified official archive with only ffprobe/MOV/H.264/pipe support. `BoundedMp4` checks the finite container and SPS crop before `Mp4Decoder` requires every decoded frame and a consistent bounded report. Frozen synthetic seeds in `tests/mp4-fixtures.json` make offline checks independent of host video tools. `tests/mp4-fixtures.py` records their FFmpeg 8.1.2/libx264 generator; encoder build differences may change bytes and need reviewed fixture updates. The four `probe-fault` images replace only the decoder tool to test live stdout/stderr limits, timeout with pipe backpressure and nonzero exit; they are never deployment images. The real runner also repeats existing resource controls with MP4 and verifies container removal.

The WAV operation is `POST /catalog-audio-clips` with one `clip` and the matching attachment GET route. Follow the [WAV profile and migration](../../docs/file-transfers/inspected-wav.md): exactly mono 16 kHz signed 16-bit PCM, 46–320,044 bytes and a positive duration through ten seconds. Its bounded PHP parser validates structure and sample alignment without another codec. It shares quarantine, quotas and all maintenance commands; new definitions require reinspection of all four formats. The migration preserves both recorded MP4 baselines, including their distinct default policy.

`tests/wav-parser.php` builds synthetic fixtures without a host encoder or recordings. The real runner uses Python's standard-library WAV writer and reader for an independent maximum-sized native HTTP upload/download check, exercises all WAV parser cases and repeats the existing resource/cleanup controls. This is structural PCM evidence, not playback or transcription verification.
