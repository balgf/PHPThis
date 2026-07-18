<?php

declare(strict_types=1);

namespace PHPThis\Http;

use InvalidArgumentException;

final readonly class ResponseCookie
{
    public function __construct(
        public string $name,
        public string $value,
        public string $path,
        public bool $secure,
        public bool $httpOnly,
        public CookieSameSite $sameSite,
        public ?int $expiresAt = null,
        public ?int $maximumAgeSeconds = null,
    ) {
        if (preg_match('/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/D', $name) !== 1) {
            throw new InvalidArgumentException('Response cookie name must be an HTTP token.');
        }

        if (preg_match('/^[\x21\x23-\x2B\x2D-\x3A\x3C-\x5B\x5D-\x7E]*$/D', $value) !== 1) {
            throw new InvalidArgumentException('Response cookie value must contain cookie-safe ASCII bytes.');
        }

        if ($path === '' || $path[0] !== '/' || preg_match('/[\x00-\x20;\x7F]/', $path) === 1) {
            throw new InvalidArgumentException('Response cookie path must be an absolute cookie-safe path.');
        }

        if ($expiresAt !== null && $expiresAt < 1) {
            throw new InvalidArgumentException('Response cookie expiration must be a positive Unix timestamp.');
        }

        if ($maximumAgeSeconds !== null && $maximumAgeSeconds < 0) {
            throw new InvalidArgumentException('Response cookie maximum age cannot be negative.');
        }

        if ($sameSite === CookieSameSite::None && !$secure) {
            throw new InvalidArgumentException('SameSite=None cookies must be Secure.');
        }

        if (str_starts_with($name, '__Host-') && (!$secure || $path !== '/')) {
            throw new InvalidArgumentException('__Host- cookies must be Secure and use Path=/.');
        }

        if (str_starts_with($name, '__Secure-') && !$secure) {
            throw new InvalidArgumentException('__Secure- cookies must be Secure.');
        }
    }

    public function headerValue(): string
    {
        $parts = ["{$this->name}={$this->value}", "Path={$this->path}"];

        if ($this->expiresAt !== null) {
            $parts[] = 'Expires=' . gmdate('D, d M Y H:i:s \G\M\T', $this->expiresAt);
        }

        if ($this->maximumAgeSeconds !== null) {
            $parts[] = "Max-Age={$this->maximumAgeSeconds}";
        }

        if ($this->secure) {
            $parts[] = 'Secure';
        }

        if ($this->httpOnly) {
            $parts[] = 'HttpOnly';
        }

        $parts[] = "SameSite={$this->sameSite->value}";

        return implode('; ', $parts);
    }
}
