<?php

declare(strict_types=1);

namespace PHPThis\Routing;

use InvalidArgumentException;
use OutOfBoundsException;

final readonly class PathParameters
{
    /**
     * @param array<string, int> $positiveIntegers
     * @param array<string, string> $tokens
     */
    private function __construct(
        private array $positiveIntegers,
        private array $tokens,
    ) {
    }

    public static function none(): self
    {
        return new self([], []);
    }

    public static function onePositiveInteger(string $name, int $value): self
    {
        return self::fromValues([$name => $value], []);
    }

    /**
     * @param array<array-key, mixed> $positiveIntegers
     * @param array<array-key, mixed> $tokens
     */
    public static function fromValues(array $positiveIntegers, array $tokens): self
    {
        if (count($positiveIntegers) + count($tokens) > 2) {
            throw new InvalidArgumentException('Path parameters cannot contain more than two values.');
        }

        $validatedPositiveIntegers = [];
        $validatedTokens = [];

        foreach ($positiveIntegers as $name => $value) {
            if (!is_string($name) || !is_int($value)) {
                throw new InvalidArgumentException(
                    'Positive-integer path parameters require string names and integer values.',
                );
            }

            self::validateName($name);

            if ($value < 1) {
                throw new InvalidArgumentException(
                    'Positive-integer path parameter must be greater than zero.',
                );
            }

            $validatedPositiveIntegers[$name] = $value;
        }

        foreach ($tokens as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                throw new InvalidArgumentException(
                    'Token path parameters require string names and string values.',
                );
            }

            self::validateName($name);

            if (isset($validatedPositiveIntegers[$name])) {
                throw new InvalidArgumentException("Path parameter {$name} has multiple types.");
            }

            if (!RouteParameterType::isToken($value)) {
                throw new InvalidArgumentException('Token path parameter is not canonical.');
            }

            $validatedTokens[$name] = $value;
        }

        return new self($validatedPositiveIntegers, $validatedTokens);
    }

    public function positiveInteger(string $name): int
    {
        if (!isset($this->positiveIntegers[$name])) {
            throw new OutOfBoundsException("No positive-integer path parameter named {$name}.");
        }

        return $this->positiveIntegers[$name];
    }

    public function token(string $name): string
    {
        if (!isset($this->tokens[$name])) {
            throw new OutOfBoundsException("No token path parameter named {$name}.");
        }

        return $this->tokens[$name];
    }

    private static function validateName(string $name): void
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/D', $name) !== 1) {
            throw new InvalidArgumentException(
                'Path parameter name must be lowercase snake-like ASCII.',
            );
        }
    }
}
