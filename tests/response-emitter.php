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
    use PHPThis\Http\CookieSameSite;
    use PHPThis\Http\LocalFileBody;
    use PHPThis\Http\Response;
    use PHPThis\Http\ResponseCookie;
    use PHPThis\Http\ResponseEmissionFailed;
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

    $ordinaryStatus = ResponseEmitterSpy::$status;
    $ordinaryHeaders = ResponseEmitterSpy::$headers;
    $path = tempnam(sys_get_temp_dir(), 'phpthis-response-');

    if (!is_string($path)) {
        throw new RuntimeException('Unable to create the local-file response fixture.');
    }

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

        $invalidValues = [
            static fn(): LocalFileBody => new LocalFileBody('relative.file', 0),
            static fn(): LocalFileBody => new LocalFileBody($path . "\n", 0),
            static fn(): LocalFileBody => new LocalFileBody($path, -1),
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

            throw new RuntimeException('Expected invalid local-file response framing to be rejected.');
        }

        $mismatchedBytes = $fileBytes + 1;
        $failedResponses = [
            new Response(
                200,
                ['Content-Length' => (string) $mismatchedBytes],
                '',
                [],
                new LocalFileBody($path, $mismatchedBytes),
            ),
            new Response(
                200,
                ['Content-Length' => '0'],
                '',
                [],
                new LocalFileBody($path . '.missing', 0),
            ),
        ];

        foreach ($failedResponses as $failedResponse) {
            ResponseEmitterSpy::reset();

            try {
                (new ResponseEmitter())->emit($failedResponse);
                throw new RuntimeException('Expected local-file emission to fail.');
            } catch (ResponseEmissionFailed $failure) {
                $failedEmission = ResponseEmitterSpy::snapshot();

                if (
                    $failedEmission['status'] !== null
                    || $failedEmission['headers'] !== []
                    || $failure->responseStarted
                    || str_contains($failure->getMessage(), $path)
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
        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException('Unable to remove the local-file response fixture.');
        }
    }

    echo json_encode([
        'status' => $ordinaryStatus,
        'headers' => $ordinaryHeaders,
        'body' => $body,
    ], JSON_THROW_ON_ERROR), "\n";
}
