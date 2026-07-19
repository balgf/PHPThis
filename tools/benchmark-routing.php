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
 *     literal_routes: int,
 *     typed_routes: int,
 *     total_routes: int,
 *     construction_microseconds: float,
 *     route_memory_bytes: int,
 *     hit_nanoseconds: float,
 *     dynamic_hit_nanoseconds: float,
 *     miss_nanoseconds: float,
 *     allowed_methods_nanoseconds: float,
 *     dynamic_allowed_methods_nanoseconds: float
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
        $routes[] = new Route(
            'GET',
            '/item-groups/' . $index . '/{item_id:positive-int}',
            $handler,
        );
    }

    $router = new Router($routes);
    unset($routes);
    $constructionNanoseconds = clockNanoseconds() - $constructionStarted;
    $routeMemoryBytes = memory_get_usage() - $memoryBefore;
    $hitRequest = new Request('GET', '/routes/' . ($routeCount - 1));
    $dynamicHitRequest = new Request(
        'GET',
        '/item-groups/' . ($routeCount - 1) . '/999',
    );
    $missRequest = new Request('GET', '/routes/missing');

    for ($iteration = 0; $iteration < WARMUP_ITERATIONS; $iteration++) {
        verifyLookups($router, $hitRequest, $dynamicHitRequest, $missRequest);
    }

    $hitStarted = clockNanoseconds();

    for ($iteration = 0; $iteration < LOOKUP_ITERATIONS; $iteration++) {
        if ($router->match($hitRequest) === null) {
            throw new RuntimeException('Expected the benchmark hit route.');
        }
    }

    $hitNanoseconds = (clockNanoseconds() - $hitStarted) / LOOKUP_ITERATIONS;
    $dynamicHitStarted = clockNanoseconds();

    for ($iteration = 0; $iteration < LOOKUP_ITERATIONS; $iteration++) {
        if ($router->match($dynamicHitRequest) === null) {
            throw new RuntimeException('Expected the benchmark dynamic hit route.');
        }
    }

    $dynamicHitNanoseconds = (
        clockNanoseconds() - $dynamicHitStarted
    ) / LOOKUP_ITERATIONS;
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
    $dynamicAllowedMethodsStarted = clockNanoseconds();

    for ($iteration = 0; $iteration < LOOKUP_ITERATIONS; $iteration++) {
        if ($router->allowedMethodsForPath($dynamicHitRequest->path) !== ['GET']) {
            throw new RuntimeException('Expected GET to be allowed for the benchmark dynamic route.');
        }
    }

    $dynamicAllowedMethodsNanoseconds = (
        clockNanoseconds() - $dynamicAllowedMethodsStarted
    ) / LOOKUP_ITERATIONS;

    return [
        'literal_routes' => $routeCount,
        'typed_routes' => $routeCount,
        'total_routes' => $routeCount * 2,
        'construction_microseconds' => $constructionNanoseconds / 1_000,
        'route_memory_bytes' => $routeMemoryBytes,
        'hit_nanoseconds' => $hitNanoseconds,
        'dynamic_hit_nanoseconds' => $dynamicHitNanoseconds,
        'miss_nanoseconds' => $missNanoseconds,
        'allowed_methods_nanoseconds' => $allowedMethodsNanoseconds,
        'dynamic_allowed_methods_nanoseconds' => $dynamicAllowedMethodsNanoseconds,
    ];
}

function clockNanoseconds(): float
{
    return (float) hrtime(true);
}

function verifyLookups(
    Router $router,
    Request $hitRequest,
    Request $dynamicHitRequest,
    Request $missRequest,
): void
{
    $dynamicMatch = $router->match($dynamicHitRequest);

    if (
        $router->match($hitRequest) === null
        || $dynamicMatch === null
        || $dynamicMatch->pathParameters->positiveInteger('item_id') !== 999
        || $router->match($missRequest) !== null
        || $router->allowedMethodsForPath($hitRequest->path) !== ['GET']
        || $router->allowedMethodsForPath($dynamicHitRequest->path) !== ['GET']
    ) {
        throw new RuntimeException('Routing benchmark lookup verification failed.');
    }
}
