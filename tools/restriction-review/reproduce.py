#!/usr/bin/env python3
"""Reproduce only Issue #67's fixed historical size and PHT007 controls.

Run with Python 3.9+ from this checkout; Git and PHP 8.4 must be on PATH.
This optional maintainer proof reads pinned Git blobs, never the working-tree
payload or vendor directory. It is not an application or release validity gate.
"""

import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import selectors
import shutil
import subprocess
import sys
import tempfile
import time


BASELINE = "de7353ec2ff881cd85392dd59a6bd08ab7e9d64f"
ROOT = Path(__file__).resolve().parents[2]


class ReproductionFailure(Exception):
    """A fixed expectation failed; external output is deliberately not exposed."""


def require(condition, message):
    if not condition:
        raise ReproductionFailure(message)


def run(command, cwd, *, environment=None, payload=b"", limit=65536, timeout=30):
    """Capture both streams concurrently with byte caps and a finite deadline."""
    with tempfile.TemporaryFile() as input_file:
        input_file.write(payload)
        input_file.seek(0)
        process = subprocess.Popen(
            command, cwd=cwd, env={} if environment is None else environment,
            stdin=input_file, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
        )
        output = {"stdout": bytearray(), "stderr": bytearray()}
        deadline = time.monotonic() + timeout
        try:
            with selectors.DefaultSelector() as selector:
                for name, stream in (("stdout", process.stdout), ("stderr", process.stderr)):
                    os.set_blocking(stream.fileno(), False)
                    selector.register(stream, selectors.EVENT_READ, name)
                while selector.get_map():
                    remaining = deadline - time.monotonic()
                    require(remaining > 0, "A fixed subprocess exceeded its deadline.")
                    for key, _ in selector.select(min(remaining, 0.25)):
                        chunk = os.read(key.fileobj.fileno(), 65536)
                        if not chunk:
                            selector.unregister(key.fileobj)
                            continue
                        captured = output[key.data]
                        require(len(captured) + len(chunk) <= limit,
                                "A fixed subprocess exceeded its output cap.")
                        captured.extend(chunk)
                remaining = deadline - time.monotonic()
                require(remaining > 0, "A fixed subprocess exceeded its deadline.")
                return_code = process.wait(timeout=remaining)
        finally:
            if process.poll() is None:
                process.kill()
                process.wait(timeout=5)
            process.stdout.close()
            process.stderr.close()
    return return_code, bytes(output["stdout"]), bytes(output["stderr"])


def snapshot(git, destination):
    # git archive obeys export-ignore and would omit maintainer guard inputs.
    code, listing, errors = run(
        [git, "ls-tree", "-rz", "--full-tree", BASELINE], ROOT,
    )
    require(code == 0 and not errors, "The pinned baseline Git tree is unavailable.")
    entries = []
    for record in listing.rstrip(b"\0").split(b"\0"):
        metadata, raw_path = record.split(b"\t", 1)
        mode, kind, object_id = metadata.split(b" ")
        path = PurePosixPath(raw_path.decode("utf-8"))
        require(kind == b"blob" and mode in (b"100644", b"100755"),
                "The baseline contains an unexpected Git object mode.")
        require(not path.is_absolute() and not {"..", ".git", "vendor"}.intersection(path.parts),
                "The baseline contains an unexpected snapshot path.")
        entries.append((path, mode, object_id))
    require(len(entries) == 519 and len({item[0] for item in entries}) == 519,
            "The pinned baseline must contain exactly 519 distinct tracked files.")
    code, blobs, errors = run(
        [git, "cat-file", "--batch"], ROOT,
        payload=b"\n".join(item[2] for item in entries) + b"\n",
        limit=16 * 1024 * 1024,
    )
    require(code == 0 and not errors, "The pinned baseline Git blobs are unavailable.")
    offset = 0
    for path, mode, object_id in entries:
        end = blobs.index(b"\n", offset)
        returned_id, kind, size = blobs[offset:end].split(b" ")
        start = end + 1
        finish = start + int(size)
        contents = blobs[start:finish]
        digest = hashlib.sha1(b"blob " + size + b"\0" + contents).hexdigest().encode("ascii")
        require(returned_id == object_id == digest and kind == b"blob"
                and len(contents) == int(size) and blobs[finish:finish + 1] == b"\n",
                "A pinned baseline blob failed identity verification.")
        target = destination.joinpath(*path.parts)
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(contents)
        target.chmod(0o755 if mode == b"100755" else 0o644)
        offset = finish + 1
    require(offset == len(blobs), "The pinned baseline blob stream has unexpected trailing bytes.")


def size_controls(php, source, work):
    command = php + ["tools/guardrails.php"]

    def check(expected_code, expected_stdout, expected_stderr):
        actual = run(command, source)
        require(actual == (expected_code, expected_stdout.encode(), expected_stderr.encode()),
                "A fixed historical size-control result changed.")

    check(0, "PASS guardrails: 250 Markdown files, 239 PHP files, 2618 core lines\n", "")
    application = source / "src/Application.php"
    original = application.read_bytes()
    original_file = work / "Application.original.php"
    original_file.write_bytes(original)
    application.write_bytes(original + b"\n\n\n")
    token_check = r'''
$tokens = static function (string $file): array {
    $significant = [];
    foreach (token_get_all(file_get_contents($file), TOKEN_PARSE) as $token) {
        if (is_array($token)) {
            if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $significant[] = [$token[0], $token[1]];
        } else {
            $significant[] = $token;
        }
    }
    return $significant;
};
exit($tokens($argv[1]) === $tokens($argv[2]) ? 0 : 1);
'''
    require(run(php + ["-r", token_check, str(original_file), str(application)], work)
            == (0, b"", b""), "The blank-line control changed significant PHP tokens.")
    check(1, "", "FAIL Core source has 2621 physical lines; the accepted response-cookie profile limit is 2620.\n")
    application.write_bytes(original)
    controls = source / "tools/restriction-controls"
    controls.mkdir()
    for number in range(1, 12):
        (controls / f"NoOperation{number:02}.php").write_bytes(b"<?php\n\ndeclare(strict_types=1);\n")
    check(1, "", "FAIL Markdown files (250) must outnumber PHP files (250).\n")
    (controls / "empty.md").write_bytes(b"")
    check(0, "PASS guardrails: 251 Markdown files, 250 PHP files, 2618 core lines\n", "")
    return [
        "baseline: PASS; 250 Markdown, 239 PHP, 2618 core lines",
        "three blank lines: one core-line failure at 2621; significant PHP tokens unchanged",
        "eleven inert PHP files after restoring core: one ratio failure at 250 Markdown / 250 PHP",
        "one empty Markdown file: PASS; 251 Markdown, 250 PHP, 2618 core lines",
    ]


def environment_controls(php, source, work):
    exact = r'''<?php

declare(strict_types=1);

final readonly class SyntheticConfiguration
{
    public function __construct(public string $mode)
    {
    }
}

final class SyntheticEnvironment
{
    public static function forWorker(): SyntheticConfiguration
    {
        $mode = \getenv('ISSUE67_WS_MODE');
        if ($mode !== 'local') {
            throw new InvalidArgumentException('Invalid synthetic configuration.');
        }
        return new SyntheticConfiguration($mode);
    }
}
'''
    enumeration = exact.replace("        $mode = \\getenv('ISSUE67_WS_MODE');", r'''        foreach (\getenv() as $name => $unusedValue) {
            if (str_starts_with($name, 'ISSUE67_WS_') && $name !== 'ISSUE67_WS_MODE') {
                throw new InvalidArgumentException('Invalid synthetic configuration.');
            }
        }
        $mode = \getenv('ISSUE67_WS_MODE');''')
    (work / "ExactEnvironment.php").write_text(exact, encoding="utf-8")
    (work / "EnumerationEnvironment.php").write_text(enumeration, encoding="utf-8")
    (work / "inspect.php").write_text(r'''<?php
declare(strict_types=1);
require $argv[1];
use PHPThis\Verification\EnvironmentAccessProfile;
$exact = EnvironmentAccessProfile::inspect(file_get_contents(__DIR__ . '/ExactEnvironment.php'), 'src/ExactEnvironment.php');
$enumeration = EnvironmentAccessProfile::inspect(file_get_contents(__DIR__ . '/EnumerationEnvironment.php'), 'src/EnumerationEnvironment.php');
$scattered = EnvironmentAccessProfile::boundaryFailures([
    'src/FirstEnvironment.php' => $exact['reads'],
    'src/SecondEnvironment.php' => $exact['reads'],
]);
echo json_encode([$exact, $enumeration, $scattered], JSON_THROW_ON_ERROR);
''', encoding="utf-8")
    code, output, errors = run(
        php + [str(work / "inspect.php"), str(source / "verification/EnvironmentAccessProfile.php")], work,
    )
    require(code == 0 and not errors, "The fixed PHT007 structural controls could not run.")
    exact_result, enumeration_result, scattered = json.loads(output)
    require(exact_result == {"reads": [16], "keys": ["ISSUE67_WS_MODE"], "failures": []},
            "The exact-key control changed.")
    diagnostic = "PHT007 src/EnumerationEnvironment.php:16 must call \\getenv with exactly one non-empty uppercase literal key of at most 128 bytes."
    require(enumeration_result == {"reads": [16, 21], "keys": ["ISSUE67_WS_MODE"], "failures": [diagnostic]},
            "The enumeration control changed.")
    require(scattered == [
        f"PHT007 src/{name}Environment.php:16 reads process environment in more than one application-owned PHP file; centralize every \\getenv call in one configuration boundary."
        for name in ("First", "Second")
    ], "The cross-file control changed.")
    (work / "runtime.php").write_text(r'''<?php
declare(strict_types=1);
require $argv[1];
try {
    SyntheticEnvironment::forWorker();
    fwrite(STDOUT, "accepted\n");
} catch (InvalidArgumentException) {
    fwrite(STDOUT, "rejected\n");
    exit(3);
}
''', encoding="utf-8")
    for name in ("Exact", "Enumeration"):
        for scenario, environment in (
            ("valid", {"ISSUE67_WS_MODE": "local"}),
            ("unknown", {"ISSUE67_WS_MODE": "local", "ISSUE67_WS_UNKNOWN": "synthetic"}),
            ("missing", {}),
            ("invalid", {"ISSUE67_WS_MODE": "invalid"}),
        ):
            accepted = scenario == "valid" or (scenario == "unknown" and name == "Exact")
            expected = (0, b"accepted\n", b"") if accepted else (3, b"rejected\n", b"")
            actual = run(php + [str(work / "runtime.php"), str(work / f"{name}Environment.php")],
                         work, environment=environment, limit=1024, timeout=5)
            require(actual == expected, "A fixed synthetic environment runtime result changed.")
    return [
        "exact-key boundary: zero PHT007 diagnostics",
        "enumerating boundary: one PHT007 diagnostic; five added source lines",
        "two reading files: two PHT007 diagnostics",
        "eight synthetic child cases: PASS; stderr empty; only fixed accepted/rejected output",
        "unknown prefixed name: exact-key boundary accepts; enumerating boundary rejects",
    ]


def main():
    require(len(sys.argv) == 1, "This fixed reproducer accepts no arguments.")
    git, php_binary = shutil.which("git"), shutil.which("php")
    require(git is not None and php_binary is not None, "Git and PHP 8.4 must be available on PATH.")
    # Retain the installed runtime's extension configuration, including tokenizer.
    # Disable file hooks; every child still receives an explicit environment.
    php = [php_binary, "-d", "auto_prepend_file=", "-d", "auto_append_file="]
    require(run(php + ["-r", "exit(PHP_VERSION_ID >= 80400 && PHP_VERSION_ID < 80500 ? 0 : 1);"], ROOT)
            == (0, b"", b""), "This historical reproduction requires PHP 8.4.x.")
    probe = ["-r", "exit(function_exists('token_get_all') ? 0 : 1);"]
    require(run(php + probe, ROOT) == (0, b"", b""),
            "PHP tokenizer must be available in the installed runtime.")
    with tempfile.TemporaryDirectory(prefix="phpthis-issue67-") as directory:
        work = Path(directory)
        source = work / "baseline"
        source.mkdir()
        snapshot(git, source)
        results = size_controls(php, source, work) + environment_controls(php, source, work)
    print(f"PASS fixed historical restriction controls at {BASELINE}")
    print("Snapshot: 519 tracked Git blobs verified; executable modes preserved; temporary copy removed.")
    for result in results:
        print(result)
    print("PHT007 structural stage only; no full application or Composer gate; no real-consumer migration proof.")


if __name__ == "__main__":
    try:
        main()
    except ReproductionFailure as failure:
        print(f"FAIL {failure}", file=sys.stderr)
        sys.exit(1)
    except (OSError, ValueError, subprocess.SubprocessError):
        print("FAIL fixed historical reproduction could not verify all expected controls; no captured subprocess output was disclosed.", file=sys.stderr)
        sys.exit(1)
