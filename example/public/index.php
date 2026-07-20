<?php

declare(strict_types=1);

use Example\Observability\TerminalRequestCoordinator;
use PHPThis\Http\ResponseEmitter;

/** @var TerminalRequestCoordinator $coordinator */
$coordinator = require dirname(__DIR__) . '/bootstrap.php';
$response = $coordinator->handle($_SERVER, $_GET);

(new ResponseEmitter())->emit($response);
