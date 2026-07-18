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
    ) {
        if ($status < 100 || $status > 599) {
            throw new InvalidArgumentException('Response status must be between 100 and 599.');
        }

        foreach ($headers as $name => $value) {
            if ($name === '' || preg_match('/^[A-Za-z0-9-]+$/D', $name) !== 1) {
                throw new InvalidArgumentException('Response contains an invalid header name.');
            }

            if (strtolower($name) === 'set-cookie') {
                throw new InvalidArgumentException('Set-Cookie must use an explicit ResponseCookie value.');
            }

            if (str_contains($value, "\r") || str_contains($value, "\n")) {
                throw new InvalidArgumentException('Response header values cannot contain newlines.');
            }
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
