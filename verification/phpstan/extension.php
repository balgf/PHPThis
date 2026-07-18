<?php

declare(strict_types=1);

require_once __DIR__ . '/MixedScalarCoercionRule.php';
require_once __DIR__ . '/DirectPdoConstructionRule.php';

return [
    'rules' => [
        PHPThis\Verification\PHPStan\DirectPdoConstructionRule::class,
        PHPThis\Verification\PHPStan\MixedScalarCoercionRule::class,
    ],
];
