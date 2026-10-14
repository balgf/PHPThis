<?php

declare(strict_types=1);

use NamedHeaderRecipe\MachinePolicyFixture;
use PHPThis\Http\RequestReader;
use PHPThis\Http\Response;
use PHPThis\Verification\EnvironmentAccessProfile;
use PHPThis\Verification\SyntaxProfile;

require_once __DIR__ . '/fixtures/named-header/Fixture.php';
require_once dirname(__DIR__) . '/verification/SyntaxProfile.php';
require_once dirname(__DIR__) . '/verification/EnvironmentAccessProfile.php';

/** @return Generator<string, Closure(): void, mixed, void> */
function namedHeaderAuthenticationTests(): Generator
{
    yield 'named-header machine profile authenticates through explicit ordered policies' => static function (): void {
        [$fixture, $response] = namedHeaderScenario(['HTTP_EXAMPLE_MACHINE_KEY' => MachinePolicyFixture::CREDENTIAL]);
        requireNamedHeader($response->status === 200, 'Valid named-header request was denied.');
        requireNamedHeader($fixture->steps === ['authenticate', 'resolve_tenant', 'authorize', 'retrieve'], 'Machine policy order changed.');
        requireNamedHeader($fixture->budget->used() === 1 && $fixture->trace->snapshot()['statements'] === 1, 'Successful protected read was not exercised.');
        requireNamedHeader($response->body === "{\"data\":{\"account_id\":42,\"key\":\"Doc_Visible\",\"title\":\"Machine document\"}}\n", 'Explicit authority or representation changed.');
        requireNamedHeaderRedaction($fixture, $response, 'success');
    };

    yield 'named-header machine profile rejects malformed and alternate sources before verification' => static function (): void {
        $valid = ['HTTP_EXAMPLE_MACHINE_KEY' => MachinePolicyFixture::CREDENTIAL];
        $inputs = [[], ['HTTP_AUTHORIZATION' => 'Bearer SyntheticBearerMarker']];
        foreach (['', ' ', 'mk1_', 'mk1_' . str_repeat('a', 63), 'mk1_' . str_repeat('a', 65), 'mk1_' . str_repeat('A', 64), 'Bearer ' . MachinePolicyFixture::CREDENTIAL, MachinePolicyFixture::CREDENTIAL . ', ' . MachinePolicyFixture::CREDENTIAL, ' ' . MachinePolicyFixture::CREDENTIAL, MachinePolicyFixture::CREDENTIAL . ' ', 'mk1_' . str_repeat('é', 32)] as $value) {
            $inputs[] = ['HTTP_EXAMPLE_MACHINE_KEY' => $value];
        }
        foreach (['HTTP_AUTHORIZATION', 'HTTP_PROXY_AUTHORIZATION', 'HTTP_COOKIE', 'HTTP_X_API_KEY'] as $source) {
            $inputs[] = $valid + [$source => 'SyntheticAlternateMarker'];
            $inputs[] = $valid + [$source => ''];
        }
        foreach ($inputs as $headers) {
            [$fixture, $response] = namedHeaderScenario($headers);
            requireNamedHeaderDenial($fixture, $response, 403, []);
        }
        foreach ([['access_token' => MachinePolicyFixture::CREDENTIAL], ['api_key' => ['nested' => MachinePolicyFixture::CREDENTIAL]], ['unused' => 'value']] as $query) {
            [$fixture, $response] = namedHeaderScenario($valid, $query);
            requireNamedHeaderDenial($fixture, $response, 403, []);
        }
        [$fixture, $response] = namedHeaderScenario($valid, [], '{"credential":"SyntheticBodyMarker"}');
        requireNamedHeaderDenial($fixture, $response, 403, []);
    };

    yield 'named-header machine profile separates definitive rejection from verification uncertainty' => static function (): void {
        $valid = ['HTTP_EXAMPLE_MACHINE_KEY' => MachinePolicyFixture::CREDENTIAL];
        foreach (['expired', 'revoked', 'inactive', 'owner_disabled', 'wrong_audience'] as $mode) {
            [$fixture, $response] = namedHeaderScenario($valid, mode: $mode);
            requireNamedHeaderDenial($fixture, $response, 403, ['authenticate']);
        }
        [$fixture, $response] = namedHeaderScenario(['HTTP_EXAMPLE_MACHINE_KEY' => 'mk1_' . str_repeat('b', 64)]);
        requireNamedHeaderDenial($fixture, $response, 403, ['authenticate']);
        foreach (['outage', 'malformed_provider'] as $mode) {
            [$fixture, $response] = namedHeaderScenario($valid, mode: $mode);
            requireNamedHeaderDenial($fixture, $response, 503, ['authenticate']);
        }
        [$fixture, $response] = namedHeaderScenario($valid, mode: 'unexpected');
        requireNamedHeaderDenial($fixture, $response, 500, ['authenticate']);
    };

    yield 'named-header machine profile preserves tenant and action denials' => static function (): void {
        foreach (['cross_tenant' => ['authenticate', 'resolve_tenant'], 'scope_denied' => ['authenticate', 'resolve_tenant', 'authorize']] as $mode => $steps) {
            [$fixture, $response] = namedHeaderScenario(['HTTP_EXAMPLE_MACHINE_KEY' => MachinePolicyFixture::CREDENTIAL], mode: $mode);
            requireNamedHeaderDenial($fixture, $response, 403, $steps);
        }
    };

    yield 'named-header machine profile records transport rejection and raw-duplicate evidence limits' => static function (): void {
        foreach ([MachinePolicyFixture::CREDENTIAL . "\t", str_repeat('a', 8_193)] as $value) {
            [$fixture, $response] = namedHeaderScenario(['HTTP_EXAMPLE_MACHINE_KEY' => $value]);
            requireNamedHeaderDenial($fixture, $response, 400, []);
        }
        [$fixture, $response] = namedHeaderScenario(['HTTP_EXAMPLE_MACHINE_KEY' => MachinePolicyFixture::CREDENTIAL, 'HTTP_Example_Machine_Key' => 'different']);
        requireNamedHeaderDenial($fixture, $response, 400, []);
        // CGI metadata has already lost raw multiplicity. Its identical collision is accepted.
        // The separate proxy proof must reject duplicate raw fields before this point.
        [$fixture, $response] = namedHeaderScenario(['HTTP_EXAMPLE_MACHINE_KEY' => MachinePolicyFixture::CREDENTIAL, 'HTTP_Example_Machine_Key' => MachinePolicyFixture::CREDENTIAL]);
        requireNamedHeader($response->status === 200, 'Reader duplicate-normalization contract changed.');
        requireNamedHeaderRedaction($fixture, $response, 'success');
    };

    yield 'named-header machine recipe satisfies the strict source profiles' => static function (): void {
        foreach (['MachineCredential', 'AuthenticateMachineRequest', 'VerifyMachineCredential', 'MachineCredentialRejected', 'MachineVerificationUnavailable'] as $name) {
            $path = 'tests/fixtures/named-header/' . $name . '.php';
            $source = file_get_contents(dirname(__DIR__) . '/' . $path);
            requireNamedHeader(is_string($source), 'Machine recipe source is missing.');
            if (is_string($source)) {
                requireNamedHeader(SyntaxProfile::failures($source, $path) === [] && EnvironmentAccessProfile::inspect($source, $path)['failures'] === [], 'Machine recipe violates the structural profile.');
            }
        }
    };
}

/**
 * @param array<string, mixed> $headers
 * @param array<string, mixed> $query
 * @return array{MachinePolicyFixture, Response}
 */
function namedHeaderScenario(array $headers, array $query = [], string $body = '', string $mode = 'accepted'): array
{
    $fixture = new MachinePolicyFixture($mode);
    $reader = new RequestReader(1_024, 'data://text/plain;base64,' . base64_encode($body));
    $response = $fixture->coordinator($reader)->handle([
        'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/accounts/42/documents/Doc_Visible', ...$headers,
    ], $query);
    return [$fixture, $response];
}

/** @param list<string> $steps */
function requireNamedHeaderDenial(MachinePolicyFixture $fixture, Response $response, int $status, array $steps): void
{
    requireNamedHeader($response->status === $status && $fixture->steps === $steps, 'Machine denial status or stop point changed.');
    requireNamedHeader($fixture->budget->used() === 0 && $fixture->trace->snapshot()['statements'] === 0, 'Denied machine request reached protected SQL.');
    $body = match ($status) {
        400 => "{\"error\":{\"code\":\"invalid_request\",\"message\":\"Request is invalid.\"}}\n",
        403 => "{\"error\":{\"code\":\"forbidden\",\"message\":\"Request is forbidden.\"}}\n",
        503 => "{\"error\":{\"code\":\"service_unavailable\",\"message\":\"Service is unavailable.\"}}\n",
        500 => "{\"error\":{\"code\":\"internal_server_error\",\"message\":\"Internal server error.\"}}\n",
        default => throw new RuntimeException('Unexpected machine test status.'),
    };
    requireNamedHeader($response->body === $body, 'Machine denial disclosed classification or detail.');
    requireNamedHeaderRedaction($fixture, $response, $status === 500 ? 'unknown_failure' : 'known_failure');
}

function requireNamedHeaderRedaction(MachinePolicyFixture $fixture, Response $response, string $outcome): void
{
    $summary = $fixture->summary?->toArray();
    requireNamedHeader($fixture->summaries === 1 && ($summary['outcome'] ?? null) === $outcome, 'Machine request summary changed.');
    requireNamedHeader(($summary['response_status'] ?? null) === $response->status && ($summary['unknown_failure_class'] ?? null) === ($outcome === 'unknown_failure' ? RuntimeException::class : null), 'Machine summary disclosed a denial class or selected another status.');
    requireNamedHeader(($response->headers['Cache-Control'] ?? null) === 'private, no-store', 'Machine response lost its private no-store policy.');
    requireNamedHeader(!array_key_exists('WWW-Authenticate', $response->headers) && !array_key_exists('Retry-After', $response->headers), 'Machine response emitted an authentication or retry signal.');
    $evidence = json_encode([$response->headers, $response->body, $summary, $fixture->trace->snapshot()], JSON_THROW_ON_ERROR);
    foreach ([MachinePolicyFixture::CREDENTIAL, 'SyntheticBearerMarker', 'SyntheticAlternateMarker', 'SyntheticBodyMarker', 'PrivateVerifierMarker', 'expired', 'revoked', 'wrong_audience'] as $private) {
        requireNamedHeader(!str_contains($evidence, $private), 'Machine response or diagnostic disclosed private input.');
    }
}

function requireNamedHeader(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
