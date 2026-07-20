<?php

declare(strict_types=1);

namespace PHPThis\Http;

use PHPThis\Session\SessionLifecycle;
use Throwable;

final readonly class RequestBoundary
{
    public function __construct(
        private RequestReader $reader,
        private RequestHandler $handler,
        private ErrorResponseRegistry $errorResponses,
        private ?SessionLifecycle $sessions = null,
    ) {
    }

    /**
     * @param array<array-key, mixed> $server
     * @param array<array-key, mixed> $query
     * @param array<array-key, mixed> $parsedFields
     * @param array<array-key, mixed> $files
     */
    public function handle(
        array $server,
        array $query,
        array $parsedFields = [],
        array $files = [],
    ): Response
    {
        $sessions = $this->sessions;
        $sessionBegun = false;

        try {
            $request = $this->reader->read($server, $query, $parsedFields, $files);

            if ($sessions !== null) {
                $sessions->begin($request);
                $sessionBegun = true;
            }

            $response = $this->handler->handle($request);
        } catch (Throwable $failure) {
            $response = $this->errorResponses->responseFor($failure);

            if ($response === null) {
                if ($sessionBegun && $sessions !== null) {
                    $sessions->abort();
                }

                throw $failure;
            }
        }

        return $sessionBegun && $sessions !== null ? $sessions->finish($response) : $response;
    }
}
