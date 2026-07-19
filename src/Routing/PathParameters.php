<?php

declare(strict_types=1);

namespace PHPThis\Routing;

use InvalidArgumentException;
use OutOfBoundsException;

final readonly class PathParameters
{
    private function __construct(
        private ?string $name,
        private ?int $positiveInteger,
    ) {
    }

    public static function none(): self
    {
        return new self(null, null);
    }

    public static function onePositiveInteger(string $name, int $value): self
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/D', $name) !== 1) {
            throw new InvalidArgumentException('Path parameter name must be lowercase snake-like ASCII.');
        }

        if ($value < 1) {
            throw new InvalidArgumentException('Positive-integer path parameter must be greater than zero.');
        }

        return new self($name, $value);
    }

    public function positiveInteger(string $name): int
    {
        if ($this->name !== $name || $this->positiveInteger === null) {
            throw new OutOfBoundsException("No positive-integer path parameter named {$name}.");
        }

        return $this->positiveInteger;
    }
}
