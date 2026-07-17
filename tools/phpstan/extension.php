<?php

declare(strict_types=1);

require_once __DIR__ . '/MixedScalarCoercionRule.php';

return [
    'rules' => [
        PHPThis\StaticAnalysis\MixedScalarCoercionRule::class,
    ],
];
