# Inspected catalog PNG reference

Optional application-owned proof for issue #108. Start with the [single file-transfer policy](.ai/file-transfers.md) and [configuration contract](.ai/configuration.md). The public composition denies all requests. Do not deploy the synthetic test policy.

From the framework root, with PHP 8.4, its development dependencies, Python 3 and a local Docker daemon:

```sh
php tools/test-inspected-media.php
docker build -t phpthis-inspected-media:issue108 tools/inspected-media
docker build --build-arg INSPECTOR_IMAGE=phpthis-inspected-media:issue108 -f tools/inspected-media/tests/fault.Dockerfile -t phpthis-inspected-media:issue108-faults tools/inspected-media
docker build --build-arg INSPECTOR_IMAGE=phpthis-inspected-media:issue108 -f tools/inspected-media/tests/update.Dockerfile -t phpthis-inspected-media:issue108-update tools/inspected-media
docker build --build-arg INSPECTOR_IMAGE=phpthis-inspected-media:issue108 --label phpthis.fixture-recovery=third -f tools/inspected-media/tests/update.Dockerfile -t phpthis-inspected-media:issue108-recovery tools/inspected-media
python3 tools/inspected-media/run.py
composer check
```

The runner creates only UUID-owned data, loopback servers and exact labeled containers and removes them on completion. Images remain local for reuse. A killed runner may leave its private `tmp/png-proof-*` directory; stop its owned processes and use its retained container identities for supervised recovery before deleting it. Never prune unrelated containers or storage.

The sole application console exposes `images:activate-definition`, `images:inspect-one`, `images:retry <32-lowerhex-id>`, `images:reconcile-one`, and `images:expire-one`. Commands are single-pass. Unknown/invalid arguments exit 2 before configuration; operational failure exits 1 with generic stderr JSON. Expected outcomes exit 0 with one JSON object on stdout. Provisioning/schema creation is separate fixture/deployment work. The Docker supervisor is application-owned reference code, not a framework process API or a production sandbox certification.
