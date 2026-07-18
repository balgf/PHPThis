<?php

declare(strict_types=1);

namespace PHPThis\Session;

use InvalidArgumentException;
use PHPThis\Http\CookieSameSite;
use PHPThis\Http\ResponseCookie;

final readonly class SessionConfiguration
{
    public function __construct(
        public string $internalName,
        public string $cookieName,
        public bool $cookieSecure,
        public CookieSameSite $cookieSameSite,
        public string $nativeSavePath,
    ) {
        if (
            preg_match('/^(?=.*[A-Za-z])[A-Za-z0-9]+$/D', $internalName) !== 1 || strlen($internalName) > 64
        ) {
            throw new InvalidArgumentException('Internal session name must contain a letter and at most 64 alphanumeric bytes.');
        }

        if (
            preg_match('/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/D', $cookieName) !== 1
            || strlen($cookieName) > 128
        ) {
            throw new InvalidArgumentException('Session cookie name must be an HTTP token.');
        }

        if (
            (str_starts_with($cookieName, '__Host-') || str_starts_with($cookieName, '__Secure-'))
            && !$cookieSecure
        ) {
            throw new InvalidArgumentException('Prefixed session cookies must be Secure.');
        }

        if ($cookieSameSite === CookieSameSite::None && !$cookieSecure) {
            throw new InvalidArgumentException('SameSite=None session cookies must be Secure.');
        }

        if ($nativeSavePath === '' || preg_match('/[\x00\r\n]/', $nativeSavePath) === 1) {
            throw new InvalidArgumentException('Native session save path must be an explicit safe value.');
        }
    }

    public function liveCookie(string $id): ResponseCookie
    {
        return new ResponseCookie(
            $this->cookieName,
            $id,
            '/',
            $this->cookieSecure,
            true,
            $this->cookieSameSite,
        );
    }

    public function expiredCookie(): ResponseCookie
    {
        return new ResponseCookie(
            $this->cookieName,
            '',
            '/',
            $this->cookieSecure,
            true,
            $this->cookieSameSite,
            1,
            0,
        );
    }
}
