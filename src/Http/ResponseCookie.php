<?php

declare(strict_types=1);

namespace PHPThis\Http;

use InvalidArgumentException;

final readonly class ResponseCookie
{
    private const int MAXIMUM_NAME_VALUE_BYTES = 4_096;
    private const int MAXIMUM_PATH_BYTES = 1_024;
    private const int MAXIMUM_AGE_SECONDS = 34_560_000;

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
        if (
            strlen($name) > self::MAXIMUM_NAME_VALUE_BYTES
            || preg_match('/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/D', $name) !== 1
        ) {
            throw new InvalidArgumentException('Response cookie name must be an HTTP token within the 4096-byte name/value limit.');
        }

        if (
            strlen($name) + strlen($value) > self::MAXIMUM_NAME_VALUE_BYTES
            || preg_match('/^[\x21\x23-\x2B\x2D-\x3A\x3C-\x5B\x5D-\x7E]*$/D', $value) !== 1
        ) {
            throw new InvalidArgumentException('Response cookie name and value must contain at most 4096 cookie-safe ASCII bytes.');
        }
        if (
            $path === '' || $path[0] !== '/' || strlen($path) > self::MAXIMUM_PATH_BYTES
            || preg_match('/[\x00-\x20;\x7F-\xFF]/', $path) === 1
        ) {
            throw new InvalidArgumentException('Response cookie path must be an absolute cookie-safe path of at most 1024 bytes.');
        }
        if ($expiresAt !== null && ($expiresAt < 1 || strlen(gmdate('Y', $expiresAt)) !== 4)) {
            throw new InvalidArgumentException('Response cookie expiration must use a four-digit UTC year.');
        }
        if ($maximumAgeSeconds !== null && ($maximumAgeSeconds < 0 || $maximumAgeSeconds > self::MAXIMUM_AGE_SECONDS)) {
            throw new InvalidArgumentException('Response cookie maximum age must be between 0 and 34560000 seconds.');
        }
        if ($sameSite === CookieSameSite::None && !$secure) {
            throw new InvalidArgumentException('SameSite=None cookies must be Secure.');
        }
        $lowercaseName = strtolower($name);
        if (str_starts_with($lowercaseName, '__host-') && (!$secure || $path !== '/')) {
            throw new InvalidArgumentException('__Host- cookies must be Secure and use Path=/.');
        }
        if (str_starts_with($lowercaseName, '__secure-') && !$secure) {
            throw new InvalidArgumentException('__Secure- cookies must be Secure.');
        }
        if (
            (str_starts_with($lowercaseName, '__http-') || str_starts_with($lowercaseName, '__host-http-'))
            && (!$secure || !$httpOnly)
        ) {
            throw new InvalidArgumentException('__Http- cookies must be Secure and HttpOnly.');
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
