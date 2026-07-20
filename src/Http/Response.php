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
        if ($status < 100 || $status > 599) {
            throw new InvalidArgumentException('Response status must be between 100 and 599.');
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

        if ($fileBody !== null && (
            $body !== ''
            || $status < 200
            || in_array($status, [204, 205, 206, 304], true)
            || isset($normalizedHeaderNames['content-range'])
            || isset($normalizedHeaderNames['transfer-encoding'])
            || $contentLength !== (string) $fileBody->bytes
        )) {
            throw new InvalidArgumentException('Local file response framing is invalid or unsupported.');
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
