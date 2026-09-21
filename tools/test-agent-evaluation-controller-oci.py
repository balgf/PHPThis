#!/usr/bin/env python3
"""Opt-in real OCI/Codex integration against a fixed local no-paid API fixture.

Usage: python3 tools/test-agent-evaluation-controller-oci.py /absolute/config.json
The configuration must reference already built digest-pinned images and already
reviewed locked dependencies. The explanation case uses its separate fixed
zero-spend control configuration and must be selected explicitly with
``--case explanation``. This test never builds, downloads, or uses a key.
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
UPSTREAM_CASES = {
    "input-http-failure": {"operation": "input_tokens", "category": "http_status", "http_status": 429,
                           "curl_code": 0, "response_limit_exceeded": False},
    "responses-http-failure": {"operation": "responses", "category": "http_status", "http_status": 503,
                               "curl_code": 0, "response_limit_exceeded": False},
    "input-transport-failure": {"operation": "input_tokens", "category": "curl", "http_status": None,
                                "curl_code": 52, "response_limit_exceeded": False},
    "responses-transport-failure": {"operation": "responses", "category": "curl", "http_status": None,
                                    "curl_code": 52, "response_limit_exceeded": False},
    "input-response-limit": {"operation": "input_tokens", "category": "response_limit", "http_status": 200,
                             "curl_code": 23, "response_limit_exceeded": True},
    "responses-response-limit": {"operation": "responses", "category": "response_limit", "http_status": 200,
                                 "curl_code": 23, "response_limit_exceeded": True},
}
EXPLANATION_RUN_ID = "00000000000000000000000000007099"
PHP_WORKER = r"""
// Host export must preserve candidate modes even with a private runner umask.
umask(0077);
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
    $result = ['status' => 'failed', 'class' => $failure::class, 'message' => $failure->getMessage(),
        'source' => basename($failure->getFile()) . ':' . $failure->getLine()];
    if (in_array($argv[5], ['input-http-failure', 'responses-http-failure', 'input-transport-failure',
        'responses-transport-failure', 'input-response-limit', 'responses-response-limit'], true)) {
        $proxy = agentEvaluationJsonFile($argv[3] . '/evidence/proxy.json');
        $ledger = agentEvaluationRequireObject($proxy, 'ledger', 'upstream integration ledger');
        $result['proxy_aggregate_usage'] = agentEvaluationControllerProxyAggregateUsage($ledger);
    }
    fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR) . "\n");
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
PHP_EXPLANATION_WORKER = r"""
umask(0077);
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
    $task = agentEvaluationExplanationTask($argv[1] . '/tools/agent-evaluation');
    $configuration = agentEvaluationControllerReadExplanationOciControlConfiguration($argv[2], $task, '');
    agentEvaluationControllerValidateExplanationPreflightInputs($argv[1], $configuration, $task);
    $approval = agentEvaluationRequireObject($configuration, 'approval', 'explanation OCI control');
    if (!hash_equals(
        agentEvaluationRequireString($approval, 'run_id', 'explanation OCI control approval'),
        $argv[4],
    )) {
        throw new RuntimeException('Explanation OCI control is bound to a different run ID.');
    }
    $workspace = agentEvaluationControllerPrepareWorkspace(
        $argv[1],
        agentEvaluationRequireString($configuration, 'prepared_dependencies', 'explanation OCI control'),
        $argv[3],
        $task,
    );
    $control = agentEvaluationControllerCreatePreflightRoot();
    $engine = agentEvaluationControllerOciPreflight(
        agentEvaluationRequireObject($configuration, 'engine', 'explanation OCI control'),
        $control,
    );
    $resources = agentEvaluationControllerOciPrepare(
        $engine,
        $argv[4],
        $workspace['candidate_root'],
        $workspace['dependencies_root'],
        true,
    );
    $sourcePrompt = file_get_contents(
        agentEvaluationRequireString($task, 'directory', 'explanation OCI task') . '/'
        . agentEvaluationRequireString(
            agentEvaluationRequireObject($task, 'prompt', 'explanation OCI task'),
            'path',
            'explanation OCI task prompt',
        ),
    );
    if (!is_string($sourcePrompt)) {
        throw new RuntimeException('Unable to read the explanation OCI source prompt.');
    }
    $profile = agentEvaluationRequireObject($configuration, 'profile', 'explanation OCI control');
    $generation = agentEvaluationControllerRunLiveCodex(
        $resources,
        agentEvaluationExplanationEffectivePrompt($sourcePrompt),
        $profile,
        '',
    );
    if ($generation['termination_reason'] !== 'completed'
        || $generation['external_actions_approved'] !== true
        || !agentEvaluationControllerExplanationActionsApproved($generation['events'])
    ) {
        throw new RuntimeException('Explanation OCI control did not complete its read-only generation boundary.');
    }
    $ledger = agentEvaluationRequireObject(
        agentEvaluationRequireObject($generation, 'proxy_evidence', 'explanation OCI generation'),
        'ledger',
        'explanation OCI proxy evidence',
    );
    $observedTransport = agentEvaluationNormalizeExplanationTransportTools(
        $ledger['transport_tools'] ?? null,
        'explanation OCI observed transport tools',
    );
    $configuredTransport = agentEvaluationNormalizeExplanationTransportTools(
        $profile['transport_tools'] ?? null,
        'explanation OCI configured transport tools',
    );
    if ($observedTransport === null || $observedTransport !== $configuredTransport) {
        throw new RuntimeException('Explanation OCI control transport identity drifted.');
    }
    agentEvaluationControllerOciStopGeneration($resources);
    $export = agentEvaluationControllerOciExportCandidate($resources, $workspace['candidate_root']);
    $freeze = agentEvaluationControllerFreezeWorkspace($workspace, $task);
    if ($freeze['changed_files'] !== [] || $freeze['added_lines'] !== 0 || $freeze['deleted_lines'] !== 0
        || $freeze['patch'] !== ''
    ) {
        throw new RuntimeException('Explanation OCI control changed the pinned workspace.');
    }
    $generationCleanup = agentEvaluationControllerOciDestroyGeneration($resources);
    $result = [
        'status' => 'completed',
        'termination_reason' => $generation['termination_reason'],
        'response' => $generation['response'],
        'usage' => $generation['usage'],
        'transport_tools' => $observedTransport,
        'external_actions' => $generation['external_actions'],
        'process' => $generation['process'],
        'freeze' => [
            'candidate_sha256' => $freeze['candidate_sha256'],
            'patch_sha256' => $freeze['patch_sha256'],
            'changed_files' => $freeze['changed_files'],
            'added_lines' => $freeze['added_lines'],
            'deleted_lines' => $freeze['deleted_lines'],
        ],
        'export' => $export,
        'generation_cleanup' => $generationCleanup,
        'resource_identity' => ['owner' => $resources['owner'], 'run_id' => $resources['run_id']],
    ];
} catch (Throwable $failure) {
    $result = [
        'status' => 'failed',
        'class' => $failure::class,
        'message' => $failure->getMessage(),
        'source' => basename($failure->getFile()) . ':' . $failure->getLine(),
    ];
} finally {
    try {
        $cleanupVerified = $resources === null && $control === null;
        if ($resources !== null) {
            $result['oci_cleanup'] = agentEvaluationControllerOciCleanup($resources);
            $cleanupVerified = $result['oci_cleanup']['verified'] === true
                && $result['oci_cleanup']['status'] === 'pass';
        } elseif ($control !== null) {
            $ledger = agentEvaluationControllerReadOciRecoveryLedger($control);
            $cleanupVerified = $ledger === null
                || ($ledger['containers'] === [] && $ledger['volumes'] === []);
        }
        if ($cleanupVerified) {
            if ($workspace !== null) {
                $result['workspace_cleanup'] = [
                    'status' => 'pass',
                    'removed' => agentEvaluationControllerCleanupWorkspace($workspace),
                ];
            }
            if ($control !== null) {
                agentEvaluationControllerRemoveTree($control);
            }
        } else {
            $result['status'] = 'failed';
            $result['cleanup_failure'] = 'Explanation OCI resource cleanup requires review.';
            $result['recovery_control_root'] = $control;
        }
    } catch (Throwable $failure) {
        $result['status'] = 'failed';
        $result['cleanup_failure'] = 'Explanation OCI cleanup failed.';
        $result['recovery_control_root'] = $control;
        $result['source'] = basename($failure->getFile()) . ':' . $failure->getLine();
    } finally {
        if ($interruptHandlers !== null) {
            agentEvaluationControllerRestoreInterruptHandlers($interruptHandlers);
        }
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


def verify_evidence(run_root, should_pass, expected_failure_phase=None, forbidden_markers=()):
    evidence = run_root / "evidence"
    manifest_bytes = (evidence / "evidence-manifest.json").read_bytes()
    manifest = json.loads(manifest_bytes)
    assert manifest["synthetic"] is False
    assert manifest["comparative_claims"] is False
    if forbidden_markers:
        retained = list(evidence.iterdir())
        assert all(path.is_file() and not path.is_symlink() for path in retained), "Upstream evidence must remain a flat regular-file inventory"
        assert {path.name for path in retained} == set(manifest["artifacts"]) | {"evidence-manifest.json"}, "Every retained upstream artifact must be hash-bound; unlisted files are forbidden"
        for marker in (CREDENTIAL_SENTINEL, *forbidden_markers):
            assert marker.encode() not in manifest_bytes, "Raw synthetic content entered the retained manifest"
    for artifact, descriptor in manifest["artifacts"].items():
        path = evidence / artifact
        assert path.is_file() and not path.is_symlink()
        data = path.read_bytes()
        assert CREDENTIAL_SENTINEL.encode() not in data, "Ambient host sentinel entered retained evidence"
        for marker in forbidden_markers:
            assert marker.encode() not in data, "Raw synthetic upstream content entered retained evidence"
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


def verify_upstream_failure(run_root, case, requests, response_count, worker_result, input_tokens):
    evidence = run_root / "evidence"
    generation = json.loads((evidence / "generation-process.json").read_text())
    expected = {"schema_version": 1, **UPSTREAM_CASES[case]}
    diagnostic = generation["upstream_failure"]
    assert type(diagnostic) is dict and diagnostic == expected, "The intended upstream operation and failure category were not retained exactly"
    assert type(diagnostic["schema_version"]) is int and type(diagnostic["curl_code"]) is int
    assert diagnostic["http_status"] is None or type(diagnostic["http_status"]) is int
    assert diagnostic["response_limit_exceeded"] is expected["response_limit_exceeded"], "Diagnostic booleans must not be coerced from integers"
    assert generation["failure_code"] == "AGENT_EVALUATION_CONTROLLER_PROXY_UPSTREAM_FAILED"
    assert generation["termination_reason"] == "process_failed" and generation["exit_code"] == -1
    assert generation["timed_out"] is False and generation["output_limit_exceeded"] is False
    assert len(json.dumps(generation["upstream_failure"]).encode()) < 256, "Upstream diagnostics exceeded their fixed small shape"
    for name in ["freeze.json", "candidate.patch", "candidate.manifest", "application-check.json", "score.json"]:
        assert not (evidence / name).exists(), "Upstream rejection must not advance to candidate freeze or scoring"
    events = [json.loads(line) for line in (evidence / "events.jsonl").read_text().splitlines()]
    assert not any(event.get("item", {}).get("type") in ["command_execution", "file_change"] for event in events), "A failure before the first response must not execute a candidate command"
    proxy = json.loads((evidence / "proxy.json").read_text())
    assert proxy["synthetic_upstream"] is True and proxy["upstream_origin"] == "http://127.0.0.1:18765"
    ledger = proxy["ledger"]
    assert ledger["blocked"] is True and ledger["failure_reason"] is None
    assert ledger["observed_request_count"] == 1, "The rejected request must not be retried"
    assert isinstance(ledger["request_sha256"], str) and re.fullmatch(r"[a-f0-9]{64}", ledger["request_sha256"]), "Pending request identity was discarded"
    assert {name: ledger[name] for name in ["input_tokens", "output_tokens", "cached_tokens", "reasoning_tokens"]} == {
        "input_tokens": 0, "output_tokens": 0, "cached_tokens": 0, "reasoning_tokens": 0}, "Unvalidated response bytes became settled usage"
    assert ledger["response_bytes"] == 0 and ledger["last_response_bytes"] is None and ledger["last_response_sha256"] is None
    assert ledger["response_rejection_stage"] is None and ledger["response_observation"] is None
    assert ledger["provider_error_event_seen"] is False and ledger["provider_error_observation"] is None
    assert "spending" not in ledger, "The fixed zero-spend smoke fixture must not introduce a calibration money policy"
    if expected["operation"] == "input_tokens":
        assert [request["path"] for request in requests] == ["/v1/responses/input_tokens"] and response_count == 0
        assert ledger["request_count"] == 0 and ledger["reserved_input"] == 0 and ledger["reserved_output"] == 0
        assert worker_result["proxy_aggregate_usage"] == {"input_tokens": 0, "output_tokens": 0, "cached_tokens": 0, "reasoning_tokens": 0}
    else:
        assert [request["path"] for request in requests] == ["/v1/responses/input_tokens", "/v1/responses"] and response_count == 1
        assert ledger["request_count"] == 1 and ledger["reserved_input"] == input_tokens
        assert ledger["reserved_output"] == requests[1]["approved_max_output_tokens"] > 0, "The full forwarded response allowance must remain reserved"
        assert worker_result["proxy_aggregate_usage"] == {"input_tokens": None, "output_tokens": None, "cached_tokens": None, "reasoning_tokens": None}
    return {"diagnostic": expected, "request_count": ledger["request_count"],
            "observed_request_count": ledger["observed_request_count"],
            "reserved_input": ledger["reserved_input"], "reserved_output": ledger["reserved_output"],
            "aggregate_usage": worker_result["proxy_aggregate_usage"], "retry_observed": False,
            "raw_upstream_content_retained": False,
            "accounting_scope": "Real token reservation boundary in the zero-spend smoke fixture; monetary reservation controls belong to the offline PHP tests."}


def verify_prompt_delivery(run_root, requests):
    evidence = run_root / "evidence"
    prompt = (evidence / "prompt.md").read_bytes()
    source_prompt = (evidence / "source-prompt.md").read_bytes()
    task = json.loads((evidence / "task.json").read_text())
    policy = json.loads((evidence / "workspace-policy.json").read_text())
    assert policy == {"schema_version": 1, "kind": "generation-workspace-policy-v1",
                      "policy": task["workspace_policy"]}, "Retained policy differs from the admitted task"
    marker = b"## Enforced evaluation workspace policy (v1)\n"
    assert prompt.startswith(source_prompt + b"\n\n" + marker)
    assert prompt.count(marker) == 1, "The exact enforced policy must appear once"
    policy_section = prompt.split(marker, 1)[1].decode("utf-8")
    policy_blocks = re.findall(r"\n```json\n(.*?)```\n", policy_section, re.DOTALL)
    assert len(policy_blocks) == 1 and json.loads(policy_blocks[0]) == policy["policy"], "Delivered prompt must state the exact admitted policy"
    creates = [request for request in requests if request["path"] == "/v1/responses"]
    assert creates, "Prompt delivery requires the actual pinned Codex Responses request"
    descriptor = {"bytes": len(prompt), "sha256": hashlib.sha256(prompt).hexdigest()}
    assert creates[0]["user_texts"].count(descriptor) == 1, "The first generation request must contain the exact retained prompt once as user input_text"
    return {"retained_prompt": descriptor, "received_user_text_matches": 1, "admitted_workspace_policy": "exact"}


def verify_explanation_control(run_root, requests, response_count, result, dependencies_sha256):
    assert result["status"] == "completed" and result["termination_reason"] == "completed"
    assert result["response"] == "Deterministic read-only explanation fixture completed."
    assert result["usage"] == {"input_tokens": 100, "output_tokens": 100,
                               "cached_tokens": 0, "reasoning_tokens": 0}
    assert result["transport_tools"] == {
        "kind": "codex-responses-local-tools-v1",
        "count": 4,
        "sha256": "3392681cd5b82960557ffe2ce5b0ba1e223cc0f97a226432a7a43353475d6aed",
    }
    assert result["external_actions"] == {
        "approved": True,
        "network": "none",
        "socket_attempt_telemetry": None,
        "host_proxy_requests": 1,
        "proxy_blocked": False,
        "observed_commands": [],
    }, "Explanation infrastructure control must complete without a command or file action"
    process = result["process"]
    assert process["exit_code"] == 0 and process["termination_reason"] == "completed"
    assert process["timed_out"] is False and process["output_limit_exceeded"] is False
    assert process["synthetic_upstream"] is True and process["failure_code"] is None
    assert process["upstream_failure"] is None
    assert process["cleanup"]["container_stopped"] is True and process["cleanup"]["oom_killed"] is False
    freeze = result["freeze"]
    assert freeze["changed_files"] == [] and freeze["added_lines"] == 0 and freeze["deleted_lines"] == 0
    assert freeze["candidate_sha256"] == "da90475f0d494a125dfdff21f85408b97164ec276961c1d7db522a63bbb5be6d"
    assert freeze["patch_sha256"] == hashlib.sha256(b"").hexdigest()
    assert result["export"]["generation_stopped"] is True
    assert result["generation_cleanup"] == {"status": "pass", "generation_destroyed": True}
    assert result["oci_cleanup"]["status"] == "pass" and result["oci_cleanup"]["verified"] is True
    assert result["workspace_cleanup"]["status"] == "pass"
    assert [path.name for path in run_root.iterdir()] == ["evidence"]
    assert [path.name for path in (run_root / "evidence").iterdir()] == ["prepared-dependencies.manifest"], \
        "Infrastructure control must retain only preparation metadata, without schema-v3 model evidence"
    assert hashlib.sha256((run_root / "evidence/prepared-dependencies.manifest").read_bytes()).hexdigest() \
        == dependencies_sha256
    assert [request["path"] for request in requests] == ["/v1/responses/input_tokens", "/v1/responses"]
    assert response_count == 1
    assert requests[1]["model"] == "gpt-5.4-2026-03-05"
    expected_prompt = {"bytes": 1007,
                       "sha256": "33038bfb324e53b2ab0704284dc78087ff85f948a2170e19b1f10925ecff79f6"}
    for request in requests:
        assert request["user_texts"].count(expected_prompt) == 1, \
            "Both token counting and generation must receive the exact revision-2 explanation prompt once"
    return {
        "boundary": "infrastructure-only",
        "effective_prompt": expected_prompt,
        "effective_prompt_verified": True,
        "schema_v3_model_evidence": False,
        "read_only_candidate": True,
        "candidate_relative_writable_mounts": False,
        "response": result["response"],
        "usage": result["usage"],
        "transport_tools": result["transport_tools"],
        "changed_files": [],
        "cleanup": "verified",
        "fixture_requests": requests,
        "resource_identity": result["resource_identity"],
        "control_status": "pass",
    }


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
    parser.add_argument("--case", choices=["all", "complete", "explanation", "scoring-boundary", "workspace-escape", "symlink", "file-mode", "relay-signal", "pids-limit", "memory-limit", "disk-limit", "token-limit", "interrupt", "wall-bound", "output-bound", *UPSTREAM_CASES], default="all")
    options = parser.parse_args()
    configuration = options.configuration.resolve(strict=True)
    with configuration.open("rb") as source:
        configuration_bytes = source.read(65_537)
    if not 1 <= len(configuration_bytes) <= 65_536:
        raise RuntimeError("Integration configuration must fit the controller's 64 KiB limit")
    reviewed = json.loads(configuration_bytes)
    approval = reviewed.get("approval")
    explanation_control = isinstance(approval, dict) and approval == {
        "reference": "synthetic-oci-explanation-control",
        "model": "gpt-5.4-2026-03-05",
        "runs": 1,
        "spending_ceiling_usd": "0.00",
        "run_id": EXPLANATION_RUN_ID,
    }
    if (options.case == "explanation") != explanation_control:
        raise RuntimeError("The explanation case requires its separate exact zero-spend control configuration")
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
    cases = ["complete", "scoring-boundary", "workspace-escape", "symlink", "file-mode", "relay-signal", "pids-limit", "memory-limit", "disk-limit", "token-limit", "interrupt", "wall-bound", "output-bound", *UPSTREAM_CASES] if options.case == "all" else [options.case]
    try:
        for index, case in enumerate(cases, 1):
            server.reset("wall-limit" if case in ["interrupt", "wall-bound"] else case)
            run_root = temporary_root / case
            run_id = EXPLANATION_RUN_ID if case == "explanation" else hashlib.sha256((str(temporary_root) + case).encode()).hexdigest()[:32]
            primitive = case in ["wall-bound", "output-bound"]
            worker = PHP_EXPLANATION_WORKER if case == "explanation" else (PHP_BOUND_WORKER if primitive else PHP_WORKER)
            code, stdout, stderr = bounded_worker([php, "-r", worker, str(ROOT), str(configuration), str(run_root), run_id, case], server, case == "interrupt")
            if server.failure is not None:
                raise RuntimeError("Deterministic fixture failed: " + server.failure)
            upstream_markers = (fixture.UPSTREAM_BODY_SENTINEL, fixture.UPSTREAM_HEADER_SENTINEL) if case in UPSTREAM_CASES else ()
            for marker in upstream_markers:
                assert marker.encode() not in stdout and marker.encode() not in stderr, "Raw synthetic upstream content entered worker output"
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
            if case == "explanation":
                if code != 0:
                    raise RuntimeError("Unexpected explanation infrastructure result: " + json.dumps(result)
                                       + " " + stderr.decode(errors="replace"))
                evidence_result = verify_explanation_control(
                    run_root, server.requests, server.responses, result,
                    reviewed["prepared_dependencies_sha256"])
                evidence_result["independent_engine_cleanup"] = verify_engine_absence(
                    reviewed, result["resource_identity"], temporary_root)
                results[case] = evidence_result
                print("PASS OCI integration " + case, flush=True)
                continue
            expected_pass = case in ["complete", "scoring-boundary"]
            if (code == 0) != expected_pass:
                raise RuntimeError("Unexpected " + case + " result: " + json.dumps(result) + " " + stderr.decode(errors="replace"))
            expected_phase = "freeze" if case in ["workspace-escape", "symlink", "file-mode"] else "generate"
            evidence_result = verify_evidence(run_root, expected_pass, expected_phase, upstream_markers)
            if case == "complete":
                evidence_result["prompt_delivery"] = verify_prompt_delivery(run_root, server.requests)
                generation = json.loads((run_root / "evidence/generation-process.json").read_text())
                assert generation["upstream_failure"] is None, "Successful upstream calls must not leave a failure observation"
            if case in UPSTREAM_CASES:
                evidence_result["upstream_failure_control"] = verify_upstream_failure(
                    run_root, case, server.requests, server.responses, result, fixture.INPUT_TOKENS)
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
                if case == "workspace-escape":
                    assert manifest["primary_failure"]["reason_code"] == "candidate_new_path_unapproved", "The actual unapproved-file freeze rejection must retain its fixed safe reason"
                    evidence_result["reason_code"] = manifest["primary_failure"]["reason_code"]
                evidence_result["mutation_verified"] = True
                evidence_result["failure_code"] = manifest["primary_failure"].get("code")
            if case in UPSTREAM_CASES:
                pass  # Exact operation counts, including absence of retries, were checked above.
            elif case == "token-limit":
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
