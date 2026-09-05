#!/usr/bin/env python3
"""Opt-in real OCI/Codex integration against a fixed local no-paid API fixture.

Usage: python3 tools/test-agent-evaluation-controller-oci.py /absolute/config.json
The configuration must reference already built digest-pinned images and already
reviewed locked dependencies. This test never builds, downloads, or uses a key.
"""

import argparse
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import selectors
import shutil
import signal
import subprocess
import sys
import tempfile
import time
import traceback

sys.dont_write_bytecode = True

ROOT = Path(__file__).resolve().parent.parent
FIXTURE_PATH = ROOT / "tools/agent-evaluation-controller/oci/fixture-upstream.py"
CREDENTIAL_SENTINEL = "phpthis-integration-ambient-sentinel"
PHP_WORKER = r"""
define('PHPTHIS_AGENT_EVALUATION_CONTROLLER_LIBRARY_ONLY', true);
define('PHPTHIS_AGENT_EVALUATION_CONTROLLER_TESTING', true);
define('AGENT_EVALUATION_CONTROLLER_OCI_TEST_UPSTREAM', true);
require $argv[1] . '/tools/agent-evaluation-controller.php';
try {
    $configuration = agentEvaluationControllerReadLiveConfiguration($argv[2]);
    $approval = agentEvaluationRequireObject($configuration, 'approval', 'integration approval');
    if ($approval['spending_ceiling_usd'] !== '0.00') {
        throw new RuntimeException('Integration requires a zero-spend synthetic approval record.');
    }
    $result = agentEvaluationControllerExecuteLive(
        $argv[1], $argv[3],
        ['run_id' => $argv[4], 'task_id' => AGENT_EVALUATION_CONTROLLER_TASK_ID],
        $configuration, '',
    );
    fwrite(STDOUT, json_encode(['status' => 'completed', 'result' => $result], JSON_THROW_ON_ERROR) . "\n");
} catch (Throwable $failure) {
    fwrite(STDOUT, json_encode(['status' => 'failed', 'class' => $failure::class, 'message' => $failure->getMessage(),
        'source' => basename($failure->getFile()) . ':' . $failure->getLine()], JSON_THROW_ON_ERROR) . "\n");
    exit(1);
}
"""
PHP_BOUND_WORKER = r"""
define('PHPTHIS_AGENT_EVALUATION_CONTROLLER_LIBRARY_ONLY', true);
define('PHPTHIS_AGENT_EVALUATION_CONTROLLER_TESTING', true);
define('AGENT_EVALUATION_CONTROLLER_OCI_TEST_UPSTREAM', true);
require $argv[1] . '/tools/agent-evaluation-controller.php';
$workspace = null;
$resources = null;
$control = null;
$result = null;
$interruptHandlers = null;
try {
    $interruptHandlers = agentEvaluationControllerInstallInterruptHandlers();
    $configuration = agentEvaluationControllerReadLiveConfiguration($argv[2]);
    $task = agentEvaluationTask($argv[1] . '/tools/agent-evaluation', AGENT_EVALUATION_CONTROLLER_TASK_ID);
    $workspace = agentEvaluationControllerPrepareWorkspace($argv[1] . '/skeleton', $configuration['prepared_dependencies'], $argv[3], $task);
    $control = agentEvaluationControllerCreatePreflightRoot();
    $engine = agentEvaluationControllerOciPreflight($configuration['engine'], $control);
    $resources = agentEvaluationControllerOciPrepare($engine, $argv[4], $workspace['candidate_root'], $workspace['dependencies_root']);
    $profile = $configuration['profile'];
    // These are lower-level adapter controls. No task manifest or v0.1 run
    // record receives these deliberately smaller synthetic limits.
    if ($argv[5] === 'wall-bound') { $profile['budgets']['wall_seconds'] = 2; }
    elseif ($argv[5] === 'output-bound') { $profile['budgets']['command_output_bytes'] = 16_384; }
    else { throw new RuntimeException('Unexpected fixed primitive control.'); }
    $observed = agentEvaluationControllerOciRunGeneration($resources, 'Deterministic OCI resource-bound fixture.', $profile, '');
    $result = ['status' => 'completed', 'termination_reason' => $observed['termination_reason'],
        'elapsed_milliseconds' => $observed['elapsed_milliseconds'], 'timed_out' => $observed['timed_out'],
        'output_limit_exceeded' => $observed['output_limit_exceeded'], 'stderr' => substr($observed['stderr'], 0, 16_384),
        'event_bytes' => strlen($observed['events_jsonl']), 'stderr_bytes' => strlen($observed['stderr']),
        'resource_identity' => ['owner' => $resources['owner'], 'run_id' => $resources['run_id']]];
} catch (Throwable $failure) {
    $result = ['status' => 'failed', 'message' => $failure->getMessage(), 'source' => basename($failure->getFile()) . ':' . $failure->getLine()];
} finally {
    try {
        $cleanupVerified = $resources === null && $control === null;
        if ($resources !== null) {
            $result['oci_cleanup'] = agentEvaluationControllerOciCleanup($resources);
            $cleanupVerified = $result['oci_cleanup']['verified'] === true && $result['oci_cleanup']['status'] === 'pass';
        }
        if ($cleanupVerified) {
            if ($workspace !== null) { $result['workspace_cleanup'] = ['status' => 'pass', 'removed' => agentEvaluationControllerCleanupWorkspace($workspace)]; }
            if ($control !== null) { agentEvaluationControllerRemoveTree($control); }
        } else {
            $result['status'] = 'failed';
            $result['message'] = 'Resource-control cleanup requires review';
            $result['recovery_control_root'] = $control;
        }
    } catch (Throwable $failure) {
        $result['status'] = 'failed';
        $result['message'] = 'Resource-control cleanup failed';
        $result['recovery_control_root'] = $control;
        $result['source'] = basename($failure->getFile()) . ':' . $failure->getLine();
    } finally {
        if ($interruptHandlers !== null) { agentEvaluationControllerRestoreInterruptHandlers($interruptHandlers); }
    }
}
fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR) . "\n");
exit($result['status'] === 'completed' ? 0 : 1);
"""


def bounded_worker(arguments, server, interrupt=False):
    environment = {"PATH": "/usr/local/bin:/usr/bin:/bin", "LANG": "C", "LC_ALL": "C",
                   "PHPTHIS_OCI_AMBIENT_SENTINEL": CREDENTIAL_SENTINEL}
    process = subprocess.Popen(arguments, cwd=ROOT, env=environment, stdout=subprocess.PIPE,
                               stderr=subprocess.PIPE, start_new_session=True)
    selector = selectors.DefaultSelector()
    selector.register(process.stdout, selectors.EVENT_READ, "stdout")
    selector.register(process.stderr, selectors.EVENT_READ, "stderr")
    output = {"stdout": bytearray(), "stderr": bytearray()}
    deadline = time.monotonic() + 600
    interrupted = False
    active_at = None
    try:
        while selector.get_map():
            if time.monotonic() >= deadline:
                raise RuntimeError("OCI integration worker exceeded its independent 600-second bound")
            if interrupt and server.responses > 0:
                active_at = active_at or time.monotonic()
                if not interrupted and time.monotonic() - active_at >= 2:
                    process.send_signal(signal.SIGTERM)
                    interrupted = True
            for key, _ in selector.select(0.2):
                data = os.read(key.fd, 65536)
                if not data:
                    selector.unregister(key.fileobj)
                    continue
                output[key.data].extend(data)
                if sum(map(len, output.values())) > 1_048_576:
                    raise RuntimeError("OCI integration worker exceeded its independent output bound")
        return process.wait(timeout=5), bytes(output["stdout"]), bytes(output["stderr"])
    finally:
        selector.close()
        if process.poll() is None:
            process.send_signal(signal.SIGTERM)
            try:
                process.wait(timeout=15)
            except subprocess.TimeoutExpired:
                os.killpg(process.pid, signal.SIGKILL)
                process.wait(timeout=5)


def verify_evidence(run_root, should_pass, expected_failure_phase=None):
    evidence = run_root / "evidence"
    manifest = json.loads((evidence / "evidence-manifest.json").read_text())
    assert manifest["synthetic"] is False
    assert manifest["comparative_claims"] is False
    for artifact, descriptor in manifest["artifacts"].items():
        path = evidence / artifact
        assert path.is_file() and not path.is_symlink()
        data = path.read_bytes()
        assert CREDENTIAL_SENTINEL.encode() not in data, "Ambient host sentinel entered retained evidence"
        assert len(data) == descriptor["bytes"]
        assert hashlib.sha256(data).hexdigest() == descriptor["sha256"]
    assert [path.name for path in run_root.iterdir()] == ["evidence"], "Disposable workspace survived cleanup"
    assert manifest["cleanup_failure"] is None
    if should_pass:
        assert manifest["observed_phases"] == ["prepare", "generate", "freeze", "score", "validate", "retain", "cleanup"]
        assert manifest["primary_failure"] is None
        score = json.loads((evidence / "score.json").read_text())
        assert score["automated_status"] == "pass", "The application gate or public scorer failed; inspect retained scoring artifacts"
        assert score["admissible"] is True
        events = [json.loads(line) for line in (evidence / "events.jsonl").read_text().splitlines()]
        commands = [event["item"] for event in events if event.get("type") == "item.completed" and event.get("item", {}).get("type") == "command_execution"]
        assert any(command.get("exit_code") == 0 and "PASS OCI generation containment and application checks" in command.get("aggregated_output", "")
                   and "PASS escaped-session descendant created for OCI cleanup" in command.get("aggregated_output", "")
                   for command in commands), "Reviewed generation command must actually complete its containment and unchanged application checks"
        assert (evidence / "freeze.json").is_file()
        assert (evidence / "generation-cleanup.json").is_file()
    else:
        assert manifest["primary_failure"] is not None
        assert manifest["primary_failure"]["phase"] == expected_failure_phase, "Control failed before reaching its intended boundary"
    return {"phases": manifest["observed_phases"], "artifacts": len(manifest["artifacts"]),
            "cleanup": "verified", "expected_run_success": should_pass, "control_status": "pass"}


def verify_engine_absence(reviewed, identity, temporary_root):
    owner = identity.get("owner")
    run_id = identity.get("run_id")
    assert isinstance(owner, str) and re.fullmatch(r"phpthis-eval-[a-f0-9]{32}", owner)
    assert isinstance(run_id, str) and re.fullmatch(r"[a-f0-9]{32}", run_id)
    environment = {"PATH": "/usr/bin:/bin", "LANG": "C", "LC_ALL": "C"}
    with tempfile.TemporaryDirectory(prefix="engine-absence-", dir=temporary_root) as config_root:
        config = Path(config_root) / "config.json"
        config.write_text("{}\n")
        config.chmod(0o600)
        base = [reviewed["engine"]["docker_binary"], "--config", config_root,
                "--host", "unix://" + reviewed["engine"]["docker_socket"]]
        filters = ["--filter", "label=org.phpthis.evaluation.owner=" + owner,
                   "--filter", "label=org.phpthis.evaluation.run_id=" + run_id]
        for operation in [["container", "ls", "--all", "--quiet"], ["volume", "ls", "--quiet"]]:
            process = subprocess.Popen(base + operation + filters, cwd=config_root, env=environment,
                                       stdout=subprocess.PIPE, stderr=subprocess.STDOUT, start_new_session=True)
            selector = selectors.DefaultSelector()
            selector.register(process.stdout, selectors.EVENT_READ)
            output = bytearray()
            deadline = time.monotonic() + 10
            try:
                while selector.get_map():
                    if time.monotonic() >= deadline:
                        raise RuntimeError("Independent engine absence query exceeded ten seconds")
                    for key, _ in selector.select(0.1):
                        data = os.read(key.fd, 4096)
                        if not data:
                            selector.unregister(key.fileobj)
                        output.extend(data)
                        if len(output) > 65_536:
                            raise RuntimeError("Independent engine absence query exceeded its output bound")
                assert process.wait(timeout=1) == 0 and output == b"", "Owned OCI resources remain or the independent query failed"
            finally:
                selector.close()
                if process.poll() is None:
                    os.killpg(process.pid, signal.SIGKILL)
                    process.wait(timeout=5)
                process.stdout.close()
    return {"verified": True, "containers_remaining": 0, "volumes_remaining": 0,
            "owner": owner, "run_id": run_id}


def main():
    if not __debug__:
        raise RuntimeError("OCI integration requires Python assertions; optimized execution is unsupported")
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("configuration", type=Path)
    parser.add_argument("--retain-evidence", action="store_true", help="Keep validated evidence in the generated private temporary root")
    parser.add_argument("--case", choices=["all", "complete", "scoring-boundary", "workspace-escape", "symlink", "file-mode", "relay-signal", "pids-limit", "memory-limit", "disk-limit", "token-limit", "interrupt", "wall-bound", "output-bound"], default="all")
    options = parser.parse_args()
    configuration = options.configuration.resolve(strict=True)
    with configuration.open("rb") as source:
        configuration_bytes = source.read(65_537)
    if not 1 <= len(configuration_bytes) <= 65_536:
        raise RuntimeError("Integration configuration must fit the controller's 64 KiB limit")
    reviewed = json.loads(configuration_bytes)
    identities = {"configuration_sha256": hashlib.sha256(configuration_bytes).hexdigest(),
                  "generation_image": reviewed["engine"]["generation_image"],
                  "scoring_image": reviewed["engine"]["scoring_image"],
                  "generation_toolchain": reviewed["engine"]["generation_toolchain"],
                  "scoring_toolchain": reviewed["engine"]["scoring_toolchain"],
                  "prepared_dependencies_sha256": reviewed["prepared_dependencies_sha256"],
                  "prepared_lock_sha256": reviewed["prepared_lock_sha256"]}
    php = shutil.which("php")
    if php is None:
        raise RuntimeError("PHP 8.4 must be explicitly installed before integration")
    spec = importlib.util.spec_from_file_location("phpthis_oci_fixture", FIXTURE_PATH)
    if spec is None or spec.loader is None:
        raise RuntimeError("The fixed local fixture module is unavailable")
    fixture = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(fixture)
    server, thread = fixture.start_fixture()
    results = {}
    report = {"suite": "real-codex-local-fixture", "paid_requests": 0, "comparative_claims": False,
              "reviewed_inputs": identities, "results": results}
    temporary_root = Path(tempfile.mkdtemp(prefix="phpthis-agent-evaluation-oci-integration-")).resolve()
    os.chmod(temporary_root, 0o700)
    cases = ["complete", "scoring-boundary", "workspace-escape", "symlink", "file-mode", "relay-signal", "pids-limit", "memory-limit", "disk-limit", "token-limit", "interrupt", "wall-bound", "output-bound"] if options.case == "all" else [options.case]
    try:
        for index, case in enumerate(cases, 1):
            server.reset("wall-limit" if case in ["interrupt", "wall-bound"] else case)
            run_root = temporary_root / case
            run_id = hashlib.sha256((str(temporary_root) + case).encode()).hexdigest()[:32]
            primitive = case in ["wall-bound", "output-bound"]
            worker = PHP_BOUND_WORKER if primitive else PHP_WORKER
            code, stdout, stderr = bounded_worker([php, "-r", worker, str(ROOT), str(configuration), str(run_root), run_id, case], server, case == "interrupt")
            if server.failure is not None:
                raise RuntimeError("Deterministic fixture failed: " + server.failure)
            result = json.loads(stdout)
            if primitive:
                expected_reason = "wall_time_limit" if case == "wall-bound" else "output_limit"
                if code != 0 or result.get("termination_reason") != expected_reason:
                    raise RuntimeError("Unexpected primitive " + case + " result: " + json.dumps(result))
                assert result["oci_cleanup"]["status"] == "pass"
                assert result["workspace_cleanup"]["status"] == "pass"
                assert server.responses >= 1, "Resource control never reached the real Codex Responses transport"
                if case == "wall-bound":
                    assert result["timed_out"] is True and result["output_limit_exceeded"] is False
                    assert 2_000 <= result["elapsed_milliseconds"] <= 10_000, "Two-second wall control exceeded its ten-second teardown allowance"
                else:
                    assert result["output_limit_exceeded"] is True and result["timed_out"] is False
                    assert result["event_bytes"] + result["stderr_bytes"] <= 16_384
                    assert server.responses == 2, "Output fixture must reach its oversized final response"
                assert [path.name for path in run_root.iterdir()] == ["evidence"]
                result["independent_engine_cleanup"] = verify_engine_absence(reviewed, result["resource_identity"], temporary_root)
                result["fixture_requests"] = server.requests
                result["control_status"] = "pass"
                results[case] = result
                print("PASS OCI integration " + case, flush=True)
                continue
            expected_pass = case in ["complete", "scoring-boundary"]
            if (code == 0) != expected_pass:
                raise RuntimeError("Unexpected " + case + " result: " + json.dumps(result) + " " + stderr.decode(errors="replace"))
            expected_phase = "freeze" if case in ["workspace-escape", "symlink", "file-mode"] else "generate"
            evidence_result = verify_evidence(run_root, expected_pass, expected_phase)
            mutation_markers = {"workspace-escape": "PASS OCI unlisted file mutation", "symlink": "PASS OCI symlink mutation",
                                "file-mode": "PASS OCI file mode mutation"}
            if case in mutation_markers:
                events = [json.loads(line) for line in (run_root / "evidence/events.jsonl").read_text().splitlines()]
                assert any(event.get("type") == "item.completed" and event.get("item", {}).get("type") == "command_execution"
                           and event["item"].get("exit_code") == 0 and mutation_markers[case] in event["item"].get("aggregated_output", "")
                           for event in events), "Freeze control must successfully apply and verify its intended mutation"
                manifest = json.loads((run_root / "evidence/evidence-manifest.json").read_text())
                expected_marker = {"symlink": "AGENT_EVALUATION_CONTROLLER_OCI_ARCHIVE_ENTRY_INVALID",
                                   "file-mode": "AGENT_EVALUATION_CONTROLLER_OCI_CANDIDATE_MODE_CHANGED"}.get(case)
                if expected_marker is not None:
                    assert manifest["primary_failure"]["code"] == expected_marker
                evidence_result["mutation_verified"] = True
                evidence_result["failure_code"] = manifest["primary_failure"].get("code")
            if case == "token-limit":
                assert server.responses == 0 and len(server.requests) == 1
            else:
                assert server.responses >= 1, "Control never reached the real Codex Responses transport"
            if case == "scoring-boundary":
                check = json.loads((run_root / "evidence/application-check.json").read_text())
                assert "PASS OCI scoring immutability controls" in check["stdout"]
            expected_reasons = {"memory-limit": "memory_limit", "pids-limit": "process_limit",
                                "disk-limit": "disk_limit", "token-limit": "model_token_limit"}
            if case in expected_reasons or case in ["interrupt", "relay-signal"]:
                generation = json.loads((run_root / "evidence/generation-process.json").read_text())
                if case in expected_reasons:
                    assert generation["termination_reason"] == expected_reasons[case], "Unrelated generation failure cannot satisfy a resource control"
                    if case == "disk-limit":
                        assert generation["resource_observation"]["disk_free_bytes"]["workspace_tmp"] == 0
                        evidence_result["control"] = "persistent full scratch mount"
                    elif case == "pids-limit":
                        assert generation["resource_observation"]["pids_events"]["max"] > 0
                    elif case == "memory-limit":
                        observation = generation["resource_observation"]
                        assert observation is not None and observation["memory_events"]["oom"] > 0 and observation["memory_events"]["oom_kill"] > 0
                elif case == "interrupt":
                    assert generation["failure_code"] == "AGENT_EVALUATION_CONTROLLER_INTERRUPTED"
                else:
                    assert generation["termination_reason"] == "process_failed"
                    assert generation["failure_code"] == "AGENT_EVALUATION_CONTROLLER_OCI_RELAY_INCOMPLETE"
                    assert "KeyboardInterrupt" in (run_root / "evidence/generation.stderr").read_text()
                    events = [json.loads(line) for line in (run_root / "evidence/events.jsonl").read_text().splitlines()]
                    assert any(event.get("type") == "item.started" and "os.kill(1, signal.SIGINT)" in event.get("item", {}).get("command", "") for event in events)
                evidence_result["termination_reason"] = generation["termination_reason"]
                evidence_result["failure_code"] = generation["failure_code"]
                evidence_result["resource_observation"] = generation["resource_observation"]
            evidence_result["fixture_requests"] = server.requests
            identity = json.loads((run_root / "evidence/owned-resources.json").read_text())
            assert identity["run_id"] == run_id
            evidence_result["independent_engine_cleanup"] = verify_engine_absence(reviewed, identity, temporary_root)
            results[case] = evidence_result
            print("PASS OCI integration " + case, flush=True)
        print(json.dumps(report, sort_keys=True))
    finally:
        server.shutdown()
        server.server_close()
        thread.join(timeout=5)
        # Every run's controller cleanup is asserted before the harness removes
        # its own evidence copies. Failing evidence remains available for review.
        if len(results) == len(cases) and not options.retain_evidence:
            shutil.rmtree(temporary_root)
        elif len(results) == len(cases):
            (temporary_root / "integration-report.json").write_text(json.dumps(report, indent=2) + "\n")
            (temporary_root / "integration-report.json").chmod(0o600)
            print("Validated integration evidence retained at " + str(temporary_root), file=sys.stderr)
        else:
            print("Failed integration evidence retained at " + str(temporary_root), file=sys.stderr)


if __name__ == "__main__":
    try:
        main()
    except Exception as failure:
        last_frame = traceback.extract_tb(failure.__traceback__)[-1]
        detail = str(failure) or type(failure).__name__ + " at " + Path(last_frame.filename).name + ":" + str(last_frame.lineno)
        print("FAIL OCI integration: " + detail, file=sys.stderr)
        sys.exit(1)
