<?php

declare(strict_types=1);

namespace PHPThis\Routing;

use InvalidArgumentException;

final readonly class RouteSegment
{
    private function __construct(
        public ?string $literal,
        public ?string $parameterName,
        public ?RouteParameterType $parameterType,
    ) {
    }

    public static function literal(string $literal): self
    {
        return new self($literal, null, null);
    }

    public static function parameter(string $name, RouteParameterType $type): self
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/D', $name) !== 1) {
            throw new InvalidArgumentException(
                'Path parameter name must be lowercase snake-like ASCII.',
            );
        }

        return new self(null, $name, $type);
    }
}
