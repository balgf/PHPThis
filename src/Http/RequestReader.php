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
    private const int MAXIMUM_TEMPORARY_PATH_BYTES = 4_096;

    /** @var positive-int */
    private int $maximumBodyBytes;
    private string $inputUri;
    /** @var positive-int|null */
    private ?int $maximumMultipartBytes;

    public function __construct(
        int $maximumBodyBytes,
        string $inputUri,
        ?int $maximumMultipartBytes = null,
    ) {
        if ($maximumBodyBytes < 1 || $maximumBodyBytes >= PHP_INT_MAX) {
            throw new InvalidArgumentException('Maximum request body bytes must be positive and safely bounded.');
        }
        if ($inputUri === '') {
            throw new InvalidArgumentException('Request input URI must be non-empty.');
        }
        if (
            $maximumMultipartBytes !== null
            && ($maximumMultipartBytes < 1 || $maximumMultipartBytes >= PHP_INT_MAX)
        ) {
            throw new InvalidArgumentException('Maximum multipart bytes must be positive and safely bounded.');
        }
        $this->maximumBodyBytes = $maximumBodyBytes;
        $this->inputUri = $inputUri;
        $this->maximumMultipartBytes = $maximumMultipartBytes;
    }

    /**
     * @param array<array-key, mixed> $server
     * @param array<array-key, mixed> $query
     * @param array<array-key, mixed> $parsedFields
     * @param array<array-key, mixed> $files
     */
    public function read(
        array $server,
        array $query,
        array $parsedFields = [],
        array $files = [],
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
        $uploads = $this->uploads($method, $headers, $parsedFields, $files);
        $body = $uploads === null ? $this->body($headers) : '';
        try {
            return new Request(
                method: $method,
                path: $path,
                query: $parsedQuery,
                body: $body,
                headers: $headers,
                uploads: $uploads ?? [],
            );
        } catch (InvalidArgumentException $failure) {
            throw new InvalidRequest('PHP runtime input did not produce a valid request.', previous: $failure);
        }
    }

    /**
     * @param array<string, string> $headers
     * @param array<array-key, mixed> $parsedFields
     * @param array<array-key, mixed> $files
     * @return array<string, RequestUpload>|null
     */
    private function uploads(
        string $method,
        array $headers,
        array $parsedFields,
        array $files,
    ): ?array {
        $contentType = $headers['content-type'] ?? null;
        if (
            $contentType === null
            || preg_match('@^ *multipart/form-data(?: *;| *$)@iD', $contentType) !== 1
        ) {
            if ($files !== []) {
                throw new InvalidRequest('Uploaded files require multipart/form-data.');
            }
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
        $maximumMultipartBytes = $this->maximumMultipartBytes;
        if ($declaredBytes === null) {
            throw new InvalidRequest('Multipart requests require Content-Length.');
        }
        if ($maximumMultipartBytes === null) {
            throw new UnsupportedMediaType('Multipart form data is not enabled.');
        }
        if ($declaredBytes > $maximumMultipartBytes) {
            throw new RequestBodyTooLarge('Declared multipart request exceeds its byte limit.');
        }
        if ($parsedFields !== [] || count($files) > 1) {
            throw new InvalidRequest('Multipart requests accept one file and no text fields.');
        }
        if ($files === []) {
            return [];
        }
        $field = array_key_first($files);
        $file = $files[$field];
        if (
            !is_string($field)
            || $field === ''
            || preg_match('/[\x00-\x1F\x7F]/', $field) === 1
            || !is_array($file)
            || array_diff(array_keys($file), ['name', 'full_path', 'type', 'tmp_name', 'error', 'size']) !== []
            || !isset($file['name'], $file['type'], $file['tmp_name'], $file['error'], $file['size'])
            || !is_string($file['name'])
            || !is_string($file['type'])
            || !is_string($file['tmp_name'])
            || !is_int($file['error'])
            || !is_int($file['size'])
        ) {
            throw new InvalidRequest('The uploaded file is missing typed flat metadata.');
        }
        $clientFullPath = array_key_exists('full_path', $file) ? $file['full_path'] : '';
        if (
            !is_string($clientFullPath)
            || $file['size'] < 0
            || $file['size'] > $declaredBytes
            || strlen($file['tmp_name']) > self::MAXIMUM_TEMPORARY_PATH_BYTES
            || preg_match(
                '/[\x00-\x1F\x7F]/',
                $field . $file['name'] . $clientFullPath . $file['type'] . $file['tmp_name'],
            ) === 1
        ) {
            throw new InvalidRequest('The uploaded file metadata has an invalid representation.');
        }
        $error = RequestUploadError::tryFrom($file['error']);
        if ($error === null) {
            throw new RuntimeException('PHP reported an unknown upload error.');
        }
        if ($error === RequestUploadError::Success && ($file['name'] === '' || $file['tmp_name'] === '')) {
            throw new InvalidRequest('A successful upload requires a client filename and temporary path.');
        }
        if ($error === RequestUploadError::NoFile && ($file['name'] !== '' || $clientFullPath !== '' || $file['type'] !== '' || $file['tmp_name'] !== '' || $file['size'] !== 0)) {
            throw new InvalidRequest('A missing upload cannot contain file metadata.');
        }
        return [$field => new RequestUpload(
            $file['name'],
            $file['type'],
            $file['tmp_name'],
            $file['size'],
            $error,
        )];
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
