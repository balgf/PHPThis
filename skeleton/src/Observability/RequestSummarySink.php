<?php

declare(strict_types=1);

namespace App\Observability;

interface RequestSummarySink
{
    public function emit(RequestSummary $summary): void;
}
