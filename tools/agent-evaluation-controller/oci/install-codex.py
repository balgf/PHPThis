"""Build-time extraction of two exact members from a pinned official npm tarball."""

import base64
import hashlib
import io
import os
import sys
import tarfile
import urllib.request

PACKAGES = {
    "arm64": ("linux-arm64", "aarch64-unknown-linux-musl",
              "1Y0t2OjCeqR8GaReaeDSrIpf7CDzUmbNGwEQeWPggNQvrwm3zd9R74WHMyalcxr6e4UOlJXXFfZPjpGbPoclWQ=="),
    "amd64": ("linux-x64", "x86_64-unknown-linux-musl",
              "q+0FqEFRkao0o8mBG1q2wDlREvQFlwPt/SdXCJLGWx2zTL2iuzwgUiGD3zDzrTecpPrtv7uMHo/+jGc+Dk3yLA=="),
}

if len(sys.argv) != 2 or sys.argv[1] not in PACKAGES:
    raise SystemExit("Only the pinned Linux arm64 and amd64 packages are supported.")

package, target, integrity = PACKAGES[sys.argv[1]]
url = "https://registry.npmjs.org/@openai/codex/-/codex-0.153.1-" + package + ".tgz"
with urllib.request.urlopen(url, timeout=120) as response:
    archive = response.read(160 * 1024 * 1024 + 1)
if len(archive) > 160 * 1024 * 1024 or hashlib.sha512(archive).digest() != base64.b64decode(integrity):
    raise SystemExit("Official Codex package exceeded its bound or failed SHA-512 verification.")

with tarfile.open(fileobj=io.BytesIO(archive), mode="r:gz") as source:
    for name, member_path in {
        "codex": "package/vendor/" + target + "/bin/codex",
        "rg": "package/vendor/" + target + "/codex-path/rg",
    }.items():
        member = source.getmember(member_path)
        if not member.isfile() or member.size > 384 * 1024 * 1024:
            raise SystemExit("Pinned Codex package member is not a bounded regular file.")
        stream = source.extractfile(member)
        if stream is None:
            raise SystemExit("Pinned Codex package member is unreadable.")
        destination = "/opt/phpthis/" + name
        with open(destination, "xb") as output:
            while block := stream.read(1024 * 1024):
                output.write(block)
        os.chmod(destination, 0o755)
