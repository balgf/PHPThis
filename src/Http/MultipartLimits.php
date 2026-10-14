<?php

declare(strict_types=1);

namespace PHPThis\Http;

use InvalidArgumentException;

final readonly class MultipartLimits
{
    public function __construct(
        public int $maximumRequestBytes,
        public int $maximumFileBytes,
        public int $maximumTotalFileBytes,
    ) {
        if (
            $maximumRequestBytes < 1 || $maximumRequestBytes >= PHP_INT_MAX
            || $maximumFileBytes < 1 || $maximumTotalFileBytes < 1
            || $maximumFileBytes > $maximumTotalFileBytes
            || $maximumTotalFileBytes > $maximumRequestBytes
        ) {
            throw new InvalidArgumentException('Multipart limits require positive bounded file, total-file and request bytes in that order.');
        }
    }
}
