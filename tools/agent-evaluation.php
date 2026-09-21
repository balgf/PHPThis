<?php

declare(strict_types=1);

require_once __DIR__ . '/agent-evaluation/support.php';
require_once __DIR__ . '/agent-evaluation/tasks.php';
require_once __DIR__ . '/agent-evaluation/run.php';
require_once __DIR__ . '/agent-evaluation/score.php';

/** @param list<string> $arguments */
function agentEvaluationMain(array $arguments): int
{
    $root = dirname(__DIR__);
    $kit = $root . '/tools/agent-evaluation';
    $command = $arguments[1] ?? 'help';

    try {
        if ($command === 'validate') {
            agentEvaluationRequireArgumentCount($arguments, 2, 'validate');
            agentEvaluationValidateKit($kit);
            fwrite(STDOUT, sprintf("PASS agent evaluation kit: %d tasks\n", count(AGENT_EVALUATION_TASK_REVISIONS)));

            return 0;
        }

        if ($command === 'list') {
            agentEvaluationRequireArgumentCount($arguments, 2, 'list');
            $tasks = agentEvaluationValidateKit($kit);
            $summary = [];

            foreach ($tasks as $task) {
                $summary[] = [
                    'id' => $task['id'],
                    'revision' => $task['revision'],
                    'kind' => $task['kind'],
                    'comparative_claims' => $task['comparative_claims'],
                ];
            }

            foreach (agentEvaluationComparisonTasks($kit) as $task) {
                $summary[] = [
                    'schema_version' => $task['schema_version'],
                    'id' => $task['id'],
                    'revision' => $task['revision'],
                    'kind' => $task['kind'],
                    'protocol' => $task['protocol']['id'],
                    'conditions' => array_column($task['conditions'], 'id'),
                ];
            }

            $explanationTask = agentEvaluationExplanationTask($kit);
            $summary[] = [
                'schema_version' => $explanationTask['schema_version'],
                'id' => $explanationTask['id'],
                'revision' => $explanationTask['revision'],
                'kind' => $explanationTask['kind'],
                'comparative_claims' => $explanationTask['comparative_claims'],
            ];

            fwrite(STDOUT, agentEvaluationJson($summary));

            return 0;
        }

        if ($command === 'prompt') {
            agentEvaluationRequireArgumentCount($arguments, 3, 'prompt <task-id>');
            $taskId = $arguments[2] ?? null;

            if (!is_string($taskId) || $taskId === '') {
                throw new RuntimeException('prompt requires one task ID.');
            }

            $pin = AGENT_EVALUATION_TASK_REVISIONS[$taskId] ?? null;
            $task = match (is_array($pin) ? $pin['schema_version'] : null) {
                2 => agentEvaluationComparisonTask($kit, $taskId),
                3 => agentEvaluationExplanationTask($kit),
                default => agentEvaluationTask($kit, $taskId),
            };
            $prompt = file_get_contents($task['directory'] . '/' . $task['prompt']['path']);

            if (!is_string($prompt)) {
                throw new RuntimeException("Unable to read task prompt: {$taskId}.");
            }

            fwrite(STDOUT, $prompt);

            return 0;
        }

        if ($command === 'validate-run') {
            agentEvaluationRequireArgumentCount($arguments, 4, 'validate-run <task-id> <run.json>');
            $taskId = $arguments[2] ?? null;
            $recordPath = $arguments[3] ?? null;

            if (!is_string($taskId) || $taskId === '' || !is_string($recordPath) || $recordPath === '') {
                throw new RuntimeException('validate-run requires one task ID and one run-record path.');
            }

            $pin = AGENT_EVALUATION_TASK_REVISIONS[$taskId] ?? null;
            $explanation = is_array($pin) && $pin['schema_version'] === 3;
            $runRecord = agentEvaluationJsonFile($recordPath);
            if ($explanation) {
                $task = agentEvaluationExplanationTask($kit);
                agentEvaluationValidateExplanationRunRecord($runRecord, $task);
                agentEvaluationValidateExplanationRunArtifacts($runRecord, dirname($recordPath));
            } else {
                $task = agentEvaluationTask($kit, $taskId);
                agentEvaluationValidateRunRecord($runRecord, $task);
                agentEvaluationValidateRunArtifacts($runRecord, dirname($recordPath));
            }
            fwrite(STDOUT, "PASS agent evaluation run record: {$taskId}\n");

            return 0;
        }

        if ($command === 'validate-score') {
            agentEvaluationRequireArgumentCount(
                $arguments,
                5,
                'validate-score <task-id> <run.json> <score.json>',
            );
            $taskId = $arguments[2] ?? null;
            $runPath = $arguments[3] ?? null;
            $recordPath = $arguments[4] ?? null;

            if (
                !is_string($taskId)
                || $taskId === ''
                || !is_string($runPath)
                || $runPath === ''
                || !is_string($recordPath)
                || $recordPath === ''
            ) {
                throw new RuntimeException('validate-score requires one task ID, one run-record path, and one score-record path.');
            }

            $pin = AGENT_EVALUATION_TASK_REVISIONS[$taskId] ?? null;
            $explanation = is_array($pin) && $pin['schema_version'] === 3;
            $runRecord = agentEvaluationJsonFile($runPath);
            if ($explanation) {
                $task = agentEvaluationExplanationTask($kit);
                $runRoot = realpath(dirname($runPath));
                $scoreRoot = realpath(dirname($recordPath));
                if ($runRoot === false || $scoreRoot === false || $runRoot !== $scoreRoot) {
                    throw new RuntimeException(
                        'Explanation run and score records must share one existing evidence directory.',
                    );
                }
                $scoreRecord = agentEvaluationJsonFile($recordPath);
                agentEvaluationValidateExplanationRunRecord($runRecord, $task);
                agentEvaluationValidateExplanationRunArtifacts($runRecord, $runRoot);
                agentEvaluationValidateExplanationScoreRecord(
                    $scoreRecord,
                    $task,
                    $runRecord,
                    agentEvaluationFileHash($runPath, 'run record'),
                );
                agentEvaluationValidateExplanationScoreArtifacts($scoreRecord, $runRecord, $runRoot);
                agentEvaluationValidateExplanationOuterEvidence($scoreRecord, $runRecord, $runRoot);
            } else {
                $task = agentEvaluationTask($kit, $taskId);
                agentEvaluationValidateRunRecord($runRecord, $task);
                agentEvaluationValidateRunArtifacts($runRecord, dirname($runPath));
                agentEvaluationValidateScoreRecord(
                    agentEvaluationJsonFile($recordPath),
                    $task,
                    $runRecord,
                    agentEvaluationFileHash($runPath, 'run record'),
                );
            }
            fwrite(STDOUT, "PASS agent evaluation score record: {$taskId}\n");

            return 0;
        }

        if ($command === 'help') {
            if (count($arguments) !== 1 && count($arguments) !== 2) {
                throw new RuntimeException('help received an unexpected number of arguments.');
            }
            fwrite(
                STDOUT,
                "Usage:\n"
                . "  php tools/agent-evaluation.php validate\n"
                . "  php tools/agent-evaluation.php list\n"
                . "  php tools/agent-evaluation.php prompt <task-id>\n"
                . "  php tools/agent-evaluation.php validate-run <task-id> <run.json>\n"
                . "  php tools/agent-evaluation.php validate-score <task-id> <run.json> <score.json>\n",
            );

            return 0;
        }

        throw new RuntimeException("Unknown agent-evaluation command: {$command}.");
    } catch (Throwable $throwable) {
        fwrite(STDERR, "FAIL agent evaluation: {$throwable->getMessage()}\n");

        return 1;
    }
}

/** @param list<string> $arguments */
function agentEvaluationRequireArgumentCount(array $arguments, int $expected, string $usage): void
{
    if (count($arguments) !== $expected) {
        throw new RuntimeException("{$usage} received an unexpected number of arguments.");
    }
}

if (!defined('PHPTHIS_AGENT_EVALUATION_LIBRARY_ONLY')) {
    /** @var list<string> $argv */
    exit(agentEvaluationMain($argv));
}
