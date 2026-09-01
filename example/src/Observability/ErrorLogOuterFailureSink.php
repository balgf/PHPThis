<?php

declare(strict_types=1);

namespace Example\Observability;

use RuntimeException;
use Throwable;

final class ErrorLogOuterFailureSink
{
    public function emit(Throwable $failure): void
    {
        if (!error_log(
            'application.http_outer_failure failure_class=' . FailureClass::fromThrowable($failure),
        )) {
            throw new RuntimeException('Unable to emit the outer HTTP failure event.');
        }
    }
}
