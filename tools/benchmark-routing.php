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
const TOKEN_BYTES = 64;
const UUID_VALUE = '01890f5a-4c96-7a2b-8c3d-123456789abc';
const ULID_VALUE = '01arz3ndektsv4rrffq69g5fav';

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
 *     timed_typed_parameters: int,
 *     fixed_parameter_types: list<string>,
 *     timed_dynamic_parameter_type: string,
 *     timed_uuid_parameter_type: string,
 *     token_bytes: int,
 *     total_routes: int,
 *     construction_microseconds: float,
 *     route_memory_bytes: int,
 *     hit_nanoseconds: float,
 *     dynamic_hit_nanoseconds: float,
 *     uuid_hit_nanoseconds: float,
 *     miss_nanoseconds: float,
 *     oversized_token_miss_nanoseconds: float,
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
    $uuidStart = intdiv($routeCount, 3);
    $ulidStart = intdiv($routeCount * 2, 3);

    for ($index = 0; $index < $routeCount; $index++) {
        $parameter = match (true) {
            $index < $uuidStart => '{document_key:token}',
            $index < $ulidStart => '{document_id:uuid}',
            default => '{document_id:ulid}',
        };
        $routes[] = new Route('GET', '/routes/' . $index, $handler);
        $routes[] = new Route(
            'GET',
            '/accounts/account-'
                . $index
                . '/documents/'
                . $parameter,
            $handler,
        );
        $routes[] = new Route(
            'GET',
            '/accounts/{account_id:positive-int}/document-groups/'
                . $index
                . '/documents/'
                . $parameter,
            $handler,
        );
    }

    $router = new Router($routes);
    unset($routes);
    $constructionNanoseconds = clockNanoseconds() - $constructionStarted;
    $routeMemoryBytes = memory_get_usage() - $memoryBefore;
    $hitRequest = new Request('GET', '/routes/' . ($routeCount - 1));
    $token = 'D' . str_repeat('9', TOKEN_BYTES - 1);
    $dynamicHitRequest = new Request(
        'GET',
        '/accounts/999/document-groups/'
            . ($routeCount - 1)
            . '/documents/'
            . ULID_VALUE,
    );
    $uuidHitRequest = new Request(
        'GET',
        '/accounts/999/document-groups/'
            . intdiv($routeCount, 2)
            . '/documents/'
            . UUID_VALUE,
    );
    $missRequest = new Request('GET', '/routes/missing');
    $oversizedTokenRequest = new Request(
        'GET',
        '/accounts/999/document-groups/0/documents/'
            . $token
            . '0',
    );

    for ($iteration = 0; $iteration < WARMUP_ITERATIONS; $iteration++) {
        verifyLookups(
            $router,
            $hitRequest,
            $dynamicHitRequest,
            $uuidHitRequest,
            $missRequest,
            $oversizedTokenRequest,
        );
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
    $uuidHitStarted = clockNanoseconds();

    for ($iteration = 0; $iteration < LOOKUP_ITERATIONS; $iteration++) {
        if ($router->match($uuidHitRequest) === null) {
            throw new RuntimeException('Expected the benchmark UUID hit route.');
        }
    }

    $uuidHitNanoseconds = (clockNanoseconds() - $uuidHitStarted) / LOOKUP_ITERATIONS;
    $missStarted = clockNanoseconds();

    for ($iteration = 0; $iteration < LOOKUP_ITERATIONS; $iteration++) {
        if ($router->match($missRequest) !== null) {
            throw new RuntimeException('Expected the benchmark route to be absent.');
        }
    }

    $missNanoseconds = (clockNanoseconds() - $missStarted) / LOOKUP_ITERATIONS;
    $oversizedTokenMissStarted = clockNanoseconds();

    for ($iteration = 0; $iteration < LOOKUP_ITERATIONS; $iteration++) {
        if ($router->match($oversizedTokenRequest) !== null) {
            throw new RuntimeException('Expected the oversized benchmark token to miss routing.');
        }
    }

    $oversizedTokenMissNanoseconds = (
        clockNanoseconds() - $oversizedTokenMissStarted
    ) / LOOKUP_ITERATIONS;
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
        'typed_routes' => $routeCount * 2,
        'timed_typed_parameters' => 2,
        'fixed_parameter_types' => ['positive-int', 'token', 'uuid', 'ulid'],
        'timed_dynamic_parameter_type' => 'ulid',
        'timed_uuid_parameter_type' => 'uuid',
        'token_bytes' => TOKEN_BYTES,
        'total_routes' => $routeCount * 3,
        'construction_microseconds' => $constructionNanoseconds / 1_000,
        'route_memory_bytes' => $routeMemoryBytes,
        'hit_nanoseconds' => $hitNanoseconds,
        'dynamic_hit_nanoseconds' => $dynamicHitNanoseconds,
        'uuid_hit_nanoseconds' => $uuidHitNanoseconds,
        'miss_nanoseconds' => $missNanoseconds,
        'oversized_token_miss_nanoseconds' => $oversizedTokenMissNanoseconds,
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
    Request $uuidHitRequest,
    Request $missRequest,
    Request $oversizedTokenRequest,
): void
{
    $dynamicMatch = $router->match($dynamicHitRequest);
    $uuidMatch = $router->match($uuidHitRequest);

    if (
        $router->match($hitRequest) === null
        || $dynamicMatch === null
        || $uuidMatch === null
        || $dynamicMatch->pathParameters->positiveInteger('account_id') !== 999
        || $dynamicMatch->pathParameters->ulid('document_id') !== ULID_VALUE
        || $uuidMatch->pathParameters->positiveInteger('account_id') !== 999
        || $uuidMatch->pathParameters->uuid('document_id') !== UUID_VALUE
        || $router->match($missRequest) !== null
        || $router->match($oversizedTokenRequest) !== null
        || $router->allowedMethodsForPath($hitRequest->path) !== ['GET']
        || $router->allowedMethodsForPath($dynamicHitRequest->path) !== ['GET']
    ) {
        throw new RuntimeException('Routing benchmark lookup verification failed.');
    }
}
