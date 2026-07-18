<?php

declare(strict_types=1);

namespace PHPThis;

use PHPThis\Http\Request;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\Response;
use PHPThis\Routing\Router;

final readonly class Application implements RequestHandler
{
    public function __construct(private Router $router)
    {
    }

    public function handle(Request $request): Response
    {
        $route = $this->router->match($request);

        if ($route !== null) {
            return $route->handler->handle($request);
        }

        $allowedMethods = $this->router->allowedMethodsForPath($request->path);

        if ($allowedMethods !== []) {
            return new Response(
                status: 405,
                headers: [
                    'Allow' => implode(', ', $allowedMethods),
                    'Content-Type' => 'text/plain; charset=utf-8',
                ],
                body: "Method Not Allowed\n",
            );
        }

        return new Response(
            status: 404,
            headers: ['Content-Type' => 'text/plain; charset=utf-8'],
            body: "Not Found\n",
        );
    }
}
