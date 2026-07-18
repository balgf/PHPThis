<?php

declare(strict_types=1);

namespace PHPThis\Http;

use InvalidArgumentException;
use RuntimeException;

final readonly class RequestReader
{
    private const int MAXIMUM_REQUEST_TARGET_BYTES = 8_192;
    private const int MAXIMUM_QUERY_PARAMETERS = 64;
    private const int MAXIMUM_HEADERS = 64;
    private const int MAXIMUM_HEADER_VALUE_BYTES = 8_192;

    /** @var positive-int */
    private int $maximumBodyBytes;
    private string $inputUri;

    public function __construct(
        int $maximumBodyBytes,
        string $inputUri,
    ) {
        if ($maximumBodyBytes < 1 || $maximumBodyBytes >= PHP_INT_MAX) {
            throw new InvalidArgumentException('Maximum request body bytes must be positive and safely bounded.');
        }

        if ($inputUri === '') {
            throw new InvalidArgumentException('Request input URI must be non-empty.');
        }

        $this->maximumBodyBytes = $maximumBodyBytes;
        $this->inputUri = $inputUri;
    }

    /**
     * @param array<array-key, mixed> $server
     * @param array<array-key, mixed> $query
     */
    public function read(array $server, array $query): Request
    {
        $methodValue = $server['REQUEST_METHOD'] ?? null;
        $requestTargetValue = $server['REQUEST_URI'] ?? null;

        if (!is_string($methodValue)) {
            throw new InvalidRequest('REQUEST_METHOD must be a string.');
        }

        if (!is_string($requestTargetValue)) {
            throw new InvalidRequest('REQUEST_URI must be a string.');
        }

        $method = strtoupper($methodValue);

        if (preg_match('/^[A-Z]+$/D', $method) !== 1) {
            throw new InvalidRequest('REQUEST_METHOD must contain letters only.');
        }

        if (
            strlen($requestTargetValue) > self::MAXIMUM_REQUEST_TARGET_BYTES
            || str_contains($requestTargetValue, '#')
        ) {
            throw new InvalidRequest('REQUEST_URI exceeds the accepted path representation.');
        }

        $queryPosition = strpos($requestTargetValue, '?');
        $path = $queryPosition === false
            ? $requestTargetValue
            : substr($requestTargetValue, 0, $queryPosition);

        if ($path === '' || $path[0] !== '/') {
            throw new InvalidRequest('REQUEST_URI must contain an absolute path.');
        }

        $parsedQuery = $this->query($query);
        $headers = $this->headers($server);
        $body = $this->body($headers);

        try {
            return new Request(
                method: $method,
                path: $path,
                query: $parsedQuery,
                body: $body,
                headers: $headers,
            );
        } catch (InvalidArgumentException $failure) {
            throw new InvalidRequest('PHP runtime input did not produce a valid request.', previous: $failure);
        }
    }

    /**
     * @param array<array-key, mixed> $query
     * @return array<string, mixed>
     */
    private function query(array $query): array
    {
        if (count($query) > self::MAXIMUM_QUERY_PARAMETERS) {
            throw new InvalidRequest('The request contains too many query parameters.');
        }

        $parsed = [];

        foreach ($query as $name => $value) {
            if (!is_string($name)) {
                throw new InvalidRequest('Query parameter names must be strings.');
            }

            $parsed[$name] = $value;
        }

        return $parsed;
    }

    /**
     * @param array<array-key, mixed> $server
     * @return array<string, string>
     */
    private function headers(array $server): array
    {
        $headers = [];

        foreach ($server as $serverName => $value) {
            if (!is_string($serverName)) {
                continue;
            }

            $name = match (true) {
                $serverName === 'CONTENT_TYPE' => 'content-type',
                $serverName === 'CONTENT_LENGTH' => 'content-length',
                str_starts_with($serverName, 'HTTP_') => strtolower(
                    str_replace('_', '-', substr($serverName, 5)),
                ),
                default => null,
            };

            if ($name === null) {
                continue;
            }

            if (!is_string($value)) {
                throw new InvalidRequest('Request header values must be strings.');
            }

            if (
                $name === ''
                || preg_match('/^[!#$%&\'*+\-.^_`|~0-9a-z]+$/D', $name) !== 1
                || strlen($value) > self::MAXIMUM_HEADER_VALUE_BYTES
                || preg_match('/[\x00-\x1F\x7F]/', $value) === 1
            ) {
                throw new InvalidRequest('The request contains an invalid header representation.');
            }

            if (array_key_exists($name, $headers)) {
                if ($headers[$name] === $value) {
                    continue;
                }

                throw new InvalidRequest('Conflicting normalized request headers are forbidden.');
            }

            if (count($headers) >= self::MAXIMUM_HEADERS) {
                throw new InvalidRequest('The request contains too many headers.');
            }

            $headers[$name] = $value;
        }

        return $headers;
    }

    /** @param array<string, string> $headers */
    private function body(array $headers): string
    {
        $contentLength = $headers['content-length'] ?? null;
        $declaredBytes = null;

        if ($contentLength !== null) {
            if (preg_match('/^(0|[1-9][0-9]*)$/D', $contentLength) !== 1) {
                throw new InvalidRequest('Content-Length must be a canonical non-negative integer.');
            }

            $parsedLength = filter_var($contentLength, FILTER_VALIDATE_INT);

            if (!is_int($parsedLength)) {
                throw new RequestBodyTooLarge('Declared request body exceeds the supported integer range.');
            }

            if ($parsedLength > $this->maximumBodyBytes) {
                throw new RequestBodyTooLarge('Declared request body exceeds its byte limit.');
            }

            $declaredBytes = $parsedLength;
        }

        $body = file_get_contents(
            $this->inputUri,
            false,
            null,
            0,
            $this->maximumBodyBytes + 1,
        );

        if (!is_string($body)) {
            throw new RuntimeException('Unable to read the request input stream.');
        }

        $actualBytes = strlen($body);

        if ($actualBytes > $this->maximumBodyBytes) {
            throw new RequestBodyTooLarge('Actual request body exceeds its byte limit.');
        }

        if ($declaredBytes !== null && $actualBytes !== $declaredBytes) {
            throw new InvalidRequest('Content-Length does not match the request body.');
        }

        return $body;
    }
}
