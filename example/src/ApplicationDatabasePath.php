<?php

declare(strict_types=1);

namespace Example;

final readonly class ApplicationDatabasePath
{
    private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $isAbsolute = DIRECTORY_SEPARATOR === '\\'
            ? preg_match('/\A[A-Za-z]:[\\\\\/]/D', $value) === 1
            : str_starts_with($value, '/');

        if (
            $value === ''
            || strlen($value) > 4_096
            || !$isAbsolute
            || str_ends_with($value, '/')
            || str_ends_with($value, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $value) === 1
        ) {
            throw new InvalidApplicationDatabasePath();
        }

        return new self($value);
    }
}
