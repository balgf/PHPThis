<?php

declare(strict_types=1);

namespace PHPThis\Session;

use InvalidArgumentException;

final readonly class SessionSnapshot
{
    private const int MAXIMUM_VALUES = 64;
    private const int MAXIMUM_KEY_BYTES = 64;
    private const int MAXIMUM_STRING_BYTES = 8_192;
    private const int MAXIMUM_TOTAL_STRING_BYTES = 65_536;

    /** @var array<string, bool|int|string|null> */
    public array $values;

    /** @param array<array-key, mixed> $values */
    public function __construct(array $values)
    {
        if (count($values) > self::MAXIMUM_VALUES) {
            throw new InvalidArgumentException('Session snapshot contains too many values.');
        }

        $parsed = [];
        $totalStringBytes = 0;

        foreach ($values as $name => $value) {
            if (
                !is_string($name)
                || $name === ''
                || strlen($name) > self::MAXIMUM_KEY_BYTES
                || str_starts_with($name, '__phpthis_')
                || preg_match('/^[A-Za-z][A-Za-z0-9_.-]*$/D', $name) !== 1
            ) {
                throw new InvalidArgumentException('Session value names must be bounded application-owned tokens.');
            }

            if (
                !is_bool($value)
                && !is_int($value)
                && !is_string($value)
                && $value !== null
            ) {
                throw new InvalidArgumentException('Session values must be bool, int, string, or null.');
            }

            if (is_string($value) && strlen($value) > self::MAXIMUM_STRING_BYTES) {
                throw new InvalidArgumentException('Session string value exceeds its byte limit.');
            }

            if (is_string($value)) {
                $totalStringBytes += strlen($value);

                if ($totalStringBytes > self::MAXIMUM_TOTAL_STRING_BYTES) {
                    throw new InvalidArgumentException('Session string values exceed their combined byte limit.');
                }
            }

            $parsed[$name] = $value;
        }

        $this->values = $parsed;
    }
}
