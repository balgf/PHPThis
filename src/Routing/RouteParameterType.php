<?php

declare(strict_types=1);

namespace PHPThis\Routing;

enum RouteParameterType: string
{
    case PositiveInteger = 'positive-int';
    case Token = 'token';
    case Uuid = 'uuid';
    case Ulid = 'ulid';

    public function accepts(string $segment): bool
    {
        return match ($this) {
            self::PositiveInteger => self::positiveInteger($segment) !== null,
            self::Token => self::isToken($segment),
            self::Uuid => self::isUuid($segment),
            self::Ulid => self::isUlid($segment),
        };
    }

    public static function positiveInteger(string $segment): ?int
    {
        if (preg_match('/^[1-9][0-9]*$/D', $segment) !== 1) {
            return null;
        }

        $maximum = (string) PHP_INT_MAX;
        $length = strlen($segment);
        $maximumLength = strlen($maximum);

        if (
            $length > $maximumLength
            || ($length === $maximumLength && strcmp($segment, $maximum) > 0)
        ) {
            return null;
        }

        return (int) $segment;
    }

    public static function isToken(string $segment): bool
    {
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D', $segment) === 1;
    }

    public static function isUuid(string $segment): bool
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',
            $segment,
        ) === 1;
    }

    public static function isUlid(string $segment): bool
    {
        return preg_match('/^[0-7][0-9abcdefghjkmnpqrstvwxyz]{25}$/D', $segment) === 1;
    }
}
