<?php

declare(strict_types=1);

namespace PHPThis\Http;

use InvalidArgumentException;

final readonly class LocalFileBody
{
    public function __construct(public string $path, public int $bytes)
    {
        $isAbsolute = DIRECTORY_SEPARATOR === '\\'
            ? preg_match('/\A[A-Za-z]:[\\\\\/]/D', $path) === 1
            : str_starts_with($path, '/');
        if (
            $path === '' || strlen($path) > 4_096 || !$isAbsolute
            || $bytes < 0 || preg_match('/[\x00-\x1F\x7F]/', $path) === 1
        ) {
            throw new InvalidArgumentException('Local file body path or length is invalid.');
        }
    }
}
