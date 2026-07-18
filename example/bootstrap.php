<?php

declare(strict_types=1);

use Example\Routes;
use PHPThis\Application;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;
use PHPThis\Routing\Router;

require dirname(__DIR__) . '/autoload.php';

$databasePath = dirname(__DIR__) . '/tmp/example.sqlite';

if (!is_file($databasePath)) {
    throw new RuntimeException('Example database is missing. Run composer example:setup first.');
}

$dsn = 'sqlite:' . $databasePath;
$listUsersConnection = Connection::connect($dsn, new QueryBudget(1), new QueryTrace(1));
$createUserConnection = Connection::connect($dsn, new QueryBudget(2), new QueryTrace(2));

return new Application(new Router(Routes::create($listUsersConnection, $createUserConnection)));
