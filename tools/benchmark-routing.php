<?php

declare(strict_types=1);

use PHPThis\Http\Request;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\Response;
use PHPThis\Routing\Route;
use PHPThis\Routing\Router;

require dirname(__DIR__) . '/autoload.php';

const LOOKUP_ITERATIONS = 100_000;
const WARMUP_ITERATIONS = 1_000;

$handler = new class implements RequestHandler {
    public function handle(Request $request): Response
    {
        return new Response(204, [], '');
    }
};
$results = [];

foreach ([100, 1_000, 10_000] as $routeCount) {
    $results[] = benchmarkRouteCount($routeCount, $handler);
}

fwrite(
    STDOUT,
    json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n",
);

/**
 * @return array{
 *     routes: int,
 *     construction_microseconds: float,
 *     route_memory_bytes: int,
 *     hit_nanoseconds: float,
 *     miss_nanoseconds: float,
 *     allowed_methods_nanoseconds: float
 * }
 */
function benchmarkRouteCount(int $routeCount, RequestHandler $handler): array
{
    gc_collect_cycles();
    $memoryBefore = memory_get_usage();
    $constructionStarted = clockNanoseconds();
    $routes = [];

    for ($index = 0; $index < $routeCount; $index++) {
        $routes[] = new Route('GET', '/routes/' . $index, $handler);
    }

    $router = new Router($routes);
    unset($routes);
    $constructionNanoseconds = clockNanoseconds() - $constructionStarted;
    $routeMemoryBytes = memory_get_usage() - $memoryBefore;
    $hitRequest = new Request('GET', '/routes/' . ($routeCount - 1));
    $missRequest = new Request('GET', '/routes/missing');

    for ($iteration = 0; $iteration < WARMUP_ITERATIONS; $iteration++) {
        verifyLookups($router, $hitRequest, $missRequest);
    }

    $hitStarted = clockNanoseconds();

    for ($iteration = 0; $iteration < LOOKUP_ITERATIONS; $iteration++) {
        if ($router->match($hitRequest) === null) {
            throw new RuntimeException('Expected the benchmark hit route.');
        }
    }

    $hitNanoseconds = (clockNanoseconds() - $hitStarted) / LOOKUP_ITERATIONS;
    $missStarted = clockNanoseconds();

    for ($iteration = 0; $iteration < LOOKUP_ITERATIONS; $iteration++) {
        if ($router->match($missRequest) !== null) {
            throw new RuntimeException('Expected the benchmark route to be absent.');
        }
    }

    $missNanoseconds = (clockNanoseconds() - $missStarted) / LOOKUP_ITERATIONS;
    $allowedMethodsStarted = clockNanoseconds();

    for ($iteration = 0; $iteration < LOOKUP_ITERATIONS; $iteration++) {
        if ($router->allowedMethodsForPath($hitRequest->path) !== ['GET']) {
            throw new RuntimeException('Expected GET to be allowed for the benchmark route.');
        }
    }

    $allowedMethodsNanoseconds = (
        clockNanoseconds() - $allowedMethodsStarted
    ) / LOOKUP_ITERATIONS;

    return [
        'routes' => $routeCount,
        'construction_microseconds' => $constructionNanoseconds / 1_000,
        'route_memory_bytes' => $routeMemoryBytes,
        'hit_nanoseconds' => $hitNanoseconds,
        'miss_nanoseconds' => $missNanoseconds,
        'allowed_methods_nanoseconds' => $allowedMethodsNanoseconds,
    ];
}

function clockNanoseconds(): float
{
    return (float) hrtime(true);
}

function verifyLookups(Router $router, Request $hitRequest, Request $missRequest): void
{
    if (
        $router->match($hitRequest) === null
        || $router->match($missRequest) !== null
        || $router->allowedMethodsForPath($hitRequest->path) !== ['GET']
    ) {
        throw new RuntimeException('Routing benchmark lookup verification failed.');
    }
}
