<?php

declare(strict_types=1);

namespace PHPThis\Http;

use InvalidArgumentException;
use RuntimeException;
use RequestParseBodyException;

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
        private ?MultipartLimits $multipartLimits = null,
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
    public function read(
        array $server,
        array $query,
    ): Request
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
            || str_contains($requestTargetValue, '#') || preg_match('/[\x00-\x20\x7F]/', $requestTargetValue) === 1
        ) {
            throw new InvalidRequest('REQUEST_URI has an invalid or oversized request-target representation.');
        }
        $queryPosition = strpos($requestTargetValue, '?');
        $path = $queryPosition === false
            ? $requestTargetValue
            : substr($requestTargetValue, 0, $queryPosition);
        if ($path === '' || $path[0] !== '/') {
            throw new InvalidRequest('REQUEST_URI must contain an absolute path.');
        }
        $peerAddress = null;
        if (array_key_exists('REMOTE_ADDR', $server)) {
            $address = $server['REMOTE_ADDR'];
            if (!is_string($address)) {
                throw new InvalidRequest('REMOTE_ADDR must be a bare IPv4 or IPv6 address of at most 45 bytes.');
            }
            try {
                $peerAddress = PeerAddress::fromString($address);
            } catch (InvalidArgumentException $failure) {
                throw new InvalidRequest('REMOTE_ADDR must be a bare IPv4 or IPv6 address of at most 45 bytes.', previous: $failure);
            }
        }
        $parsedQuery = $this->query($query);
        $headers = $this->headers($server);
        $multipart = $this->multipart($method, $headers);
        $body = $multipart === null ? $this->body($headers) : '';
        try {
            return new Request(
                method: $method,
                path: $path,
                query: $parsedQuery,
                body: $body,
                headers: $headers,
                multipart: $multipart,
                peerAddress: $peerAddress,
            );
        } catch (InvalidArgumentException $failure) {
            throw new InvalidRequest('PHP runtime input did not produce a valid request.', previous: $failure);
        }
    }

    /** @param array<string, string> $headers */
    private function multipart(string $method, array $headers): ?MultipartForm
    {
        $contentType = $headers['content-type'] ?? null;
        if (
            $contentType === null
            || preg_match('@^ *multipart/form-data(?: *;| *$)@iD', $contentType) !== 1
        ) {
            return null;
        }
        if ($method !== 'POST') {
            throw new InvalidRequest('Multipart requests require POST.');
        }
        $boundaryPattern = '@^multipart/form-data *; *boundary='
            . '(?:"[0-9A-Za-z\'()+_,./:=? -]{0,69}[0-9A-Za-z\'()+_,./:=?-]"'
            . '|[0-9A-Za-z\'+_.-]{1,70}) *$@iD';
        if (
            preg_match($boundaryPattern, $contentType) !== 1
            || array_key_exists('transfer-encoding', $headers)
        ) {
            throw new InvalidRequest('Multipart Content-Type requires one bounded boundary parameter.');
        }
        $declaredBytes = $this->contentLength($headers);
        $limits = $this->multipartLimits;
        if ($declaredBytes === null) {
            throw new InvalidRequest('Multipart requests require Content-Length.');
        }
        if ($limits === null) {
            throw new UnsupportedMediaType('Multipart form data is not enabled.');
        }
        if ($declaredBytes > $limits->maximumRequestBytes) {
            throw new RequestBodyTooLarge('Declared multipart request exceeds its byte limit.');
        }
        if (ini_get('enable_post_data_reading') !== '0' && ini_get('enable_post_data_reading') !== '') {
            throw new RuntimeException('Multipart ingestion requires automatic POST parsing to be disabled.');
        }
        if (ini_get('file_uploads') !== '1') {
            throw new RuntimeException('Multipart ingestion requires PHP file uploads to be enabled.');
        }
        try {
            [$fields, $files] = request_parse_body([
                'post_max_size' => $limits->maximumRequestBytes,
                'upload_max_filesize' => $limits->maximumFileBytes,
                'max_input_vars' => MultipartForm::MAXIMUM_FIELDS,
                'max_file_uploads' => MultipartForm::MAXIMUM_UPLOADS,
                'max_multipart_body_parts' => MultipartForm::MAXIMUM_PARTS,
            ]);
        } catch (RequestParseBodyException $failure) {
            throw new InvalidRequest('PHP could not parse the bounded multipart request.', previous: $failure);
        }
        return MultipartForm::fromPhp($fields, $files, $declaredBytes, $limits);
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
        $declaredBytes = $this->contentLength($headers);
        if ($declaredBytes !== null && $declaredBytes > $this->maximumBodyBytes) {
            throw new RequestBodyTooLarge('Declared request body exceeds its byte limit.');
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

    /** @param array<string, string> $headers */
    private function contentLength(array $headers): ?int
    {
        $contentLength = $headers['content-length'] ?? null;
        if ($contentLength === null) {
            return null;
        }
        if (preg_match('/^(0|[1-9][0-9]*)$/D', $contentLength) !== 1) {
            throw new InvalidRequest('Content-Length must be a canonical non-negative integer.');
        }
        $parsedLength = filter_var($contentLength, FILTER_VALIDATE_INT);
        if (!is_int($parsedLength)) {
            throw new RequestBodyTooLarge('Declared request body exceeds the supported integer range.');
        }
        return $parsedLength;
    }
}
