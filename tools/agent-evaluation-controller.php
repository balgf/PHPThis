<?php

declare(strict_types=1);

define('PHPTHIS_AGENT_EVALUATION_LIBRARY_ONLY', true);

require_once __DIR__ . '/agent-evaluation.php';
require_once __DIR__ . '/agent-evaluation-controller/contract.php';
require_once __DIR__ . '/agent-evaluation-controller/workspace.php';
require_once __DIR__ . '/agent-evaluation-controller/process.php';
require_once __DIR__ . '/agent-evaluation-controller/codex.php';
require_once __DIR__ . '/agent-evaluation-controller/scoring.php';
require_once __DIR__ . '/agent-evaluation-controller/controller.php';

if (!defined('PHPTHIS_AGENT_EVALUATION_CONTROLLER_LIBRARY_ONLY')) {
    /** @var list<string> $argv */
    exit(agentEvaluationControllerMain($argv));
}
