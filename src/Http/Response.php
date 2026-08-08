<?php

declare(strict_types=1);

namespace PHPThis\Http;

use InvalidArgumentException;

final readonly class Response
{
    /** @var list<ResponseCookie> */
    public array $cookies;

    /**
     * @param array<string, string> $headers
     * @param list<mixed> $cookies
     */
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
        array $cookies = [],
        public ?LocalFileBody $fileBody = null,
    ) {
        if ($status < 200 || $status > 599) {
            throw new InvalidArgumentException('Final response status must be between 200 and 599.');
        }

        $normalizedHeaderNames = [];
        $contentLength = null;
        foreach ($headers as $name => $value) {
            $normalizedName = strtolower($name);
            if (
                $name === ''
                || preg_match('/^[A-Za-z0-9-]+$/D', $name) !== 1
                || isset($normalizedHeaderNames[$normalizedName])
            ) {
                throw new InvalidArgumentException('Response contains an invalid or duplicate header name.');
            }

            $normalizedHeaderNames[$normalizedName] = true;
            if ($normalizedName === 'content-length') {
                $contentLength = $value;
            }
            if (
                $normalizedName === 'set-cookie'
                || preg_match('/[\x00-\x1F\x7F]/', $value) === 1
            ) {
                throw new InvalidArgumentException('Response contains an invalid header value.');
            }
        }

        if (
            isset($normalizedHeaderNames['transfer-encoding'])
            || (in_array($status, [204, 205, 304], true) && ($body !== '' || $contentLength !== null))
            || ($fileBody === null
                ? ($contentLength !== null && $contentLength !== (string) strlen($body))
                : ($body !== '' || $status === 206 || isset($normalizedHeaderNames['content-range'])
                    || $contentLength !== (string) $fileBody->bytes))
        ) {
            throw new InvalidArgumentException('Response framing is invalid or unsupported.');
        }

        $cookieScopes = [];
        $parsedCookies = [];

        foreach ($cookies as $cookie) {
            if (!$cookie instanceof ResponseCookie) {
                throw new InvalidArgumentException('Response cookies must be ResponseCookie values.');
            }

            $scope = $cookie->name . "\0" . $cookie->path;
            if (isset($cookieScopes[$scope])) {
                throw new InvalidArgumentException('Response contains a duplicate cookie name and path.');
            }
            $cookieScopes[$scope] = true;
            $parsedCookies[] = $cookie;
        }

        $this->cookies = $parsedCookies;
    }
}
