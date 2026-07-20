<?php

declare(strict_types=1);

namespace App\Observability;

use RuntimeException;

final class ErrorLogRequestSummarySink implements RequestSummarySink
{
    public function emit(RequestSummary $summary): void
    {
        $encoded = json_encode(
            $summary->toArray(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );

        if (!error_log($encoded)) {
            throw new RuntimeException('Unable to emit the request summary.');
        }
    }
}
