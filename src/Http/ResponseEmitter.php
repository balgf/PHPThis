<?php

declare(strict_types=1);

namespace PHPThis\Http;

final class ResponseEmitter
{
    public function emit(Response $response): void
    {
        http_response_code($response->status);

        foreach ($response->headers as $name => $value) {
            header($name . ': ' . $value, true);
        }

        foreach ($response->cookies as $cookie) {
            header('Set-Cookie: ' . $cookie->headerValue(), false);
        }

        echo $response->body;
    }
}
