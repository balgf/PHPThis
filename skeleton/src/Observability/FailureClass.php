<?php

declare(strict_types=1);

namespace App\Observability;

use Throwable;

final class FailureClass
{
    /** @return class-string<Throwable> */
    public static function fromThrowable(Throwable $failure): string
    {
        $class = $failure::class;

        if (!str_contains($class, '@anonymous')) {
            return $class;
        }

        $parent = get_parent_class($failure);

        if (is_string($parent) && is_a($parent, Throwable::class, true)) {
            return $parent;
        }

        return Throwable::class;
    }

    private function __construct()
    {
    }
}
