<?php

declare(strict_types=1);

use PHPThis\Application;
use PHPThis\Http\CookieSameSite;
use PHPThis\Http\LocalFileBody;
use PHPThis\Http\Request;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\Response;
use PHPThis\Http\ResponseCookie;
use PHPThis\Routing\Route;
use PHPThis\Routing\Router;

final class HandlerDecoratorProofTrace
{
    /** @var list<string> */
    public array $steps = [];

    /** @var list<Request> */
    public array $requests = [];

    public int $decoratorSideEffects = 0;

    public int $downstreamCalls = 0;

    public function recordDecoratorSideEffect(string $step, Request $request): void
    {
        $this->steps[] = $step;
        $this->requests[] = $request;
        $this->decoratorSideEffects++;
    }

    public function recordDownstreamCall(string $step, Request $request): void
    {
        $this->steps[] = $step;
        $this->requests[] = $request;
        $this->downstreamCalls++;
    }
}

final readonly class HandlerDecoratorProofTerminalHandler implements RequestHandler
{
    public function __construct(
        private HandlerDecoratorProofTrace $trace,
        private Response $response,
        private string $step,
        private ?Throwable $failure,
    ) {
    }

    public function handle(Request $request): Response
    {
        $this->trace->recordDownstreamCall($this->step, $request);

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->response;
    }
}

final readonly class HandlerDecoratorProofOrderMarkerHandler implements RequestHandler
{
    public function __construct(
        private RequestHandler $downstream,
        private HandlerDecoratorProofTrace $trace,
        private string $name,
    ) {
    }

    public function handle(Request $request): Response
    {
        $this->trace->recordDecoratorSideEffect("{$this->name}.before", $request);
        $response = $this->downstream->handle($request);
        $this->trace->recordDecoratorSideEffect("{$this->name}.after", $request);

        return $response;
    }
}

final readonly class HandlerDecoratorProofFailingMarkerHandler implements RequestHandler
{
    public function __construct(
        private RequestHandler $downstream,
        private HandlerDecoratorProofTrace $trace,
        private HandlerDecoratorProofFailingOperation $operation,
    ) {
    }

    public function handle(Request $request): Response
    {
        $this->trace->recordDecoratorSideEffect('failing-marker.before', $request);
        $this->operation->perform();

        return $this->downstream->handle($request);
    }
}

final readonly class HandlerDecoratorProofFailingOperation
{
    public function __construct(private Throwable $failure)
    {
    }

    public function perform(): void
    {
        throw $this->failure;
    }
}

final readonly class HandlerDecoratorProofMaintenanceGateHandler implements RequestHandler
{
    public function __construct(
        private RequestHandler $downstream,
        private HandlerDecoratorProofTrace $trace,
        private bool $maintenanceActive,
    ) {
    }

    public function handle(Request $request): Response
    {
        $this->trace->recordDecoratorSideEffect('maintenance.checked', $request);

        if ($this->maintenanceActive) {
            $this->trace->recordDecoratorSideEffect('maintenance.active', $request);

            return new Response(
                503,
                [
                    'Cache-Control' => 'no-store',
                    'Content-Type' => 'text/plain; charset=utf-8',
                ],
                "Maintenance\n",
            );
        }

        return $this->downstream->handle($request);
    }
}

final readonly class HandlerDecoratorProofResponseMarkerHandler implements RequestHandler
{
    public function __construct(
        private RequestHandler $downstream,
        private HandlerDecoratorProofTrace $trace,
    ) {
    }

    public function handle(Request $request): Response
    {
        $response = $this->downstream->handle($request);
        $this->trace->recordDecoratorSideEffect('response.replaced', $request);

        return new Response(
            status: $response->status,
            headers: [...$response->headers, 'X-Handler-Decorator' => 'applied'],
            body: $response->body,
            cookies: $response->cookies,
            fileBody: $response->fileBody,
        );
    }
}

/** @return array<string, Closure(): void> */
function handlerDecoratorTests(): array
{
    return [
        'explicit nested handler decorators preserve request and response identity' => static function (): void {
            $trace = new HandlerDecoratorProofTrace();
            $expectedResponse = new Response(
                200,
                ['Content-Type' => 'text/plain; charset=utf-8'],
                "handled\n",
            );
            $application = new Application(new Router([
                new Route(
                    'GET',
                    '/accounts/{account_id:positive-int}',
                    new HandlerDecoratorProofOrderMarkerHandler(
                        new HandlerDecoratorProofOrderMarkerHandler(
                            new HandlerDecoratorProofTerminalHandler(
                                $trace,
                                $expectedResponse,
                                'terminal',
                                null,
                            ),
                            $trace,
                            'inner',
                        ),
                        $trace,
                        'outer',
                    ),
                ),
            ]));
            $submitted = new Request(
                'GET',
                '/accounts/42',
                ['view' => 'summary'],
                'request-body',
                ['x-proof' => 'request'],
            );
            $actualResponse = $application->handle($submitted);
            $delivered = $trace->requests[0] ?? null;

            if (
                !$delivered instanceof Request
                || $delivered === $submitted
                || $delivered->method !== $submitted->method
                || $delivered->path !== $submitted->path
                || $delivered->query !== $submitted->query
                || $delivered->body !== $submitted->body
                || $delivered->headers !== $submitted->headers
                || $delivered->pathParameters->positiveInteger('account_id') !== 42
                || $trace->requests !== [$delivered, $delivered, $delivered, $delivered, $delivered]
                || $trace->steps !== [
                    'outer.before',
                    'inner.before',
                    'terminal',
                    'inner.after',
                    'outer.after',
                ]
                || $trace->decoratorSideEffects !== 4
                || $trace->downstreamCalls !== 1
                || $actualResponse !== $expectedResponse
            ) {
                throw new RuntimeException(
                    'Expected explicit nesting to preserve one delivered request and the original response.',
                );
            }
        },
        'maintenance gate short-circuits or delegates exactly once' => static function (): void {
            $activeTrace = new HandlerDecoratorProofTrace();
            $unusedResponse = new Response(204, [], '');
            $activeApplication = new Application(new Router([
                new Route(
                    'GET',
                    '/maintenance-gated',
                    new HandlerDecoratorProofMaintenanceGateHandler(
                        new HandlerDecoratorProofTerminalHandler(
                            $activeTrace,
                            $unusedResponse,
                            'terminal',
                            null,
                        ),
                        $activeTrace,
                        true,
                    ),
                ),
            ]));
            $activeResponse = $activeApplication->handle(new Request('GET', '/maintenance-gated'));

            if (
                $activeResponse->status !== 503
                || $activeResponse->headers !== [
                    'Cache-Control' => 'no-store',
                    'Content-Type' => 'text/plain; charset=utf-8',
                ]
                || $activeResponse->body !== "Maintenance\n"
                || $activeTrace->steps !== ['maintenance.checked', 'maintenance.active']
                || $activeTrace->decoratorSideEffects !== 2
                || $activeTrace->downstreamCalls !== 0
            ) {
                throw new RuntimeException('Expected maintenance to perform two bounded effects and no delegation.');
            }

            $inactiveTrace = new HandlerDecoratorProofTrace();
            $expectedResponse = new Response(204, [], '');
            $inactiveApplication = new Application(new Router([
                new Route(
                    'GET',
                    '/maintenance-gated',
                    new HandlerDecoratorProofMaintenanceGateHandler(
                        new HandlerDecoratorProofTerminalHandler(
                            $inactiveTrace,
                            $expectedResponse,
                            'terminal',
                            null,
                        ),
                        $inactiveTrace,
                        false,
                    ),
                ),
            ]));
            $inactiveResponse = $inactiveApplication->handle(new Request('GET', '/maintenance-gated'));

            if (
                $inactiveResponse !== $expectedResponse
                || $inactiveTrace->steps !== ['maintenance.checked', 'terminal']
                || $inactiveTrace->decoratorSideEffects !== 1
                || $inactiveTrace->downstreamCalls !== 1
            ) {
                throw new RuntimeException('Expected inactive maintenance to delegate exactly once.');
            }
        },
        'handler decorator propagates the exact downstream exception' => static function (): void {
            $trace = new HandlerDecoratorProofTrace();
            $failure = new RuntimeException('handler decorator proof failure');
            $application = new Application(new Router([
                new Route(
                    'GET',
                    '/failure',
                    new HandlerDecoratorProofOrderMarkerHandler(
                        new HandlerDecoratorProofTerminalHandler(
                            $trace,
                            new Response(204, [], ''),
                            'terminal',
                            $failure,
                        ),
                        $trace,
                        'order-marker',
                    ),
                ),
            ]));
            $caught = null;

            try {
                $application->handle(new Request('GET', '/failure'));
            } catch (Throwable $thrown) {
                $caught = $thrown;
            }

            if (
                $caught !== $failure
                || $trace->steps !== ['order-marker.before', 'terminal']
                || $trace->decoratorSideEffects !== 1
                || $trace->downstreamCalls !== 1
            ) {
                throw new RuntimeException('Expected the original failure without an after-side effect.');
            }
        },
        'handler decorator propagates its exact own exception before delegation' => static function (): void {
            $trace = new HandlerDecoratorProofTrace();
            $failure = new RuntimeException('handler decorator own proof failure');
            $application = new Application(new Router([
                new Route(
                    'GET',
                    '/own-failure',
                    new HandlerDecoratorProofFailingMarkerHandler(
                        new HandlerDecoratorProofTerminalHandler(
                            $trace,
                            new Response(204, [], ''),
                            'terminal',
                            null,
                        ),
                        $trace,
                        new HandlerDecoratorProofFailingOperation($failure),
                    ),
                ),
            ]));
            $caught = null;

            try {
                $application->handle(new Request('GET', '/own-failure'));
            } catch (Throwable $thrown) {
                $caught = $thrown;
            }

            if (
                $caught !== $failure
                || $trace->steps !== ['failing-marker.before']
                || $trace->decoratorSideEffects !== 1
                || $trace->downstreamCalls !== 0
            ) {
                throw new RuntimeException('Expected the decorator-owned failure before downstream work.');
            }
        },
        'response decorator preserves immutable buffered and local-file response fields' => static function (): void {
            $bufferedTrace = new HandlerDecoratorProofTrace();
            $bufferedCookie = new ResponseCookie(
                'buffered',
                'yes',
                '/',
                true,
                true,
                CookieSameSite::Lax,
            );
            $bufferedOriginal = new Response(
                422,
                ['Content-Type' => 'text/plain; charset=utf-8'],
                "buffered\n",
                [$bufferedCookie],
            );
            $fileTrace = new HandlerDecoratorProofTrace();
            $fileCookie = new ResponseCookie(
                'download',
                'yes',
                '/',
                true,
                true,
                CookieSameSite::Strict,
            );
            $fileBody = new LocalFileBody(
                sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpthis-handler-decorator-proof.bin',
                16,
            );
            $fileOriginal = new Response(
                200,
                [
                    'Content-Type' => 'application/octet-stream',
                    'Content-Length' => '16',
                    'Accept-Ranges' => 'none',
                ],
                '',
                [$fileCookie],
                $fileBody,
            );
            $application = new Application(new Router([
                new Route(
                    'GET',
                    '/buffered',
                    new HandlerDecoratorProofResponseMarkerHandler(
                        new HandlerDecoratorProofTerminalHandler(
                            $bufferedTrace,
                            $bufferedOriginal,
                            'buffered.terminal',
                            null,
                        ),
                        $bufferedTrace,
                    ),
                ),
                new Route(
                    'GET',
                    '/download',
                    new HandlerDecoratorProofResponseMarkerHandler(
                        new HandlerDecoratorProofTerminalHandler(
                            $fileTrace,
                            $fileOriginal,
                            'file.terminal',
                            null,
                        ),
                        $fileTrace,
                    ),
                ),
            ]));
            $buffered = $application->handle(new Request('GET', '/buffered'));
            $file = $application->handle(new Request('GET', '/download'));

            if (
                $buffered === $bufferedOriginal
                || $buffered->status !== 422
                || $buffered->headers !== [
                    'Content-Type' => 'text/plain; charset=utf-8',
                    'X-Handler-Decorator' => 'applied',
                ]
                || $buffered->body !== "buffered\n"
                || $buffered->cookies !== [$bufferedCookie]
                || $buffered->fileBody !== null
                || $bufferedOriginal->headers !== ['Content-Type' => 'text/plain; charset=utf-8']
                || $bufferedTrace->steps !== ['buffered.terminal', 'response.replaced']
                || $bufferedTrace->decoratorSideEffects !== 1
                || $bufferedTrace->downstreamCalls !== 1
                || $file === $fileOriginal
                || $file->status !== 200
                || $file->headers !== [
                    'Content-Type' => 'application/octet-stream',
                    'Content-Length' => '16',
                    'Accept-Ranges' => 'none',
                    'X-Handler-Decorator' => 'applied',
                ]
                || $file->body !== ''
                || $file->cookies !== [$fileCookie]
                || $file->fileBody !== $fileBody
                || $fileOriginal->headers !== [
                    'Content-Type' => 'application/octet-stream',
                    'Content-Length' => '16',
                    'Accept-Ranges' => 'none',
                ]
                || $fileTrace->steps !== ['file.terminal', 'response.replaced']
                || $fileTrace->decoratorSideEffects !== 1
                || $fileTrace->downstreamCalls !== 1
            ) {
                throw new RuntimeException('Expected response replacement to preserve every immutable field.');
            }
        },
        'handler decoration is route-local and removable by direct rewiring' => static function (): void {
            $decoratedTrace = new HandlerDecoratorProofTrace();
            $plainTrace = new HandlerDecoratorProofTrace();
            $decoratedResponse = new Response(200, [], "decorated\n");
            $plainResponse = new Response(200, [], "plain\n");
            $application = new Application(new Router([
                new Route(
                    'GET',
                    '/decorated',
                    new HandlerDecoratorProofOrderMarkerHandler(
                        new HandlerDecoratorProofTerminalHandler(
                            $decoratedTrace,
                            $decoratedResponse,
                            'decorated.terminal',
                            null,
                        ),
                        $decoratedTrace,
                        'order-marker',
                    ),
                ),
                new Route(
                    'GET',
                    '/plain',
                    new HandlerDecoratorProofTerminalHandler(
                        $plainTrace,
                        $plainResponse,
                        'plain.terminal',
                        null,
                    ),
                ),
            ]));
            $plain = $application->handle(new Request('GET', '/plain'));
            $notFound = $application->handle(new Request('GET', '/missing'));
            $notAllowed = $application->handle(new Request('POST', '/decorated'));

            if (
                $plain !== $plainResponse
                || $plainTrace->steps !== ['plain.terminal']
                || $plainTrace->downstreamCalls !== 1
                || $decoratedTrace->steps !== []
                || $notFound->status !== 404
                || $notFound->headers !== [
                    'Cache-Control' => 'no-store',
                    'Content-Type' => 'text/plain; charset=utf-8',
                ]
                || $notFound->body !== "Not Found\n"
                || $notAllowed->status !== 405
                || $notAllowed->headers !== [
                    'Allow' => 'GET',
                    'Cache-Control' => 'no-store',
                    'Content-Type' => 'text/plain; charset=utf-8',
                ]
                || $notAllowed->body !== "Method Not Allowed\n"
            ) {
                throw new RuntimeException('Expected undecorated, 404, and 405 paths to bypass decoration.');
            }

            $activeTrace = new HandlerDecoratorProofTrace();
            $activeResponse = new Response(200, [], "decorated\n");
            $activeApplication = new Application(new Router([
                new Route(
                    'GET',
                    '/decorated',
                    new HandlerDecoratorProofOrderMarkerHandler(
                        new HandlerDecoratorProofTerminalHandler(
                            $activeTrace,
                            $activeResponse,
                            'decorated.terminal',
                            null,
                        ),
                        $activeTrace,
                        'order-marker',
                    ),
                ),
            ]));
            $decorated = $activeApplication->handle(new Request('GET', '/decorated'));
            $rewiredTrace = new HandlerDecoratorProofTrace();
            $rewiredResponse = new Response(200, [], "rewired\n");
            $rewiredApplication = new Application(new Router([
                new Route(
                    'GET',
                    '/decorated',
                    new HandlerDecoratorProofTerminalHandler(
                        $rewiredTrace,
                        $rewiredResponse,
                        'rewired.terminal',
                        null,
                    ),
                ),
            ]));
            $rewired = $rewiredApplication->handle(new Request('GET', '/decorated'));

            if (
                $decorated !== $activeResponse
                || $activeTrace->steps !== [
                    'order-marker.before',
                    'decorated.terminal',
                    'order-marker.after',
                ]
                || $activeTrace->decoratorSideEffects !== 2
                || $activeTrace->downstreamCalls !== 1
                || $rewired !== $rewiredResponse
                || $rewiredTrace->steps !== ['rewired.terminal']
                || $rewiredTrace->decoratorSideEffects !== 0
                || $rewiredTrace->downstreamCalls !== 1
            ) {
                throw new RuntimeException('Expected decoration to be replaceable at the route constructor.');
            }
        },
    ];
}
