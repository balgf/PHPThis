<?php

declare(strict_types=1);

namespace PHPThis\Http {
    final class ResponseEmitterSpy
    {
        /** @var list<array{line: string, replace: bool}> */
        public static array $headers = [];
        public static ?int $status = null;
    }

    function header(string $header, bool $replace = true): void
    {
        ResponseEmitterSpy::$headers[] = ['line' => $header, 'replace' => $replace];
    }

    function http_response_code(int $responseCode): bool
    {
        ResponseEmitterSpy::$status = $responseCode;
        return true;
    }
}

namespace {
    use PHPThis\Http\CookieSameSite;
    use PHPThis\Http\Response;
    use PHPThis\Http\ResponseCookie;
    use PHPThis\Http\ResponseEmitter;
    use PHPThis\Http\ResponseEmitterSpy;

    require dirname(__DIR__) . '/autoload.php';

    $response = new Response(
        201,
        ['Content-Type' => 'text/plain'],
        'created',
        [
            new ResponseCookie('first', 'one', '/', true, true, CookieSameSite::Lax),
            new ResponseCookie('second', 'two', '/', true, true, CookieSameSite::Strict),
        ],
    );

    ob_start();
    (new ResponseEmitter())->emit($response);
    $body = ob_get_clean();

    if (!is_string($body)) {
        throw new RuntimeException('Unable to capture emitted response body.');
    }

    echo json_encode([
        'status' => ResponseEmitterSpy::$status,
        'headers' => ResponseEmitterSpy::$headers,
        'body' => $body,
    ], JSON_THROW_ON_ERROR), "\n";
}
