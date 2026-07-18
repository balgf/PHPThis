<?php

declare(strict_types=1);

require_once __DIR__ . '/ConnectionSqlRuleSupport.php';
require_once __DIR__ . '/ConstantSqlStringRule.php';
require_once __DIR__ . '/ConnectionCallableArrayRule.php';
require_once __DIR__ . '/ConnectionMethodCallableRule.php';
require_once __DIR__ . '/DirectPdoConstructionRule.php';
require_once __DIR__ . '/MixedScalarCoercionRule.php';

return [
    'rules' => [
        PHPThis\Verification\PHPStan\ConstantSqlStringRule::class,
        PHPThis\Verification\PHPStan\ConnectionCallableArrayRule::class,
        PHPThis\Verification\PHPStan\ConnectionMethodCallableRule::class,
        PHPThis\Verification\PHPStan\DirectPdoConstructionRule::class,
        PHPThis\Verification\PHPStan\MixedScalarCoercionRule::class,
    ],
];
