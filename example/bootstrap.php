<?php

declare(strict_types=1);

use Example\Routes;
use PHPThis\Application;
use PHPThis\Routing\Router;

require dirname(__DIR__) . '/autoload.php';

return new Application(new Router(Routes::create()));
