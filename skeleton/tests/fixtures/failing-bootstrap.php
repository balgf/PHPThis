<?php

declare(strict_types=1);

final class StarterSafeSapiOuterFailure extends RuntimeException
{
}

if (
    (error_reporting() & E_ALL) !== E_ALL
    || ini_get('display_errors') !== '0'
    || ini_get('display_startup_errors') !== '0'
    || ini_get('log_errors') !== '1'
    || ini_get('zend.exception_ignore_args') !== '1'
) {
    throw new RuntimeException('The starter web SAPI settings were not effective.');
}

throw new StarterSafeSapiOuterFailure(
    'starter-bootstrap-private-sentinel SQLSTATE[HY000] /private/starter/bootstrap.php',
);
