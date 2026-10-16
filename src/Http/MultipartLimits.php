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
        public int $maximumFieldBytes = 8_192,
        public int $maximumTotalFieldBytes = 65_536,
    ) {
        if (
            $maximumRequestBytes < 1 || $maximumRequestBytes >= PHP_INT_MAX
            || $maximumFileBytes < 1 || $maximumTotalFileBytes < 1
            || $maximumFileBytes > $maximumTotalFileBytes
            || $maximumTotalFileBytes > $maximumRequestBytes
        ) {
            throw new InvalidArgumentException('Multipart limits require positive bounded file, total-file and request bytes in that order.');
        }
        if (
            $maximumFieldBytes < 1 || $maximumTotalFieldBytes < 1
            || $maximumFieldBytes > $maximumTotalFieldBytes || $maximumTotalFieldBytes >= PHP_INT_MAX
        ) {
            throw new InvalidArgumentException('Multipart field limits require positive bounded field and total-field bytes in that order.');
        }
    }
}
