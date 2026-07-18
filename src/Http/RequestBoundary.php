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
     */
    public function handle(array $server, array $query): Response
    {
        $sessions = $this->sessions;
        $sessionBegun = false;

        try {
            $request = $this->reader->read($server, $query);

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
