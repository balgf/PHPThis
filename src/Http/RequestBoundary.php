<?php

declare(strict_types=1);

namespace PHPThis\Http;

use Throwable;

final readonly class RequestBoundary
{
    public function __construct(
        private RequestReader $reader,
        private RequestHandler $handler,
        private ErrorResponseRegistry $errorResponses,
    ) {
    }

    /**
     * @param array<array-key, mixed> $server
     * @param array<array-key, mixed> $query
     */
    public function handle(array $server, array $query): Response
    {
        try {
            $request = $this->reader->read($server, $query);
            return $this->handler->handle($request);
        } catch (Throwable $failure) {
            $response = $this->errorResponses->responseFor($failure);

            if ($response !== null) {
                return $response;
            }

            throw $failure;
        }
    }
}
