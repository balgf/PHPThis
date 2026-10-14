<?php

declare(strict_types=1);

namespace PHPThis;

use PHPThis\Http\ErrorRepresentation;
use PHPThis\Http\Request;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\Response;
use PHPThis\Routing\Router;

final readonly class Application implements RequestHandler
{
    public function __construct(
        private Router $router,
        private ?ErrorRepresentation $notFound = null,
        private ?ErrorRepresentation $methodNotAllowed = null,
    ) {
    }

    public function handle(Request $request): Response
    {
        $match = $this->router->match($request);

        if ($match !== null) {
            return $match->route->handler->handle(
                $request->withPathParameters($match->pathParameters),
            );
        }

        $allowedMethods = $this->router->allowedMethodsForPath($request->path);

        if ($allowedMethods !== []) {
            return new Response(
                status: 405,
                headers: [
                    'Allow' => implode(', ', $allowedMethods),
                    'Cache-Control' => 'no-store',
                    'Content-Type' => $this->methodNotAllowed->contentType ?? 'text/plain; charset=utf-8',
                ],
                body: $this->methodNotAllowed->body ?? "Method Not Allowed\n",
            );
        }

        return new Response(
            status: 404,
            headers: [
                'Cache-Control' => 'no-store',
                'Content-Type' => $this->notFound->contentType ?? 'text/plain; charset=utf-8',
            ],
            body: $this->notFound->body ?? "Not Found\n",
        );
    }
}
