<?php

declare(strict_types=1);

namespace PHPThis\Http {
    final class ResponseEmitterSpy
    {
        /** @var list<array{line: string, replace: bool}> */
        public static array $headers = [];
        public static ?int $status = null;
        public static bool $headersSent = false;

        public static function reset(): void
        {
            self::$headers = [];
            self::$status = null;
            self::$headersSent = false;
        }

        /**
         * @return array{
         *     status: int|null,
         *     headers: list<array{line: string, replace: bool}>
         * }
         */
        public static function snapshot(): array
        {
            return ['status' => self::$status, 'headers' => self::$headers];
        }
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

    function headers_sent(): bool
    {
        return ResponseEmitterSpy::$headersSent;
    }
}

namespace {
    use PHPThis\Application;
    use PHPThis\Http\CookieSameSite;
    use PHPThis\Http\LocalFileBody;
    use PHPThis\Http\Request;
    use PHPThis\Http\RequestHandler;
    use PHPThis\Http\Response;
    use PHPThis\Http\ResponseCookie;
    use PHPThis\Http\ResponseEmissionFailed;
    use PHPThis\Http\ResponseEmitter;
    use PHPThis\Http\ResponseEmitterSpy;
    use PHPThis\Routing\Route;
    use PHPThis\Routing\Router;

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

    $ordinaryStatus = ResponseEmitterSpy::$status;
    $ordinaryHeaders = ResponseEmitterSpy::$headers;
    $path = tempnam(sys_get_temp_dir(), 'phpthis-response-');

    if (!is_string($path)) {
        throw new RuntimeException('Unable to create the local-file response fixture.');
    }

    $selectedPath = $path . '.selected';
    $symlinkTargetPath = $path . '.target';
    $nonRegularPath = $path . '.directory';

    try {
        $fileContents = str_repeat('0123456789abcdef', 1_250);
        $fileBytes = strlen($fileContents);
        $writtenBytes = file_put_contents($path, $fileContents, LOCK_EX);

        if (!is_int($writtenBytes) || $writtenBytes !== $fileBytes) {
            throw new RuntimeException('Unable to write the local-file response fixture.');
        }

        $fileResponse = new Response(
            200,
            [
                'Content-Type' => 'application/octet-stream',
                'Content-Length' => (string) $fileBytes,
                'Accept-Ranges' => 'none',
            ],
            '',
            [],
            new LocalFileBody($path, $fileBytes),
        );
        ResponseEmitterSpy::reset();
        ob_start();
        (new ResponseEmitter())->emit($fileResponse);
        $fileOutput = ob_get_clean();

        if (
            $fileOutput !== $fileContents
            || ResponseEmitterSpy::$status !== 200
            || ResponseEmitterSpy::$headers !== [
                ['line' => 'Content-Type: application/octet-stream', 'replace' => true],
                ['line' => 'Content-Length: ' . $fileBytes, 'replace' => true],
                ['line' => 'Accept-Ranges: none', 'replace' => true],
            ]
        ) {
            throw new RuntimeException('Expected the complete local file to be emitted in bounded chunks.');
        }

        $replacementContents = str_repeat('fedcba9876543210', 1_250);

        if (
            !rename($path, $selectedPath)
            || file_put_contents($path, $replacementContents, LOCK_EX) !== $fileBytes
        ) {
            throw new RuntimeException('Unable to create the same-size replacement fixture.');
        }

        ResponseEmitterSpy::reset();
        ob_start();
        (new ResponseEmitter())->emit($fileResponse);
        $replacementOutput = ob_get_clean();

        if ($replacementOutput !== $replacementContents) {
            throw new RuntimeException(
                'Expected path-only local-file selection to emit a same-size regular replacement.',
            );
        }

        $symlinkTargetContents = str_repeat('abcdefghijklmnop', 1_250);

        if (
            !unlink($path)
            || file_put_contents($symlinkTargetPath, $symlinkTargetContents, LOCK_EX) !== $fileBytes
            || !symlink($symlinkTargetPath, $path)
        ) {
            throw new RuntimeException('Unable to create the same-size symlink-target fixture.');
        }

        ResponseEmitterSpy::reset();
        ob_start();
        (new ResponseEmitter())->emit($fileResponse);
        $symlinkTargetOutput = ob_get_clean();

        if ($symlinkTargetOutput !== $symlinkTargetContents) {
            throw new RuntimeException(
                'Expected a symlink to a same-size regular target to remain outside emitter detection.',
            );
        }

        if (!unlink($path) || !rename($selectedPath, $path)) {
            throw new RuntimeException('Unable to restore the selected local-file fixture.');
        }

        $invalidValues = [
            static fn(): LocalFileBody => new LocalFileBody('relative.file', 0),
            static fn(): LocalFileBody => new LocalFileBody($path . "\n", 0),
            static fn(): LocalFileBody => new LocalFileBody($path, -1),
            static fn(): Response => new Response(103, [], ''),
            static fn(): Response => new Response(600, [], ''),
            static fn(): Response => new Response(200, ['Transfer-Encoding' => 'identity'], ''),
            static fn(): Response => new Response(200, ['Content-Length' => '8'], 'created'),
            static fn(): Response => new Response(200, ['Content-Length' => '07'], 'created'),
            static fn(): Response => new Response(204, [], 'created'),
            static fn(): Response => new Response(204, ['Content-Length' => '0'], ''),
            static fn(): Response => new Response(205, [], 'created'),
            static fn(): Response => new Response(205, ['Content-Length' => '0'], ''),
            static fn(): Response => new Response(304, [], 'created'),
            static fn(): Response => new Response(304, ['Content-Length' => '0'], ''),
            static fn(): Response => new Response(200, ['X-Test' => "value\0suffix"], ''),
            static fn(): Response => new Response(
                200,
                ['Content-Type' => 'text/plain', 'content-type' => 'text/html'],
                '',
            ),
            static fn(): Response => new Response(
                200,
                ['Content-Length' => (string) $fileBytes],
                'buffered',
                [],
                new LocalFileBody($path, $fileBytes),
            ),
            static fn(): Response => new Response(
                200,
                [],
                '',
                [],
                new LocalFileBody($path, $fileBytes),
            ),
            static fn(): Response => new Response(
                200,
                ['Content-Length' => '0' . $fileBytes],
                '',
                [],
                new LocalFileBody($path, $fileBytes),
            ),
            static fn(): Response => new Response(
                200,
                ['Content-Length' => (string) $fileBytes, 'Transfer-Encoding' => 'chunked'],
                '',
                [],
                new LocalFileBody($path, $fileBytes),
            ),
            static fn(): Response => new Response(
                206,
                ['Content-Length' => (string) $fileBytes],
                '',
                [],
                new LocalFileBody($path, $fileBytes),
            ),
            static fn(): Response => new Response(
                200,
                ['Content-Length' => (string) $fileBytes, 'Content-Range' => 'bytes 0-1/2'],
                '',
                [],
                new LocalFileBody($path, $fileBytes),
            ),
        ];

        foreach ($invalidValues as $invalidValue) {
            try {
                $invalidValue();
            } catch (\InvalidArgumentException) {
                continue;
            }

            throw new RuntimeException('Expected invalid response framing to be rejected.');
        }

        $exactLengthResponse = new Response(200, ['Content-Length' => '7'], 'created');
        $headHandler = new class implements RequestHandler {
            public function handle(Request $request): Response
            {
                return new Response(200, [], '');
            }
        };
        $explicitHeadResponse = (new Application(new Router([
            new Route('HEAD', '/explicit-head', $headHandler),
        ])))->handle(new Request('HEAD', '/explicit-head'));

        if (
            $exactLengthResponse->body !== 'created'
            || $explicitHeadResponse->status !== 200
            || $explicitHeadResponse->body !== ''
            || $explicitHeadResponse->headers !== []
        ) {
            throw new RuntimeException('Expected supported ordinary response framing to remain valid.');
        }

        foreach ([204, 205, 304] as $emptyStatus) {
            $emptyResponse = new Response($emptyStatus, [], '');

            if ($emptyResponse->body !== '' || $emptyResponse->headers !== []) {
                throw new RuntimeException('Expected a bodyless status without Content-Length to remain valid.');
            }
        }

        $mismatchedBytes = $fileBytes + 1;
        if (!mkdir($nonRegularPath, 0700)) {
            throw new RuntimeException('Unable to create the non-regular local-file fixture.');
        }

        $failedResponses = [
            [
                'path' => $path,
                'response' => new Response(
                    200,
                    ['Content-Length' => (string) $mismatchedBytes],
                    '',
                    [],
                    new LocalFileBody($path, $mismatchedBytes),
                ),
            ],
            [
                'path' => $path . '.missing',
                'response' => new Response(
                    200,
                    ['Content-Length' => '0'],
                    '',
                    [],
                    new LocalFileBody($path . '.missing', 0),
                ),
            ],
            [
                'path' => $nonRegularPath,
                'response' => new Response(
                    200,
                    ['Content-Length' => (string) $fileBytes],
                    '',
                    [],
                    new LocalFileBody($nonRegularPath, $fileBytes),
                ),
            ],
        ];

        foreach ($failedResponses as $failedResponse) {
            ResponseEmitterSpy::reset();

            try {
                (new ResponseEmitter())->emit($failedResponse['response']);
                throw new RuntimeException('Expected local-file emission to fail.');
            } catch (ResponseEmissionFailed $failure) {
                $failedEmission = ResponseEmitterSpy::snapshot();

                if (
                    $failedEmission['status'] !== null
                    || $failedEmission['headers'] !== []
                    || $failure->responseStarted
                    || str_contains($failure->getMessage(), $failedResponse['path'])
                ) {
                    throw new RuntimeException(
                        'Expected local-file failure before headers without path disclosure.',
                    );
                }
            }
        }

        ResponseEmitterSpy::reset();
        ResponseEmitterSpy::$headersSent = true;

        try {
            (new ResponseEmitter())->emit($fileResponse);
            throw new RuntimeException('Expected prior output to reject local-file emission.');
        } catch (ResponseEmissionFailed $failure) {
            if (!$failure->responseStarted || ResponseEmitterSpy::snapshot() !== ['status' => null, 'headers' => []]) {
                throw new RuntimeException('Expected prior output to be classified as a started response.');
            }
        } finally {
            ResponseEmitterSpy::reset();
        }
    } finally {
        if (is_link($path) && !unlink($path)) {
            throw new RuntimeException('Unable to remove the local-file response symlink fixture.');
        }
        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException('Unable to remove the local-file response fixture.');
        }
        if (is_file($selectedPath) && !unlink($selectedPath)) {
            throw new RuntimeException('Unable to remove the selected local-file response fixture.');
        }
        if (is_file($symlinkTargetPath) && !unlink($symlinkTargetPath)) {
            throw new RuntimeException('Unable to remove the symlink-target response fixture.');
        }
        if (is_dir($nonRegularPath) && !rmdir($nonRegularPath)) {
            throw new RuntimeException('Unable to remove the non-regular response fixture.');
        }
    }

    echo json_encode([
        'status' => $ordinaryStatus,
        'headers' => $ordinaryHeaders,
        'body' => $body,
    ], JSON_THROW_ON_ERROR), "\n";
}
