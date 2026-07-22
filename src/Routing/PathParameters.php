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
     * @param array<string, string> $uuids
     * @param array<string, string> $ulids
     */
    private function __construct(
        private array $positiveIntegers,
        private array $tokens,
        private array $uuids,
        private array $ulids,
    ) {
    }

    public static function none(): self
    {
        return new self([], [], [], []);
    }

    public static function onePositiveInteger(string $name, int $value): self
    {
        return self::fromValues([$name => $value], [], [], []);
    }

    /**
     * @param array<array-key, mixed> $positiveIntegers
     * @param array<array-key, mixed> $tokens
     * @param array<array-key, mixed> $uuids
     * @param array<array-key, mixed> $ulids
     */
    public static function fromValues(
        array $positiveIntegers,
        array $tokens,
        array $uuids = [],
        array $ulids = [],
    ): self {
        if (count($positiveIntegers) + count($tokens) + count($uuids) + count($ulids) > 2) {
            throw new InvalidArgumentException('Path parameters cannot contain more than two values.');
        }

        $validatedPositiveIntegers = [];

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

        $validatedTokens = self::canonicalStrings(
            $tokens,
            RouteParameterType::Token,
            $validatedPositiveIntegers,
        );
        $validatedUuids = self::canonicalStrings(
            $uuids,
            RouteParameterType::Uuid,
            $validatedPositiveIntegers + $validatedTokens,
        );
        $validatedUlids = self::canonicalStrings(
            $ulids,
            RouteParameterType::Ulid,
            $validatedPositiveIntegers + $validatedTokens + $validatedUuids,
        );

        return new self(
            $validatedPositiveIntegers,
            $validatedTokens,
            $validatedUuids,
            $validatedUlids,
        );
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
        return $this->stringValue($this->tokens, $name, RouteParameterType::Token);
    }

    public function uuid(string $name): string
    {
        return $this->stringValue($this->uuids, $name, RouteParameterType::Uuid);
    }

    public function ulid(string $name): string
    {
        return $this->stringValue($this->ulids, $name, RouteParameterType::Ulid);
    }

    private static function validateName(string $name): void
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/D', $name) !== 1) {
            throw new InvalidArgumentException(
                'Path parameter name must be lowercase snake-like ASCII.',
            );
        }
    }

    /**
     * @param array<array-key, mixed> $values
     * @param array<string, mixed> $occupiedNames
     * @return array<string, string>
     */
    private static function canonicalStrings(
        array $values,
        RouteParameterType $type,
        array $occupiedNames,
    ): array {
        $validated = [];

        foreach ($values as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                throw new InvalidArgumentException(
                    "{$type->value} path parameters require string names and string values.",
                );
            }

            self::validateName($name);

            if (isset($occupiedNames[$name])) {
                throw new InvalidArgumentException("Path parameter {$name} has multiple types.");
            }

            if (!$type->accepts($value)) {
                throw new InvalidArgumentException(
                    "{$type->value} path parameter is not canonical.",
                );
            }

            $validated[$name] = $value;
        }

        return $validated;
    }

    /** @param array<string, string> $values */
    private function stringValue(
        array $values,
        string $name,
        RouteParameterType $type,
    ): string {
        if (!isset($values[$name])) {
            throw new OutOfBoundsException(
                "No {$type->value} path parameter named {$name}.",
            );
        }

        return $values[$name];
    }
}
