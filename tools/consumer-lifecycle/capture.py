#!/usr/bin/env python3
"""Capture one complete consumer gate and its actual working-file identities."""
import argparse
import datetime
import hashlib
import json
from pathlib import Path
import subprocess
import time

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument("consumer", type=Path)
parser.add_argument("output", type=Path, help="output stem for .log and .json")
args = parser.parse_args()
app = args.consumer.resolve()
if any(args.output.with_suffix(suffix).exists() for suffix in (".log", ".json")):
    parser.error("Choose a new output stem; retained evidence must not be overwritten.")
args.output.parent.mkdir(parents=True, exist_ok=True)
started = datetime.datetime.now(datetime.timezone.utc)
tick = time.monotonic()
result = subprocess.run(["composer", "check"], cwd=app, text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
args.output.with_suffix(".log").write_text(result.stdout)
files = subprocess.check_output(["git", "ls-files", "--cached", "--others", "--exclude-standard", "-z"], cwd=app).decode().split("\0")
identities = {}
for name in sorted(set(files) - {""}):
    path = app / name
    if path.is_file():
        data = path.read_bytes()
        identities[name] = {"bytes": len(data), "lines": len(data.splitlines()), "sha256": hashlib.sha256(data).hexdigest()}
lock = json.loads((app / "composer.lock").read_text())
engine_program = r"""require $argv[1]; $connection = PHPThis\Database\Connection::connect('sqlite::memory:', new PHPThis\Database\QueryBudget(1), new PHPThis\Database\QueryTrace(1)); echo json_encode($connection->selectOneRow('SELECT sqlite_version() AS version'), JSON_THROW_ON_ERROR);"""
engine = json.loads(subprocess.check_output(["php", "-r", engine_program, str(app / "vendor/autoload.php")], text=True))
record = {"started_at": started.isoformat(), "finished_at": datetime.datetime.now(datetime.timezone.utc).isoformat(),
    "elapsed_seconds": round(time.monotonic() - tick, 3), "command": ["composer", "check"], "exit_code": result.returncode,
    "head_before_capture": subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=app, text=True).strip(),
    "php": subprocess.check_output(["php", "-r", "echo PHP_VERSION;"], text=True),
    "sqlite": engine["version"],
    "python": subprocess.check_output(["python3", "--version"], text=True).strip(),
    "dependencies": [{"name": p["name"], "version": p["version"], "source": p.get("source"), "dist": p.get("dist")}
                     for p in lock["packages"] + lock["packages-dev"]],
    "files": identities, "model_usage": None,
    "measurement_limits": "Elapsed time covers the gate and capture work through hashing/engine inspection, not active authoring labor. File hashes include actual tracked and nonignored working files. Read inventory and authoring/review effort are recorded separately; no measured model token/cost claim."}
args.output.with_suffix(".json").write_text(json.dumps(record, indent=2) + "\n")
print(json.dumps({k: record[k] for k in ("exit_code", "elapsed_seconds", "php", "python")}, indent=2))
print("\n".join(result.stdout.splitlines()[-8:]))
raise SystemExit(result.returncode)
