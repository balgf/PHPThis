#!/usr/bin/env python3
"""Read-only, pinned SQL-maintenance evidence; writes only a temporary directory."""

import collections
import difflib
import json
import os
from pathlib import Path
import re
import selectors
import subprocess
import tempfile
import time


ROOT = Path(__file__).resolve().parents[2]
REVISION = "de7353ec2ff881cd85392dd59a6bd08ab7e9d64f"
INTRODUCTION = "3f337be5de5631fee5dd08a204dd830fe67c23b6"
MAINTENANCE = "d4ec6c3d281a02a918cc2fa8b4077aa45ebac6be"
PHT008 = "c3b5d9dc5d9090a8d892e2c34cc5b4bbcf683b72"
ACCOUNT_MAINTENANCE = "b5b71ef6289d32982b99b08c57315fe2864eaaec"
HANDLER = "example/src/Documents/ListDocuments/ListDocumentsHandler.php"
ACCOUNT_OPERATION = "example/src/Users/CreateUser/TransactionalCreateUser.php"
VARIANTS = ("legacy", "partial", "unbound", "repaired")


def capture(arguments, deadline_seconds=60, stream_limit=1_048_576):
    """Bound each pipe and deadline for fixed Git, PHPStan, and fixture commands."""
    process = subprocess.Popen(
        arguments, cwd=ROOT, stdin=subprocess.DEVNULL,
        stdout=subprocess.PIPE, stderr=subprocess.PIPE,
    )
    streams = [bytearray(), bytearray()]
    deadline = time.monotonic() + deadline_seconds
    try:
        with selectors.DefaultSelector() as selector:
            selector.register(process.stdout, selectors.EVENT_READ, 0)
            selector.register(process.stderr, selectors.EVENT_READ, 1)
            while selector.get_map():
                remaining = deadline - time.monotonic()
                if remaining <= 0:
                    raise RuntimeError("SQL review command exceeded its deadline.")
                for key, _ in selector.select(min(remaining, 0.1)):
                    chunk = os.read(key.fileobj.fileno(), 65_536)
                    if not chunk:
                        selector.unregister(key.fileobj)
                        continue
                    streams[key.data].extend(chunk)
                    if len(streams[key.data]) > stream_limit:
                        raise RuntimeError("SQL review command exceeded its output bound.")
            status = process.wait(timeout=max(0.01, deadline - time.monotonic()))
    finally:
        if process.poll() is None:
            process.kill()
        process.wait(timeout=5)
        process.stdout.close()
        process.stderr.close()
    return status, streams[0].decode(), streams[1].decode()


def checked(arguments):
    status, stdout, _ = capture(arguments)
    if status != 0:
        raise RuntimeError("SQL review prerequisite or Git source read failed.")
    return stdout


def source(path, revision=REVISION):
    return checked(["git", "show", revision + ":" + path])


def history(revision, operation=HANDLER):
    rows = []
    records = iter(checked(["git", "show", "--numstat", "-z", "--format=", revision]).split("\0"))
    for record in records:
        if not record:
            continue
        added, deleted, path = record.split("\t", 2)
        if not path:
            next(records)  # Rename source; classify the destination while counting one change.
            path = next(records)
        rows.append((int(added), int(deleted), path))
    predicates = {
        "all": lambda path: True,
        "php": lambda path: path.endswith(".php"),
        "markdown": lambda path: path.endswith(".md"),
        "example_php": lambda path: path.startswith("example/") and path.endswith(".php"),
        "tests_php": lambda path: path.startswith("tests/") and path.endswith(".php"),
        "tools_php": lambda path: path.startswith("tools/") and path.endswith(".php"),
        "framework_src": lambda path: path.startswith("src/"),
    }
    result = {"subject": checked(["git", "show", "-s", "--format=%s", revision]).strip()}
    for label, predicate in predicates.items():
        selected = [row for row in rows if predicate(row[2])]
        result[label] = {
            "files": len(selected), "added": sum(row[0] for row in selected),
            "deleted": sum(row[1] for row in selected),
        }
    result["handler"] = [row[:2] for row in rows if row[2] == operation]
    return result


def account_maintenance():
    before = source(ACCOUNT_OPERATION, ACCOUNT_MAINTENANCE + "^")
    after = source(ACCOUNT_OPERATION, ACCOUNT_MAINTENANCE)
    measures = {}
    statements = {}
    for label, body in (("before", before), ("after", after)):
        statements[label] = [nowdoc or literal for nowdoc, literal in re.findall(
            r"->executeStatement\(\s*(?:<<<'SQL'\n(.*?)\n\s+SQL,|'([^']*)')", body, re.S,
        )]
        measures[label] = {
            "lines": len(body.splitlines()), "bytes": len(body.encode()),
            "sql_sites": len(statements[label]),
            "binding_entries": len(re.findall(r"['\"][a-z_]+['\"]\s*=>", body)),
            "tenant_value_bindings": body.count("=> $tenant->accountId->value"),
            "requested_account_bindings": body.count("=> $accountId->value"),
            "actor_bindings": body.count("=> $principal->id"),
            "account_equality_predicates": len(re.findall(r":\w*requested\w* = :\w*resolved\w*", body)),
            "membership_principal_predicates": len(re.findall(r"memberships\.principal_id = :", body)),
        }
    if len(statements["before"]) != 3 or len(statements["after"]) != 4:
        raise RuntimeError("Historical account-maintenance SQL shape changed.")
    change = history(ACCOUNT_MAINTENANCE, ACCOUNT_OPERATION)
    change["operation_owner"] = {"path": ACCOUNT_OPERATION, "numstat_added_deleted": change.pop("handler")}
    return {
        "revision": ACCOUNT_MAINTENANCE,
        "parent": checked(["git", "rev-parse", ACCOUNT_MAINTENANCE + "^"]).strip(),
        "classification": "Actual accepted ADR 029 maintenance of the existing Create operation; not a synthetic repair.",
        "commit": change, "measures": measures,
        "existing_sql_statements_changed": sum(statements["before"][old] != statements["after"][new] for old, new in ((0, 0), (1, 2), (2, 3))),
        "sql_statements_added": 1,
        "current_operation_matches_accepted_commit": source(ACCOUNT_OPERATION) == after,
        "limits": "The new relation requires an additional statement; total churn is not a measured cost attributable solely to the no-helper rule. No helper counterfactual or historical review time was measured.",
    }


RUNTIME = r'''<?php
declare(strict_types=1);
require $argv[1] . '/autoload.php';
require __DIR__ . '/variants/' . $argv[2] . '.php.fixture';
require __DIR__ . '/tests/request-reader-support.php';
require __DIR__ . '/tests/request-policy.php';
$passed = [];
$failed = [];
foreach (requestPolicyTests() as $name => $behavior) {
    if (!str_contains($name, 'document list') || str_contains($name, 'source uses direct')) {
        continue;
    }
    try {
        $behavior();
        $passed[] = $name;
    } catch (Throwable $failure) {
        $failed[] = ['name' => $name, 'class' => $failure::class];
    }
}
$observations = [];
foreach ([3, 500] as $size) {
    $databasePath = createDocumentListDatabaseFixture('review-sweep-' . $size, $size);
    foreach (['rank_asc', 'rank_desc'] as $order) {
        foreach ([null, ['alpha'], ['alpha', 'beta'], ['alpha', 'beta', 'gamma']] as $categories) {
            $query = ['order' => $order];
            if ($categories !== null) {
                $query['categories'] = $categories;
            }
            $page = runDocumentListPageScenario($databasePath, $query);
            $observations[] = [count($page['document_keys']), $page['used'], $page['statements'], hash('sha256', $page['body'])];
            if ($page['next_cursor'] !== null) {
                $query['cursor'] = $page['next_cursor'];
                $next = runDocumentListPageScenario($databasePath, $query);
                $observations[] = [count($next['document_keys']), $next['used'], $next['statements'], hash('sha256', $next['body'])];
            }
        }
    }
}
$emptyCosts = [];
foreach (['rank_asc', 'rank_desc'] as $order) {
    foreach ([[], ['']] as $categories) {
        $empty = runDocumentListPageScenario($databasePath, ['order' => $order, 'categories' => $categories]);
        $emptyCosts[] = [count($empty['document_keys']), $empty['used'], $empty['statements']];
    }
}
$connection = PHPThis\Database\Connection::connect('sqlite::memory:', new PHPThis\Database\QueryBudget(1), new PHPThis\Database\QueryTrace(1));
$version = $connection->selectOneRow('SELECT sqlite_version() AS version');
$inputs = [];
foreach (get_included_files() as $path) {
    if (str_starts_with($path, $argv[1] . '/')) {
        $inputs[] = substr($path, strlen($argv[1]) + 1);
    }
}
echo json_encode([
    'php' => PHP_VERSION, 'sqlite' => $version['version'] ?? null,
    'passed' => $passed, 'failed' => $failed, 'observations' => $observations,
    'empty_costs' => $emptyCosts, 'runtime_repository_inputs' => $inputs,
    'runtime_total_included_files' => count(get_included_files()),
], JSON_THROW_ON_ERROR) . "\n";
exit($failed === [] ? 0 : 1);
'''


def main():
    # Future runs must not silently mix the pinned handler with changed runtime/checker code.
    checked(["git", "diff", "--exit-code", REVISION, "--", "autoload.php", "src", "example",
             "verification", "phpstan.neon", "composer.lock"])
    current = source(HANDLER)
    binding = "                        'membership_tenant_account_id' => $tenant->accountId->value,\n"
    if current.count(binding) != 8:
        raise RuntimeError("Pinned document-list source shape changed.")
    legacy = current.replace(":membership_tenant_account_id", ":resolved_tenant_account_id").replace(binding, "")
    partial = legacy.replace(":resolved_tenant_account_id\n                          )", ":membership_tenant_account_id\n                          )", 7)
    partial = partial.replace("                        'principal_id' => $principal->id,\n", "                        'principal_id' => $principal->id,\n" + binding, 7)
    variants = {"legacy": legacy, "partial": partial, "unbound": current.replace(binding, "", 1), "repaired": current}
    results = {}
    with tempfile.TemporaryDirectory(prefix="phpthis-issue67-sql-") as directory:
        scratch = Path(directory)
        (scratch / "variants").mkdir()
        (scratch / "tests").mkdir()
        for name, contents in variants.items():
            (scratch / "variants" / (name + ".php.fixture")).write_text(contents)
        for name in ("request-policy.php", "request-reader-support.php"):
            (scratch / "tests" / name).write_text(source("tests/" + name))
        (scratch / "run.php").write_text(RUNTIME)
        # Inherit the complete reviewed profile; override only its writable cache location.
        (scratch / "phpstan.neon").write_text(
            "includes:\n    - " + json.dumps(str(ROOT / "phpstan.neon"))
            + "\nparameters:\n    tmpDir: " + json.dumps(str(scratch / "phpstan-cache")) + "\n"
        )
        for name in VARIANTS:
            status, stdout, _ = capture([
                "php", "-d", "disable_functions=proc_open", str(ROOT / "vendor/bin/phpstan"),
                "analyse", "--configuration=" + str(scratch / "phpstan.neon"), "--no-progress",
                "--memory-limit=512M", "--error-format=json", str(scratch / "variants" / (name + ".php.fixture")),
            ])
            static = json.loads(stdout)
            identifiers = collections.Counter(
                message["identifier"] for file in static["files"].values() for message in file["messages"]
            )
            runtime_status, stdout, _ = capture(["php", "-d", "disable_functions=proc_open", str(scratch / "run.php"), str(ROOT), name])
            results[name] = {"static_exit": status, "static_errors": static["totals"],
                             "identifiers": dict(identifiers), "runtime_exit": runtime_status,
                             "runtime": json.loads(stdout)}
    expected = {"legacy": (8, 11, 0), "partial": (1, 11, 0), "unbound": (0, 7, 4), "repaired": (0, 11, 0)}
    for name, (findings, passed, failed) in expected.items():
        result = results[name]
        wanted = {"phpthis.pht008": findings} if findings else {}
        if (result["identifiers"] != wanted or result["static_errors"] != {"errors": 0, "file_errors": findings}
                or result["static_exit"] != int(findings > 0) or result["runtime_exit"] != int(failed > 0)
                or len(result["runtime"]["passed"]) != passed or len(result["runtime"]["failed"]) != failed):
            raise RuntimeError("Pinned SQL rehearsal outcomes changed.")
    repaired = results["repaired"]["runtime"]
    if (len(repaired["observations"]) != 24 or any(row[1:3] != [1, 1] or row[0] > 50 for row in repaired["observations"])
            or repaired["empty_costs"] != [[0, 0, 0]] * 4
            or any(results[name]["runtime"]["observations"] != repaired["observations"] for name in ("legacy", "partial"))):
        raise RuntimeError("SQL rehearsal behavior or resource bounds changed.")
    pattern = r"<<<'SQL'\n(.*?)\n\s+SQL,"
    statements = re.findall(pattern, current, re.S)
    historical = re.findall(pattern, source(HANDLER, INTRODUCTION), re.S)
    patch = list(difflib.unified_diff(legacy.splitlines(), current.splitlines()))
    report = {
        "revision": REVISION, "classification": "Historical source evidence plus synthetic repair sensitivity; not a production migration.",
        "history": {revision: history(revision) for revision in (INTRODUCTION, MAINTENANCE, PHT008)},
        "account_maintenance": account_maintenance(),
        "sql_bodies_unchanged_since_introduction": statements == historical,
        "repair": {"modeled_php_files": 1, "modeled_markdown_files": 0, "repository_files_mutated": 0,
                   "php_added": sum(line.startswith('+') and not line.startswith('+++') for line in patch),
                   "php_deleted": sum(line.startswith('-') and not line.startswith('---') for line in patch),
                   "sql_sites": len(statements), "renamed_occurrences": 8, "added_bindings": 8,
                   "handler_lines": len(current.splitlines()), "handler_bytes": len(current.encode()),
                   "sql_body_lines": sum(len(statement.splitlines()) for statement in statements),
                   "placeholder_occurrences": sum(len(re.findall(r":[A-Za-z_][A-Za-z0-9_]*", statement)) for statement in statements)},
        "runs": results,
        "limits": ["Focused PHPStan analysis and 11 existing behaviors; no full composer check or installed-consumer gate.",
                   "Four .php.fixture variants and two copied test inputs; 24 positive sweep pages and four zero-SQL empty selections.",
                   "SQLite only; no production engine plan, authority, concurrency, complete-app outcome, or human review-time measurement.",
                   "Existing dependencies reused read-only; individual commands bounded to 60 seconds and 1 MiB per captured stream."],
    }
    print(json.dumps(report, indent=2))


if __name__ == "__main__":
    main()
