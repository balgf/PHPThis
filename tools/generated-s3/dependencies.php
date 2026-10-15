<?php

declare(strict_types=1);

$sdkAutoload = dirname(__DIR__, 2) . '/tmp/generated-s3-vendor/autoload.php';
if (!is_file($sdkAutoload)) {
    throw new RuntimeException('Install the locked reference dependencies: composer install --working-dir=tools/generated-s3');
}
require_once $sdkAutoload;
