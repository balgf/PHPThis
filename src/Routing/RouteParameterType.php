<?php

declare(strict_types=1);

namespace PHPThis\Routing;

enum RouteParameterType: string
{
    case PositiveInteger = 'positive-int';
    case Token = 'token';

    public function accepts(string $segment): bool
    {
        return match ($this) {
            self::PositiveInteger => self::positiveInteger($segment) !== null,
            self::Token => self::isToken($segment),
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
}
